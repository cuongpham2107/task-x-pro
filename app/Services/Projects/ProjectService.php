<?php

namespace App\Services\Projects;

use App\Models\Project;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class ProjectService
{
    public function __construct(
        private readonly ProjectQueryService $queryService,
        private readonly ProjectMutationService $mutationService,
    ) {}

    /**
     * Lay du lieu danh sach project cho man hinh index.
     */
    public function paginateForIndex(?User $actor, array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        if ($actor) {
            Gate::forUser($actor)->authorize('viewAny', Project::class);
        }

        return $this->queryService->paginateForIndex($actor, $filters, $perPage);
    }

    /**
     * Lay so luong project theo cac tab.
     *
     * @return array<string, array{label: string, count: int}>
     */
    public function getTabCounts(?User $actor): array
    {
        return $this->queryService->getTabCounts($actor);
    }

    /**
     * Tra ve danh sach loai du an lam type filter.
     *
     * @return array<string, string>
     */
    public function getTypeOptions(): array
    {
        return $this->queryService->getTypeOptions();
    }

    /**
     * Lay chi tiet project cho man hinh edit.
     */
    public function findForEdit(User $actor, int $projectId): Project
    {
        $project = $this->queryService->findForEdit($projectId);

        Gate::forUser($actor)->authorize('view', $project);

        return $project;
    }

    /**
     * Tra ve danh muc option cho form tao/sua project.
     *
     * @return array{
     *     project_types: list<string>,
     *     project_type_labels: array<string, string>,
     *     project_statuses: list<string>,
     *     project_status_labels: array<string, string>,
     *     leaders: Collection<int, User>
     * }
     */
    public function formOptions(): array
    {
        return $this->queryService->formOptions();
    }

    /**
     * Tao project moi, tu dong sinh phase template va dong bo leader.
     */
    public function create(
        User $actor,
        array $attributes,
        ?array $leaderIds = null,
        ?array $phasePayloads = null,
    ): Project {
        return $this->mutationService->create($actor, $attributes, $leaderIds, $phasePayloads);
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
        return $this->mutationService->update($actor, $project, $attributes, $leaderIds, $phasePayloads);
    }

    /**
     * Dong bo phase cho project va validate tong trong so = 100.
     */
    public function syncPhases(User $actor, Project $project, array $phasePayloads): void
    {
        $this->mutationService->syncPhases($actor, $project, $phasePayloads);
    }

    /**
     * Xoa project theo policy hien tai.
     */
    public function delete(User $actor, Project $project): void
    {
        $this->mutationService->delete($actor, $project);
    }
}
