<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\User;

class DepartmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('project.view');
    }

    public function view(User $user, Department $department): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('project.create');
    }

    public function update(User $user, Department $department): bool
    {
        return $user->can('project.update');
    }

    public function delete(User $user, Department $department): bool
    {
        return $user->can('project.delete');
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
