<?php

namespace App\Services\Tasks;

use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Enums\SystemNotificationType;
use App\Enums\TaskStatus;
use App\Models\SystemNotification;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;

class TaskOverloadService
{
    /**
     * Kiem tra overload PIC theo BR-006 va tao thong bao canh bao.
     */
    public function warnIfPicOverloaded(User $actor, Task $task): ?string
    {
        $taskStatus = $task->status instanceof \BackedEnum
            ? (string) $task->status->value
            : (string) $task->status;

        if ($taskStatus === TaskStatus::Completed->value || $task->pic_id === null || $task->deadline === null) {
            return null;
        }

        $deadline = $task->deadline instanceof Carbon
            ? $task->deadline->copy()
            : Carbon::parse((string) $task->deadline);

        $nearbyTaskCount = $this->countNearbyDeadlineTasks((int) $task->pic_id, $deadline);
        if ($nearbyTaskCount <= 3) {
            return null;
        }

        $task->loadMissing('pic:id,name');
        $deadlineText = $deadline->format('d/m/Y H:i');
        $picName = $task->pic?->name ?? 'PIC';

        $message = "PIC {$picName} dang co {$nearbyTaskCount} task co deadline trong vung +/-1 ngay quanh {$deadlineText}.";
        $this->createOverloadNotifications($actor, $task, $message);

        return $message;
    }

    /**
     * Dem so task cua PIC co deadline nam trong khoang +/-1 ngay.
     */
    private function countNearbyDeadlineTasks(int $picId, Carbon $deadline, ?int $ignoreTaskId = null): int
    {
        $completedStatus = TaskStatus::Completed->value;

        $query = Task::query()
            ->where('pic_id', $picId)
            ->where('status', '!=', $completedStatus)
            ->whereBetween('deadline', [
                $deadline->copy()->subDay(),
                $deadline->copy()->addDay(),
            ]);

        if ($ignoreTaskId !== null) {
            $query->whereKeyNot($ignoreTaskId);
        }

        return $query->count();
    }

    /**
     * Tao thong bao he thong khi phat hien overload PIC.
     */
    private function createOverloadNotifications(User $actor, Task $task, string $message): void
    {
        $recipientIds = collect([
            $actor->id,
            $task->pic_id,
        ])
            ->filter(function (mixed $recipientId): bool {
                return $recipientId !== null;
            })
            ->map(function (mixed $recipientId): int {
                return (int) $recipientId;
            })
            ->unique()
            ->values();

        foreach ($recipientIds as $recipientId) {
            SystemNotification::query()->create([
                'user_id' => $recipientId,
                'type' => SystemNotificationType::PicOverloadWarning->value,
                'channel' => NotificationChannel::Both->value,
                'title' => 'Canh bao qua tai PIC',
                'body' => $message,
                'notifiable_type' => Task::class,
                'notifiable_id' => $task->id,
                'status' => NotificationStatus::Pending->value,
            ]);
        }
    }
}
