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
        if ($user->hasRole('ceo')) {
            return false;
        }

        return $user->can('phase.create');
    }

    public function update(User $user, Phase $phase): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        $isCreator = (int) $phase->created_by === (int) $user->id;

        if ($isCreator) {
            return true;
        }

        if ($user->hasRole('leader')) {
            return true;
        }

        return false;
    }

    public function delete(User $user, Phase $phase): bool
    {
        return $this->update($user, $phase);
    }

    public function restore(User $user, Phase $phase): bool
    {
        return $this->update($user, $phase);
    }

    public function forceDelete(User $user, Phase $phase): bool
    {
        return $this->update($user, $phase);
    }

    public function reorder(User $user, Phase $phase): bool
    {
        return $this->update($user, $phase);
    }
}
