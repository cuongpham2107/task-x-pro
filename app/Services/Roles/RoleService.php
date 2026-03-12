<?php

namespace App\Services\Roles;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleService
{
    public function __construct(
        private readonly RoleQueryService $queryService,
        private readonly RoleMutationService $mutationService,
    ) {}

    /**
     * Lay danh sach role cho man hinh index.
     */
    public function paginateForIndex(?User $actor, array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        if ($actor) {
            Gate::forUser($actor)->authorize('viewAny', Role::class);
        }

        return $this->queryService->paginateForIndex($filters, $perPage);
    }

    /**
     * Lay role cho man hinh sua.
     */
    public function findForEdit(User $actor, int $roleId): Role
    {
        $role = $this->queryService->findForEdit($roleId);

        Gate::forUser($actor)->authorize('view', $role);

        return $role;
    }

    /**
     * Tra ve permission theo nhom cho giao dien grid.
     *
     * @return array<string, list<array{id: int, name: string, label: string}>>
     */
    public function permissionGridGroups(?User $actor): array
    {
        if ($actor) {
            Gate::forUser($actor)->authorize('viewAny', Permission::class);
        }

        return $this->queryService->permissionGridGroups();
    }

    /**
     * Tao role moi va gan permission.
     */
    public function createRole(User $actor, array $attributes): Role
    {
        return $this->mutationService->createRole($actor, $attributes);
    }

    /**
     * Cap nhat role.
     */
    public function updateRole(User $actor, Role $role, array $attributes): Role
    {
        return $this->mutationService->updateRole($actor, $role, $attributes);
    }

    /**
     * Xoa role.
     */
    public function deleteRole(User $actor, Role $role): void
    {
        $this->mutationService->deleteRole($actor, $role);
    }
}
