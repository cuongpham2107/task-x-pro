<?php

namespace App\Services\Phases;

use App\Models\Phase;
use App\Models\Project;
use App\Models\User;
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
        Gate::forUser($actor)->authorize('update', $phase);

        return $phase;
    }

    public function formOptions(): array
    {
        return $this->queryService->formOptions();
    }

    public function create(User $actor, Project $project, array $attributes): Phase
    {
        Gate::forUser($actor)->authorize('create', Phase::class);
        Gate::forUser($actor)->authorize('update', $project);

        return $this->mutationService->create($actor, $project, $attributes);
    }

    public function update(User $actor, Phase $phase, array $attributes): Phase
    {
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
        Gate::forUser($actor)->authorize('update', $phase);

        return $this->mutationService->update($actor, $phase, ['status' => $status]);
    }

    public function reorder(User $actor, Project $project, array $phaseIds): void
    {
        Gate::forUser($actor)->authorize('update', $project);
        $this->mutationService->reorder($actor, $project, $phaseIds);
    }
}
