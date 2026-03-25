<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramChannel;
use NotificationChannels\Telegram\TelegramMessage;

class TaskAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Task $task,
        public User $assigner,
        public bool $isCoAssignee = false,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['mail'];

        if (trim((string) ($notifiable->telegram_id ?? '')) !== '') {
            $channels[] = TelegramChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->task->loadMissing(['phase.project']);

        $taskName = trim((string) $this->task->name) !== '' ? $this->task->name : "Công việc #{$this->task->id}";
        $assignerName = trim((string) $this->assigner->name) !== '' ? $this->assigner->name : 'Người giao việc';
        $role = $this->isCoAssignee ? 'Co-PIC (hỗ trợ)' : 'PIC (phụ trách chính)';
        $deadlineText = $this->task->deadline?->format('d/m/Y H:i') ?? 'Chưa xác định';
        $projectName = $this->task->phase?->project?->name ?? '';
        $phaseName = $this->task->phase?->name ?? '';

        $mail = (new MailMessage)
            ->subject("Bạn được giao công việc: {$taskName}")
            ->greeting("Xin chào {$notifiable->name},")
            ->line("Bạn vừa được {$assignerName} giao công việc với vai trò **{$role}**.")
            ->line("**Công việc:** {$taskName}")
            ->line("**Dự án:** {$projectName} | **Giai đoạn:** {$phaseName}")
            ->line("**Hạn chót:** {$deadlineText}");

        $taskUrl = $this->resolveTaskUrl();
        if ($taskUrl !== null) {
            $mail->action('Xem công việc', $taskUrl);
        }

        return $mail->line('Vui lòng đăng nhập hệ thống để xem chi tiết và bắt đầu thực hiện.');
    }

    public function toTelegram(object $notifiable): TelegramMessage
    {
        $this->task->loadMissing(['phase.project']);

        $taskName = trim((string) $this->task->name) !== '' ? $this->task->name : "Công việc #{$this->task->id}";
        $assignerName = trim((string) $this->assigner->name) !== '' ? $this->assigner->name : 'Người giao việc';
        $deadlineText = $this->task->deadline?->format('d/m/Y H:i') ?? 'N/A';
        $projectName = $this->task->phase?->project?->name;
        $phaseName = $this->task->phase?->name;
        $role = $this->isCoAssignee ? 'Co-PIC (hỗ trợ)' : 'PIC (phụ trách chính)';

        $contextDetails = ["Deadline: {$deadlineText}", "Vai trò: {$role}"];
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
