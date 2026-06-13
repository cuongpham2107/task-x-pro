<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramChannel;
use NotificationChannels\Telegram\TelegramMessage;

class PicDailySummaryNotification extends Notification
{
    use Queueable;

    public function __construct(
        public int $todayCount,
        public int $overdueCount,
        public int $dueTodayNotCompleted,
    ) {}

    public function via(object $notifiable): array
    {
        return [TelegramChannel::class];
    }

    public function toTelegram(object $notifiable): TelegramMessage
    {
        $todayCount = max(0, $this->todayCount);
        $overdueCount = max(0, $this->overdueCount);
        $dueTodayNotCompleted = max(0, $this->dueTodayNotCompleted);

        $content = "☀️ Chào buổi sáng! Tổng kết công việc hôm nay:\n";
        $content .= "📋 Có {$dueTodayNotCompleted} task cần hoàn thành hôm nay.\n";
        $content .= "🔴 Số task quá hạn: {$overdueCount}\n";
        $content .= "📅 Số task đến hạn: {$todayCount}";

        $message = TelegramMessage::create()
            ->to((string) $notifiable->telegram_id)
            ->content($content);

        $tasksUrl = $this->resolveTasksUrl();
        if ($tasksUrl !== null) {
            $message->button('Xem danh sách công việc', $tasksUrl);
        }

        return $message;
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }

    private function resolveTasksUrl(): ?string
    {
        $appUrl = (string) config('app.url');
        if ($appUrl !== '' && str_contains($appUrl, 'localhost')) {
            return null;
        }

        return route('tasks.index');
    }
}
