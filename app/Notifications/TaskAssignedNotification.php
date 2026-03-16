<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramChannel;
use NotificationChannels\Telegram\TelegramMessage;

class TaskAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Task $task,
        public User $assigner,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        if (trim((string) ($notifiable->telegram_id ?? '')) === '') {
            return [];
        }

        return [TelegramChannel::class];
    }

    public function toTelegram(object $notifiable): TelegramMessage
    {
        $this->task->loadMissing(['phase.project']);

        $taskName = trim((string) $this->task->name) !== '' ? $this->task->name : "Công việc #{$this->task->id}";
        $assignerName = trim((string) $this->assigner->name) !== '' ? $this->assigner->name : 'Người giao việc';
        $deadlineText = $this->task->deadline?->format('d/m/Y H:i') ?? 'N/A';
        $projectName = $this->task->phase?->project?->name;
        $phaseName = $this->task->phase?->name;

        $contextDetails = ["Deadline: {$deadlineText}"];
        if ($projectName !== null && trim((string) $projectName) !== '') {
            $contextDetails[] = "Dự án: {$projectName}";
        }
        if ($phaseName !== null && trim((string) $phaseName) !== '') {
            $contextDetails[] = "Giai đoạn: {$phaseName}";
        }

        $content = "Bạn được giao công việc \"{$taskName}\" bởi {$assignerName}.";
        if ($contextDetails !== []) {
            $content .= "\n".implode(' | ', $contextDetails);
        }

        $message = TelegramMessage::create()
            ->to((string) $notifiable->telegram_id)
            ->content($content);

        $taskUrl = $this->resolveTaskUrl();
        if ($taskUrl !== null) {
            $message->button('Xem công việc', $taskUrl);
        }

        return $message;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }

    private function resolveTaskUrl(): ?string
    {
        $appUrl = (string) config('app.url');
        if ($appUrl !== '' && str_contains($appUrl, 'localhost')) {
            return null;
        }

        $phase = $this->task->phase;

        if ($phase !== null && $phase->project_id !== null) {
            return route('projects.phases.tasks.index', [
                'project' => $phase->project_id,
                'phase' => $phase->id,
            ]);
        }

        return route('tasks.index');
    }
}
