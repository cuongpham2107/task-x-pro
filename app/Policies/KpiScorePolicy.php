<?php

namespace App\Policies;

use App\Models\KpiScore;
use App\Models\User;

class KpiScorePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('kpi.view');
    }

    public function view(User $user, KpiScore $kpiScore): bool
    {
        if ($user->can('kpi.manage')) {
            return true;
        }

        return $user->can('kpi.view') && $kpiScore->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can('kpi.manage');
    }

    public function update(User $user, KpiScore $kpiScore): bool
    {
        return $user->can('kpi.manage');
    }

    public function delete(User $user, KpiScore $kpiScore): bool
    {
        return $user->can('kpi.manage');
    }

    public function restore(User $user, KpiScore $kpiScore): bool
    {
        return $user->can('kpi.manage');
    }

    public function forceDelete(User $user, KpiScore $kpiScore): bool
    {
        return $user->can('kpi.manage');
    }
}
