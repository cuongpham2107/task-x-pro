<?php

namespace App\Policies;

use App\Enums\ProjectStatus;
use App\Models\Phase;
use App\Models\Project;
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

    public function create(User $user, ?Project $project = null): bool
    {
        if (in_array($project?->status, [ProjectStatus::Completed, ProjectStatus::Cancelled, ProjectStatus::Paused, ProjectStatus::Overdue], true)) {
            return false;
        }

        if ($user->hasRole('ceo')) {
            return false;
        }

        if ($project !== null) {
            $isProjectCreator = (int) $project->created_by === (int) $user->id;
            $isProjectLeader = $project->projectLeaders()->where('user_id', $user->id)->exists();

            if ($isProjectCreator || $isProjectLeader) {
                return true;
            }
        }

        return $user->can('phase.create');
    }

    public function update(User $user, Phase $phase): bool
    {
        if (in_array($phase->project?->status, [ProjectStatus::Completed, ProjectStatus::Cancelled, ProjectStatus::Paused, ProjectStatus::Overdue], true)) {
            return false;
        }

        if ($user->hasRole('super_admin')) {
            return true;
        }

        $isCreator = (int) $phase->created_by === (int) $user->id;
        $isProjectCreator = $phase->project && (int) $phase->project->created_by === (int) $user->id;

        if ($isCreator || $isProjectCreator) {
            return true;
        }

        if ($user->hasRole('leader') && $phase->project?->projectLeaders()->where('user_id', $user->id)->exists()) {
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
