<?php

namespace App\Policies;

use App\Models\Phase;
use App\Models\User;

class PhasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('phase.view');
    }

    public function view(User $user, Phase $phase): bool
    {
        if (! $user->can('phase.view')) {
            return false;
        }

        return $user->can('view', $phase->project);
    }

    public function create(User $user): bool
    {
        return $user->can('phase.create');
    }

    public function update(User $user, Phase $phase): bool
    {
        return $user->can('phase.update');
    }

    public function delete(User $user, Phase $phase): bool
    {
        return $user->can('phase.update');
    }

    public function restore(User $user, Phase $phase): bool
    {
        return $user->can('phase.update');
    }

    public function forceDelete(User $user, Phase $phase): bool
    {
        return $user->can('phase.update');
    }

    public function reorder(User $user, Phase $phase): bool
    {
        return $user->can('phase.update');
    }
}
