<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramChannel;
use NotificationChannels\Telegram\TelegramMessage;

class TaskDeadlineReminderNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Task $task,
        public int $daysLeft,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = [];

        if (trim((string) ($notifiable->telegram_id ?? '')) !== '') {
            $channels[] = TelegramChannel::class;
        }

        if (trim((string) ($notifiable->email ?? '')) !== '') {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toTelegram(object $notifiable): TelegramMessage
    {
        $this->task->loadMissing(['phase.project']);

        $taskName = trim((string) $this->task->name) !== '' ? $this->task->name : "Công việc #{$this->task->id}";
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

        $daysLeft = max(0, $this->daysLeft);
        $content = "Công việc \"{$taskName}\" sắp đến hạn. Còn {$daysLeft} ngày.";
        $content .= "\n".implode(' | ', $contextDetails);

        $message = TelegramMessage::create()
            ->to((string) $notifiable->telegram_id)
            ->content($content);

        $taskUrl = $this->resolveTaskUrl();
        if ($taskUrl !== null) {
            $message->button('Xem công việc', $taskUrl);
        }

        return $message;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->task->loadMissing(['phase.project']);

        $taskName = trim((string) $this->task->name) !== '' ? $this->task->name : "Công việc #{$this->task->id}";
        $deadlineText = $this->task->deadline?->format('d/m/Y H:i') ?? 'N/A';
        $projectName = $this->task->phase?->project?->name;
        $phaseName = $this->task->phase?->name;

        $daysLeft = max(0, $this->daysLeft);

        $message = (new MailMessage)
            ->subject("Nhắc deadline: {$taskName}")
            ->line("Công việc \"{$taskName}\" sắp đến hạn. Còn {$daysLeft} ngày.")
            ->line("Deadline: {$deadlineText}");

        if ($projectName !== null && trim((string) $projectName) !== '') {
            $message->line("Dự án: {$projectName}");
        }

        if ($phaseName !== null && trim((string) $phaseName) !== '') {
            $message->line("Giai đoạn: {$phaseName}");
        }

        $taskUrl = $this->resolveTaskUrl();
        if ($taskUrl !== null) {
            $message->action('Xem công việc', $taskUrl);
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
