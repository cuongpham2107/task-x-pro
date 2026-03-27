<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('project.view');
    }

    public function view(User $user, Project $project): bool
    {
        if (! $user->can('project.view')) {
            return false;
        }

        if ($user->hasAnyRole(['ceo', 'leader'])) {
            return true;
        }

        if ($project->created_by === $user->id) {
            return true;
        }

        if ($project->projectLeaders()->where('user_id', $user->id)->exists()) {
            return true;
        }

        return $project->phases()
            ->whereHas('tasks', function (Builder $query) use ($user): void {
                $query->where(function (Builder $taskQuery) use ($user): void {
                    $taskQuery
                        ->where('pic_id', $user->id)
                        ->orWhere('created_by', $user->id)
                        ->orWhereHas('coPics', function (Builder $coPicQuery) use ($user): void {
                            $coPicQuery->where('users.id', $user->id);
                        });
                });
            })
            ->exists();
    }

    public function create(User $user): bool
    {
        if ($user->hasRole('ceo')) {
            return false;
        }

        return $user->can('project.create');
    }

    public function update(User $user, Project $project): bool
    {
        if (! $user->can('project.update')) {
            return false;
        }

        if ($user->hasAnyRole(['super_admin', 'ceo'])) {
            return true;
        }

        return $user->hasRole('leader');
    }

    public function delete(User $user, Project $project): bool
    {
        if (! $user->can('project.delete')) {
            return false;
        }

        if ($user->hasAnyRole(['super_admin', 'ceo'])) {
            return true;
        }

        if (! $user->hasRole('leader')) {
            return false;
        }

        return (int) $project->created_by === (int) $user->id;
    }

    public function restore(User $user, Project $project): bool
    {
        return $this->delete($user, $project);
    }

    public function forceDelete(User $user, Project $project): bool
    {
        return $this->delete($user, $project);
    }

    public function assignLeader(User $user, Project $project): bool
    {
        return $this->update($user, $project);
    }

    public function syncPhases(User $user, Project $project): bool
    {
        return $this->update($user, $project);
    }
}
