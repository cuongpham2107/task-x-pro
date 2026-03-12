<?php

namespace App\Services\Roles;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleQueryService
{
    /**
     * Lay danh sach role cho man hinh index.
     */
    public function paginateForIndex(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $query = Role::query()
            ->where('guard_name', 'web')
            ->with(['permissions' => function ($query): void {
                $query->orderBy('name');
            }])
            ->withCount(['permissions', 'users']);

        $this->applyFilters($query, $filters);

        $sortBy = $filters['sort'] ?? 'name';
        $sortDir = $filters['dir'] ?? 'asc';
        $allowedSorts = ['name', 'created_at', 'permissions_count', 'users_count'];
        $sortColumn = in_array($sortBy, $allowedSorts, true) ? $sortBy : 'name';
        $sortDirection = $sortDir === 'desc' ? 'desc' : 'asc';

        return $query
            ->orderBy($sortColumn, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Lay role cho man hinh sua.
     */
    public function findForEdit(int $roleId): Role
    {
        return Role::query()
            ->where('guard_name', 'web')
            ->with(['permissions' => function ($query): void {
                $query->orderBy('name');
            }])
            ->findOrFail($roleId);
    }

    /**
     * Lay danh sach permission theo guard.
     *
     * @return Collection<int, Permission>
     */
    public function getPermissions(): Collection
    {
        return Permission::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->get(['id', 'name', 'guard_name']);
    }

    /**
     * Tra ve permission theo nhom module de hien thi grid.
     *
     * @return array<string, list<array{id: int, name: string, label: string}>>
     */
    public function permissionGridGroups(): array
    {
        return $this->getPermissions()
            ->groupBy(function (Permission $permission): string {
                return $this->permissionGroup($permission->name);
            })
            ->map(function (Collection $permissions): array {
                return $permissions
                    ->map(fn (Permission $permission): array => [
                        'id' => (int) $permission->id,
                        'name' => $permission->name,
                        'label' => $this->permissionLabel($permission->name),
                    ])
                    ->values()
                    ->all();
            })
            ->sortKeys()
            ->all();
    }

    /**
     * Ap dung filter tim kiem role.
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where('name', 'like', "%{$search}%");
        }
    }

    /**
     * Lay nhom permission tu dinh dang module.action.
     */
    public function permissionGroup(string $permissionName): string
    {
        $first = Arr::first(explode('.', $permissionName));

        if ($first === null || $first === '') {
            return 'other';
        }

        return (string) $first;
    }

    /**
     * Chuyen permission sang label de doc.
     */
    public function permissionLabel(string $permissionName): string
    {
        $segments = explode('.', $permissionName);

        if (count($segments) === 1) {
            return Str::headline($segments[0]);
        }

        return Str::headline((string) end($segments));
    }
}
