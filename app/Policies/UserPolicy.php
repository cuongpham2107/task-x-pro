<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('user.view');
    }

    public function view(User $user, User $targetUser): bool
    {
        return $this->viewAny($user) || $user->id === $targetUser->id;
    }

    public function create(User $user): bool
    {
        return $user->can('user.create');
    }

    public function update(User $user, User $targetUser): bool
    {
        return $user->can('user.update') || $user->id === $targetUser->id;
    }

    public function delete(User $user, User $targetUser): bool
    {
        return $user->can('user.delete');
    }

    public function restore(User $user, User $targetUser): bool
    {
        return $this->delete($user, $targetUser);
    }

    public function forceDelete(User $user, User $targetUser): bool
    {
        return $this->delete($user, $targetUser);
    }
}
