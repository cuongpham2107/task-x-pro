<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use NotificationChannels\Telegram\TelegramChannel;
use NotificationChannels\Telegram\TelegramMessage;

class PicOverloadWarningNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Task $task,
        public int $nearbyTaskCount,
        public Carbon $deadline,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [TelegramChannel::class];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toTelegram(object $notifiable): TelegramMessage
    {
        $this->task->loadMissing(['phase.project']);

        $taskName = trim((string) $this->task->name) !== '' ? $this->task->name : "Task #{$this->task->id}";
        $projectName = $this->task->phase?->project?->name;
        $phaseName = $this->task->phase?->name;
        $deadlineText = $this->deadline->format('d/m/Y H:i');

        $contextDetails = ["Task: \"{$taskName}\""];
        if ($projectName !== null && trim((string) $projectName) !== '') {
            $contextDetails[] = "Du an: {$projectName}";
        }
        if ($phaseName !== null && trim((string) $phaseName) !== '') {
            $contextDetails[] = "Giai doan: {$phaseName}";
        }

        $content = "Canh bao qua tai: Ban co {$this->nearbyTaskCount} task co deadline trong vung +/-1 ngay quanh {$deadlineText}.";
        $content .= "\n".implode(' | ', $contextDetails);

        $message = TelegramMessage::create()
            ->to((string) $notifiable->telegram_id)
            ->content($content);

        $taskUrl = $this->resolveTaskUrl();
        if ($taskUrl !== null) {
            $message->button('Xem cong viec', $taskUrl);
        }

        return $message;
    }

    /**
     * Get the array representation of the notification.
     *
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
