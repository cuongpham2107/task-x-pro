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
}
