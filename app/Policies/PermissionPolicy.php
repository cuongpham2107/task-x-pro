<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Permission;

class PermissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('super_admin');
    }

    public function view(User $user, Permission $permission): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Permission $permission): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Permission $permission): bool
    {
        return $this->viewAny($user);
    }
}
