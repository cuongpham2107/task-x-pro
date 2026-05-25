<?php

namespace App\Services\Phases;

use App\Enums\ProjectStatus;
use App\Models\Phase;
use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class PhaseService
{
    public function __construct(
        private readonly PhaseQueryService $queryService,
        private readonly PhaseMutationService $mutationService,
    ) {}

    public function getForProject(User $actor, Project $project): Collection
    {
        Gate::forUser($actor)->authorize('view', $project);

        return $this->queryService->getForProject($project, $actor);
    }

    public function findForEdit(User $actor, int $phaseId): Phase
    {
        $phase = $this->queryService->findForEdit($phaseId);
        $this->guardProjectNotPaused($phase->project);
        Gate::forUser($actor)->authorize('update', $phase);

        return $phase;
    }

    public function formOptions(): array
    {
        return $this->queryService->formOptions();
    }

    public function create(User $actor, Project $project, array $attributes): Phase
    {
        $this->guardProjectNotPaused($project);
        Gate::forUser($actor)->authorize('create', [Phase::class, $project]);
        Gate::forUser($actor)->authorize('update', $project);

        return $this->mutationService->create($actor, $project, $attributes);
    }

    public function update(User $actor, Phase $phase, array $attributes): Phase
    {
        $this->guardProjectNotPaused($phase->project);
        Gate::forUser($actor)->authorize('update', $phase);

        return $this->mutationService->update($actor, $phase, $attributes);
    }

    public function delete(User $actor, Phase $phase): void
    {
        Gate::forUser($actor)->authorize('delete', $phase);
        $this->mutationService->delete($actor, $phase);
    }

    /**
     * Update the status of a phase (e.g., 'active', 'completed').
     */
    public function updateStatus(User $actor, Phase $phase, string $status): Phase
    {
        $this->guardProjectNotPaused($phase->project);
        Gate::forUser($actor)->authorize('update', $phase);

        return $this->mutationService->update($actor, $phase, ['status' => $status]);
    }

    public function reorder(User $actor, Project $project, array $phaseIds): void
    {
        $this->guardProjectNotPaused($project);
        Gate::forUser($actor)->authorize('update', $project);
        $this->mutationService->reorder($actor, $project, $phaseIds);
    }

    private function guardProjectNotPaused(?Project $project): void
    {
        if ($project === null) {
            return;
        }

        if (in_array($project->status, [
            ProjectStatus::Completed,
            ProjectStatus::Cancelled,
            ProjectStatus::Paused,
            ProjectStatus::Overdue,
        ], true)) {
            throw new AuthorizationException('Dự án đang tạm dừng hoặc đã kết thúc, không thể thực hiện thao tác này.');
        }
    }
}
