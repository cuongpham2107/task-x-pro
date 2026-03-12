<?php

namespace App\Policies;

use App\Models\SystemNotification;
use App\Models\User;

class SystemNotificationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('notification.view');
    }

    public function view(User $user, SystemNotification $systemNotification): bool
    {
        if ($user->can('notification.manage')) {
            return true;
        }

        return $user->can('notification.view') && $systemNotification->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can('notification.manage');
    }

    public function update(User $user, SystemNotification $systemNotification): bool
    {
        if ($user->can('notification.manage')) {
            return true;
        }

        return $user->can('notification.view') && $systemNotification->user_id === $user->id;
    }

    public function delete(User $user, SystemNotification $systemNotification): bool
    {
        return $user->can('notification.manage');
    }

    public function restore(User $user, SystemNotification $systemNotification): bool
    {
        return $user->can('notification.manage');
    }

    public function forceDelete(User $user, SystemNotification $systemNotification): bool
    {
        return $user->can('notification.manage');
    }

    public function retry(User $user, SystemNotification $systemNotification): bool
    {
        return $user->can('notification.manage');
    }

    public function markAsRead(User $user, SystemNotification $systemNotification): bool
    {
        return $this->update($user, $systemNotification);
    }
}
