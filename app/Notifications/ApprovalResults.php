<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramChannel;
use NotificationChannels\Telegram\TelegramMessage;

class ApprovalResults extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Task $task,
        public User $leader,
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

        $leaderName = trim((string) $this->leader->name) !== '' ? $this->leader->name : 'Leader';
        $taskName = trim((string) $this->task->name) !== '' ? $this->task->name : "Task #{$this->task->id}";
        $projectName = $this->task->phase?->project?->name;
        $phaseName = $this->task->phase?->name;

        $contextDetails = [];
        if ($projectName !== null && trim((string) $projectName) !== '') {
            $contextDetails[] = "Dự án: {$projectName}";
        }
        if ($phaseName !== null && trim((string) $phaseName) !== '') {
            $contextDetails[] = "Giai đoạn: {$phaseName}";
        }

        $content = "Công việc \"{$taskName}\" đã được {$leaderName} phê duyệt và đang chờ CEO phê duyệt.";
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
