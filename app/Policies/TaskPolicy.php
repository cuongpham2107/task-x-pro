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

        if ($user->hasAnyRole(['super_admin', 'ceo', 'leader'])) {
            return true;
        }

        if ($task->created_by === $user->id) {
            return true;
        }

        return $this->isExecutionParticipant($user, $task);
    }

    public function create(User $user): bool
    {
        if ($user->hasRole('ceo')) {
            return false;
        }

        return $user->can('task.create');
    }

    public function update(User $user, Task $task): bool
    {
        if (! $user->can('task.update')) {
            return false;
        }

        if ($user->hasRole('super_admin')) {
            return true;
        }

        if ($user->hasRole('ceo')) {
            return false;
        }

        if ($this->isResponsibleProjectLeader($user, $task)) {
            return true;
        }

        return $this->isExecutionParticipant($user, $task);
    }

    public function delete(User $user, Task $task): bool
    {
        if (! $user->can('task.delete')) {
            return false;
        }

        if ($user->hasRole('super_admin')) {
            return true;
        }

        if (! $user->hasRole('leader')) {
            return false;
        }

        return $this->isResponsibleProjectLeader($user, $task);
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

        if ($user->hasRole('super_admin')) {
            return true;
        }

        if (! $user->hasRole('leader')) {
            return false;
        }

        return $this->isResponsibleProjectLeader($user, $task);
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
            return $this->canLeaderApproveTask($user, $task);
        }

        if ($workflowType === \App\Enums\TaskWorkflowType::Double->value) {
            if ($user->hasRole('ceo')) {
                return true;
            }

            return $this->canLeaderApproveTask($user, $task);
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

        if ($user->hasAnyRole(['super_admin', 'ceo'])) {
            return true;
        }

        if ($this->isResponsibleProjectLeader($user, $task)) {
            return true;
        }

        return $this->isExecutionParticipant($user, $task);
    }

    private function isExecutionParticipant(User $user, Task $task): bool
    {
        if ((int) $task->pic_id === (int) $user->id) {
            return true;
        }

        return $task->coPicAssignments()->where('user_id', $user->id)->exists();
    }

    private function isResponsibleProjectLeader(User $user, Task $task): bool
    {
        if (! $task->phase || ! $task->phase->project) {
            return false;
        }

        return $task->phase->project->projectLeaders()->where('user_id', $user->id)->exists();
    }

    private function canLeaderApproveTask(User $user, Task $task): bool
    {
        if (! $user->hasRole('leader')) {
            return false;
        }

        if ((int) $task->pic_id === (int) $user->id) {
            return true;
        }

        return $this->isResponsibleProjectLeader($user, $task);
    }
}
