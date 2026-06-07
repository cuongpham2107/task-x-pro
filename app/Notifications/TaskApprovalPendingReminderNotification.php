<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramChannel;
use NotificationChannels\Telegram\TelegramMessage;

class TaskApprovalPendingReminderNotification extends Notification
{
    use Queueable;

    public function __construct(
        public int $count,
    ) {}

    public function via(object $notifiable): array
    {
        return [TelegramChannel::class];
    }

    public function toTelegram(object $notifiable): TelegramMessage
    {
        $count = max(0, $this->count);
        $content = "⚠️ Cảnh báo — còn {$count} task chưa được phê duyệt.\n";
        $content .= '👉 Vui lòng xử lý để PIC không bị chậm tiến độ.';

        $message = TelegramMessage::create()
            ->to((string) $notifiable->telegram_id)
            ->content($content);

        $dashboardUrl = $this->resolveDashboardUrl();
        if ($dashboardUrl !== null) {
            $message->button('Mở Dashboard', $dashboardUrl);
        }

        return $message;
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }

    private function resolveDashboardUrl(): ?string
    {
        $appUrl = (string) config('app.url');
        if ($appUrl !== '' && str_contains($appUrl, 'localhost')) {
            return null;
        }

        return route('dashboard.index');
    }
}
