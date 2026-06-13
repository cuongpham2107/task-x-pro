<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramChannel;
use NotificationChannels\Telegram\TelegramMessage;

class TaskApprovedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Task $task,
        public User $actor,
        public ?int $rating = null,
        public ?string $reviewNote = null,
    ) {}

    public function via(object $notifiable): array
    {
        return [TelegramChannel::class];
    }

    public function toTelegram(object $notifiable): TelegramMessage
    {
        $this->task->loadMissing(['phase.project']);

        $taskName = trim((string) $this->task->name) !== '' ? $this->task->name : "Task #{$this->task->id}";
        $projectName = $this->task->phase?->project?->name;
        $phaseName = $this->task->phase?->name;

        $parts = ["✅ Task \"{$taskName}\""];
        if ($phaseName !== null && trim((string) $phaseName) !== '') {
            $parts[] = "thuộc Phase \"{$phaseName}\"";
        }
        if ($projectName !== null && trim((string) $projectName) !== '') {
            $parts[] = "của Dự án \"{$projectName}\"";
        }
        $parts[] = 'đã được duyệt Đạt.';

        $content = implode(' ', $parts);

        if ($this->rating !== null) {
            $content .= "\n📝 Đánh giá: {$this->rating}/5";
        }
        if ($this->reviewNote !== null && trim($this->reviewNote) !== '') {
            $content .= "\n💬 Nhận xét: ".trim($this->reviewNote);
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

    public function toArray(object $notifiable): array
    {
        return [];
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
