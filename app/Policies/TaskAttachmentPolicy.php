<?php

namespace App\Policies;

use App\Models\TaskAttachment;
use App\Models\User;

class TaskAttachmentPolicy
{
    /**
     * Determine whether the user can delete the given task attachment.
     */
    public function delete(User $user, TaskAttachment $attachment): bool
    {
        // Basic permission check - PICs have task.update, Leaders have task.delete
        if (! $user->can('task.update') && ! $user->can('task.delete')) {
            return false;
        }

        // Super admin can always delete
        if ($user->hasRole('super_admin')) {
            return true;
        }

        // CEO or Leader can delete attachments
        if ($user->hasAnyRole(['ceo', 'leader'])) {
            return true;
        }

        // Uploader can delete their own attachment
        if ((int) $attachment->uploader_id === (int) $user->id) {
            return true;
        }

        // Task owner (creator) or PIC can delete
        $task = $attachment->task;
        if ($task) {
            if ((int) $task->created_by === (int) $user->id) {
                return true;
            }

            if ((int) $task->pic_id === (int) $user->id) {
                return true;
            }

            // Project-level leaders assigned to the task's project
            if ($task->phase && $task->phase->project) {
                return $task->phase->project->projectLeaders()->where('user_id', $user->id)->exists();
            }
        }

        return false;
    }
}
