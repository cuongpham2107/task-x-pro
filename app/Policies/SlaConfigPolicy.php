<?php

namespace App\Policies;

use App\Models\SlaConfig;
use App\Models\User;

class SlaConfigPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('sla.view');
    }

    public function view(User $user, SlaConfig $slaConfig): bool
    {
        return $user->can('sla.view');
    }

    public function create(User $user): bool
    {
        return $user->can('sla.manage');
    }

    public function update(User $user, SlaConfig $slaConfig): bool
    {
        return $user->can('sla.manage');
    }

    public function delete(User $user, SlaConfig $slaConfig): bool
    {
        return $user->can('sla.manage');
    }

    public function restore(User $user, SlaConfig $slaConfig): bool
    {
        return $user->can('sla.manage');
    }

    public function forceDelete(User $user, SlaConfig $slaConfig): bool
    {
        return $user->can('sla.manage');
    }
}
