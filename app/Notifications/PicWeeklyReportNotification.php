<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use NotificationChannels\Telegram\TelegramChannel;
use NotificationChannels\Telegram\TelegramMessage;

class PicWeeklyReportNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Carbon $startDate,
        public Carbon $endDate,
        public int $total,
        public int $approved,
        public int $rejected,
        public int $pending,
    ) {}

    public function via(object $notifiable): array
    {
        return [TelegramChannel::class];
    }

    public function toTelegram(object $notifiable): TelegramMessage
    {
        $periodLabel = $this->startDate->format('d/m/Y').' - '.$this->endDate->format('d/m/Y');

        $content = "📊 BÁO CÁO CUỐI TUẦN (từ {$periodLabel})\n";
        $content .= "✅ Tổng số task ĐÃ LÀM trong tuần: {$this->total}\n";
        $content .= "🟢 Số task ĐẠT: {$this->approved}\n";
        $content .= "🔴 Số task KHÔNG ĐẠT: {$this->rejected}\n";
        $content .= "🟡 Số task CHƯA được phê duyệt: {$this->pending}";

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
