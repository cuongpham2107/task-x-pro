<?php

namespace App\Notifications;

use App\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramChannel;
use NotificationChannels\Telegram\TelegramMessage;

class ProjectOverdueNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Project $project,
    ) {}

    public function via(object $notifiable): array
    {
        return [TelegramChannel::class];
    }

    public function toTelegram(object $notifiable): TelegramMessage
    {
        $projectName = $this->project->name;
        $endDate = $this->project->end_date?->format('d/m/Y') ?? 'N/A';
        $content = "🚨 Dự án quá hạn: {$projectName}\n\n"
            ."Dự án đã vượt quá hạn chót ({$endDate}) nhưng chưa hoàn thành.\n"
            .'Vui lòng kiểm tra và cập nhật tiến độ ngay.';

        $message = TelegramMessage::create()
            ->to((string) $notifiable->telegram_id)
            ->content($content);

        $projectsUrl = $this->resolveProjectsUrl();
        if ($projectsUrl !== null) {
            $message->button('Xem danh sách dự án', $projectsUrl);
        }

        return $message;
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }

    private function resolveProjectsUrl(): ?string
    {
        $appUrl = (string) config('app.url');
        if ($appUrl !== '' && str_contains($appUrl, 'localhost')) {
            return null;
        }

        return route('projects.index');
    }
}
