<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('task.view');
    }

    public function view(User $user, Task $task): bool
    {
        if (! $user->can('task.view')) {
            return false;
        }

        if ($user->hasAnyRole(['ceo', 'leader'])) {
            return true;
        }

        return $this->isTaskParticipant($user, $task);
    }

    public function create(User $user): bool
    {
        return $user->can('task.create');
    }

    public function update(User $user, Task $task): bool
    {
        if (! $user->can('task.update')) {
            return false;
        }

        if ($user->hasAnyRole(['ceo', 'leader'])) {
            return true;
        }

        return $this->isTaskParticipant($user, $task);
    }

    public function delete(User $user, Task $task): bool
    {
        if (! $user->can('task.delete')) {
            return false;
        }

        return $user->hasRole('ceo') || $task->created_by === $user->id;
    }

    public function restore(User $user, Task $task): bool
    {
        return $this->delete($user, $task);
    }

    public function forceDelete(User $user, Task $task): bool
    {
        return $this->delete($user, $task);
    }

    public function assign(User $user, Task $task): bool
    {
        if (! $user->can('task.assign')) {
            return false;
        }

        if ($user->hasRole('ceo')) {
            return true;
        }

        return $task->created_by === $user->id
            || $task->phase->project->projectLeaders()->where('user_id', $user->id)->exists();
    }

    public function approve(User $user, Task $task): bool
    {
        if (! $user->can('task.approve')) {
            return false;
        }

        if ($user->hasRole('super_admin')) {
            return true;
        }

        $workflowType = $task->workflow_type instanceof \BackedEnum
            ? (string) $task->workflow_type->value
            : (string) $task->workflow_type;

        if ($workflowType === \App\Enums\TaskWorkflowType::Single->value) {
            return $user->hasRole('leader');
        }

        if ($workflowType === \App\Enums\TaskWorkflowType::Double->value) {
            return $user->hasAnyRole(['leader', 'ceo']);
        }

        return false;
    }

    public function start(User $user, Task $task): bool
    {
        if (! $this->update($user, $task)) {
            return false;
        }

        if (! $user->hasRole('super_admin') && (int) $task->pic_id !== (int) $user->id) {
            return false;
        }

        if ($task->dependency_task_id === null) {
            return true;
        }

        $dependencyStatus = $task->dependencyTask?->status instanceof \BackedEnum
            ? (string) $task->dependencyTask->status->value
            : (string) $task->dependencyTask?->status;

        return $dependencyStatus === \App\Enums\TaskStatus::Completed->value;
    }

    public function complete(User $user, Task $task): bool
    {
        return $this->update($user, $task);
    }

    public function deleteAttachment(User $user, Task $task): bool
    {
        return $this->update($user, $task);
    }

    public function comment(User $user, Task $task): bool
    {
        if (! $this->view($user, $task)) {
            return false;
        }

        if ($user->hasAnyRole(['ceo', 'leader'])) {
            return true;
        }

        if ((int) $task->pic_id === (int) $user->id) {
            return true;
        }

        return $task->coPicAssignments()->where('user_id', $user->id)->exists();
    }

    private function isTaskParticipant(User $user, Task $task): bool
    {
        if ($task->pic_id === $user->id || $task->created_by === $user->id) {
            return true;
        }

        return $task->coPicAssignments()->where('user_id', $user->id)->exists();
    }
}
