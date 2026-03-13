<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use NotificationChannels\Telegram\TelegramChannel;
use NotificationChannels\Telegram\TelegramMessage;

class WeeklySummaryNotification extends Notification
{
    use Queueable;

    /**
     * @param  array{
     *     completed: int,
     *     late: int,
     *     waiting_approval: int,
     *     due_soon: int
     * }  $summary
     */
    public function __construct(
        public array $summary,
        public Carbon $periodStart,
        public Carbon $periodEnd,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [TelegramChannel::class];
    }

    public function toTelegram(object $notifiable): TelegramMessage
    {
        $periodLabel = $this->periodStart->format('d/m/Y').' - '.$this->periodEnd->format('d/m/Y');

        $content = "Báo cáo cuối tuần ({$periodLabel})";
        $content .= "\nHoàn thành: {$this->summary['completed']}";
        $content .= "\nTrễ hạn: {$this->summary['late']}";
        $content .= "\nChờ duyệt: {$this->summary['waiting_approval']}";
        $content .= "\nSắp đến hạn (<=3 ngày): {$this->summary['due_soon']}";

        $message = TelegramMessage::create()
            ->to((string) $notifiable->telegram_id)
            ->content($content);

        $dashboardUrl = $this->resolveDashboardUrl();
        if ($dashboardUrl !== null) {
            $message->button('Mở Dashboard', $dashboardUrl);
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

    private function resolveDashboardUrl(): ?string
    {
        $appUrl = (string) config('app.url');
        if ($appUrl !== '' && str_contains($appUrl, 'localhost')) {
            return null;
        }

        return route('dashboard.index');
    }
}
