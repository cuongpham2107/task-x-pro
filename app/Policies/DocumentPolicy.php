<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class DocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('document.view');
    }

    public function view(User $user, Document $document): bool
    {
        if (! $user->can('document.view')) {
            return false;
        }

        if ($user->hasAnyRole(['ceo', 'leader'])) {
            return true;
        }

        if ($document->uploader_id === $user->id) {
            return true;
        }

        if ($document->task_id !== null) {
            return $document->task()
                ->where(function (Builder $query) use ($user): void {
                    $query
                        ->where('tasks.pic_id', $user->id)
                        ->orWhere('tasks.created_by', $user->id)
                        ->orWhereHas('coPics', function (Builder $coPicQuery) use ($user): void {
                            $coPicQuery->where('users.id', $user->id);
                        });
                })
                ->exists();
        }

        return $document->project()
            ->where(function (Builder $query) use ($user): void {
                $query
                    ->where('projects.created_by', $user->id)
                    ->orWhereHas('projectLeaders', function (Builder $leaderQuery) use ($user): void {
                        $leaderQuery->where('user_id', $user->id);
                    });
            })
            ->exists();
    }

    public function create(User $user): bool
    {
        return $user->can('document.upload');
    }

    public function update(User $user, Document $document): bool
    {
        if ($user->can('document.manage') && $user->hasAnyRole(['ceo', 'leader'])) {
            return true;
        }

        return $user->can('document.upload') && $document->uploader_id === $user->id;
    }

    public function delete(User $user, Document $document): bool
    {
        if (! $user->can('document.manage')) {
            return false;
        }

        return $user->hasAnyRole(['ceo', 'leader']) || $document->uploader_id === $user->id;
    }

    public function restore(User $user, Document $document): bool
    {
        return $this->delete($user, $document);
    }

    public function forceDelete(User $user, Document $document): bool
    {
        return $this->delete($user, $document);
    }

    public function uploadVersion(User $user, Document $document): bool
    {
        return $this->update($user, $document);
    }
}
