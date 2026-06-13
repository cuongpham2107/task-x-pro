<?php

namespace App\Services\Projects;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ProjectMutationService
{
    public function __construct(
        private readonly ProjectPayloadService $payloadService,
        private readonly ProjectPhaseService $phaseService,
        private readonly ProjectQueryService $queryService,
    ) {}

    /**
     * Tao project moi, tu dong sinh phase template va dong bo leader.
     */
    public function create(
        User $actor,
        array $attributes,
        ?array $leaderIds = null,
        ?array $phasePayloads = null,
    ): Project {
        Gate::forUser($actor)->authorize('create', Project::class);

        return DB::transaction(function () use ($actor, $attributes, $leaderIds, $phasePayloads): Project {
            $project = Project::query()->create(
                $this->payloadService->normalizedProjectAttributes($attributes, $actor->id, false)
            );

            $this->payloadService->syncLeaders($project, $leaderIds ?? [], $actor->id);

            if (is_array($phasePayloads)) {
                if ($phasePayloads !== []) {
                    $this->phaseService->upsertPhases($project, $phasePayloads);
                }
            } else {
                $this->phaseService->createPhasesFromTemplate($project);
            }

            $project->refreshProgressFromPhases();
            $project->refresh();

            return $this->queryService->hydrateProject($project);
        });
    }

    /**
     * Cap nhat thong tin project, leader va danh sach phase.
     */
    public function update(
        User $actor,
        Project $project,
        array $attributes,
        ?array $leaderIds = null,
        ?array $phasePayloads = null,
    ): Project {
        Gate::forUser($actor)->authorize('update', $project);

        return DB::transaction(function () use ($actor, $project, $attributes, $leaderIds, $phasePayloads): Project {
            $project->fill($this->payloadService->normalizedProjectAttributes($attributes, $actor->id, true));
            $project->save();

            if ($leaderIds !== null) {
                Gate::forUser($actor)->authorize('assignLeader', $project);
                $this->payloadService->syncLeaders($project, $leaderIds, $actor->id);
            }

            if ($phasePayloads !== null) {
                Gate::forUser($actor)->authorize('syncPhases', $project);
                $this->phaseService->upsertPhases($project, $phasePayloads);
            }

            if (array_key_exists('status', $attributes)) {
                $this->phaseService->syncPhaseStatusesWithProjectStatus($project);
            }

            $project->refreshProgressFromPhases();
            $project->refresh();

            return $this->queryService->hydrateProject($project);
        });
    }

    /**
     * Dong bo phase cho project va validate tong trong so = 100.
     */
    public function syncPhases(User $actor, Project $project, array $phasePayloads): void
    {
        Gate::forUser($actor)->authorize('syncPhases', $project);

        DB::transaction(function () use ($project, $phasePayloads): void {
            $this->phaseService->upsertPhases($project, $phasePayloads);
            $project->refreshProgressFromPhases();
        });
    }

    /**
     * Xoa project theo policy hien tai.
     */
    public function delete(User $actor, Project $project): void
    {
        Gate::forUser($actor)->authorize('delete', $project);
        $project->delete();
    }

    /**
     * Clone project voi tat ca phases va tasks.
     */
    public function clone(User $actor, Project $sourceProject): Project
    {
        Gate::forUser($actor)->authorize('create', Project::class);

        return DB::transaction(function () use ($actor, $sourceProject): Project {
            // Clone project with new name
            $clonedAttributes = $sourceProject->toArray();
            $clonedAttributes['name'] = $sourceProject->name.' (Clone)';
            $clonedAttributes['status'] = 'init';
            $clonedAttributes['created_by'] = $actor->id;
            unset($clonedAttributes['id'], $clonedAttributes['created_at'], $clonedAttributes['updated_at']);

            // Ensure date fields use Y-m-d format to avoid timezone shift via toArray() ISO 8601 serialization
            $clonedAttributes['start_date'] = $sourceProject->start_date?->toDateString();
            $clonedAttributes['end_date'] = $sourceProject->end_date?->toDateString();

            $newProject = Project::query()->create($clonedAttributes);

            // Copy leaders
            $leaderIds = $sourceProject->projectLeaders()
                ->orderByDesc('is_primary')
                ->orderBy('assigned_at')
                ->pluck('user_id')
                ->toArray();
            $this->payloadService->syncLeaders($newProject, $leaderIds, $actor->id);

            // Clone phases and tasks
            foreach ($sourceProject->phases as $sourcePhase) {
                $phaseAttributes = $sourcePhase->toArray();
                $phaseAttributes['project_id'] = $newProject->id;
                unset($phaseAttributes['id'], $phaseAttributes['created_at'], $phaseAttributes['updated_at']);

                // Ensure date fields use Y-m-d format to avoid timezone shift
                $phaseAttributes['start_date'] = $sourcePhase->start_date?->toDateString();
                $phaseAttributes['end_date'] = $sourcePhase->end_date?->toDateString();

                $newPhase = $newProject->phases()->create($phaseAttributes);

                // Clone tasks
                foreach ($sourcePhase->tasks as $sourceTask) {
                    $taskAttributes = $sourceTask->toArray();
                    $taskAttributes['phase_id'] = $newPhase->id;
                    $taskAttributes['status'] = 'pending';
                    $taskAttributes['progress'] = 0;
                    $taskAttributes['started_at'] = null;
                    $taskAttributes['completed_at'] = null;
                    unset($taskAttributes['id'], $taskAttributes['created_at'], $taskAttributes['updated_at']);

                    // Ensure date fields use Y-m-d format to avoid timezone shift
                    $taskAttributes['deadline'] = $sourceTask->deadline?->toDateTimeString();

                    $newTask = $newPhase->tasks()->create($taskAttributes);

                    // Clone co-PICs
                    if ($sourceTask->coPics->isNotEmpty()) {
                        $coPicIds = $sourceTask->coPics->pluck('id')->toArray();
                        $newTask->coPics()->sync($coPicIds);
                    }
                }
            }

            $newProject->refreshProgressFromPhases();
            $newProject->refresh();

            return $this->queryService->hydrateProject($newProject);
        });
    }
}
