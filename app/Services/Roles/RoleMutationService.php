<?php

namespace App\Services\Roles;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleMutationService
{
    /**
     * Tao role moi va gan permission.
     */
    public function createRole(User $actor, array $attributes): Role
    {
        Gate::forUser($actor)->authorize('create', Role::class);

        $role = Role::query()->getConnection()->transaction(function () use ($attributes): Role {
            $role = Role::query()->create([
                'name' => trim((string) ($attributes['name'] ?? '')),
                'guard_name' => 'web',
            ]);

            $permissionNames = $this->normalizePermissionNames($attributes['permissions'] ?? []);
            $role->syncPermissions($permissionNames);

            return $role;
        });

        $this->forgetPermissionCache();

        return $role->loadCount(['permissions', 'users']);
    }

    /**
     * Cap nhat role va bo permission.
     */
    public function updateRole(User $actor, Role $role, array $attributes): Role
    {
        Gate::forUser($actor)->authorize('update', $role);

        $updatedRole = Role::query()->getConnection()->transaction(function () use ($role, $attributes): Role {
            $role->name = trim((string) ($attributes['name'] ?? $role->name));
            $role->guard_name = 'web';
            $role->save();

            $permissionNames = $this->normalizePermissionNames($attributes['permissions'] ?? []);
            $role->syncPermissions($permissionNames);

            return $role;
        });

        $this->forgetPermissionCache();

        return $updatedRole->load(['permissions'])->loadCount(['permissions', 'users']);
    }

    /**
     * Xoa role theo policy.
     */
    public function deleteRole(User $actor, Role $role): void
    {
        Gate::forUser($actor)->authorize('delete', $role);

        if ($role->name === 'super_admin') {
            throw ValidationException::withMessages([
                'role' => 'Khong the xoa role super_admin.',
            ]);
        }

        Role::query()->getConnection()->transaction(function () use ($role): void {
            $role->syncPermissions([]);
            $role->delete();
        });

        $this->forgetPermissionCache();
    }

    /**
     * Chuan hoa danh sach permission trong payload.
     *
     * @return Collection<int, string>
     */
    private function normalizePermissionNames(array $permissionNames): Collection
    {
        return collect($permissionNames)
            ->filter(fn (mixed $value): bool => is_string($value) && trim($value) !== '')
            ->map(fn (string $permissionName): string => trim($permissionName))
            ->unique()
            ->values();
    }

    /**
     * Xoa cache permission/role cua Spatie.
     */
    private function forgetPermissionCache(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
