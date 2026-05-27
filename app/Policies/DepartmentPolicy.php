<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\User;

class DepartmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('department.view');
    }

    public function view(User $user, Department $department): bool
    {
        if ($user->can('department.view')) {
            return true;
        }

        // Allow leaders to view the department they head
        if ($user->hasRole('leader') && $department->head_user_id !== null && $department->head_user_id === $user->id) {
            return true;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->can('department.create');
    }

    public function update(User $user, Department $department): bool
    {
        return $user->can('department.update');
    }

    public function delete(User $user, Department $department): bool
    {
        return $user->can('department.delete');
    }

    public function restore(User $user, Department $department): bool
    {
        return $this->delete($user, $department);
    }

    public function forceDelete(User $user, Department $department): bool
    {
        return $this->delete($user, $department);
    }
}
