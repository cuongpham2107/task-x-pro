<?php

namespace App\Services\Departments;

use App\Models\Department;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

class DepartmentService
{
    public function __construct(
        private readonly DepartmentQueryService $queryService,
        private readonly DepartmentMutationService $mutationService,
    ) {}

    /**
     * Lay du lieu danh sach phong ban cho man hinh index.
     */
    public function paginateForIndex(?User $actor, array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        if ($actor) {
            Gate::forUser($actor)->authorize('viewAny', Department::class);
        }

        // Pass the actor to the query service so it can apply role-based restrictions
        return $this->queryService->paginateForIndex($filters, $perPage, $actor);
    }

    /**
     * Lay chi tiet phong ban cho man hinh sua.
     */
    public function findForEdit(User $actor, int $departmentId): Department
    {
        $department = $this->queryService->findForEdit($departmentId);

        // Authorize update for edit flow (previously used 'view' which could cause 403
        // when user has update permission but not view permission).
        Gate::forUser($actor)->authorize('update', $department);

        return $department;
    }

    /**
     * Lay du lieu tong quan cho card thong ke phong ban.
     *
     * @return array{
     *     total_departments: int,
     *     active_departments: int,
     *     inactive_departments: int,
     *     total_members: int,
     *     active_members: int,
     *     average_kpi: float
     * }
     */
    public function summaryStats(?User $actor): array
    {
        if ($actor) {
            Gate::forUser($actor)->authorize('viewAny', Department::class);
        }

        return $this->queryService->summaryStats();
    }

    /**
     * Tra ve danh muc option cho form tao/sua phong ban.
     */
    public function formOptions(): array
    {
        return $this->queryService->formOptions();
    }

    /**
     * Tao phong ban moi.
     */
    public function create(User $actor, array $attributes): Department
    {
        return $this->mutationService->create($actor, $attributes);
    }

    /**
     * Cap nhat thong tin phong ban.
     */
    public function update(User $actor, Department $department, array $attributes): Department
    {
        return $this->mutationService->update($actor, $department, $attributes);
    }

    /**
     * Xoa phong ban.
     */
    public function delete(User $actor, Department $department): void
    {
        $this->mutationService->delete($actor, $department);
    }
}
