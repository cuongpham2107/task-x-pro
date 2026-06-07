<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use NotificationChannels\Telegram\TelegramChannel;
use NotificationChannels\Telegram\TelegramMessage;

class LeaderWeeklyReportNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<int, array{name: string, progress: int, deadline: string, status: string}>  $projects
     */
    public function __construct(
        public User $leader,
        public Carbon $startDate,
        public Carbon $endDate,
        public array $projects,
    ) {}

    public function via(object $notifiable): array
    {
        return [TelegramChannel::class];
    }

    public function toTelegram(object $notifiable): TelegramMessage
    {
        $periodLabel = $this->startDate->format('d/m/Y').' - '.$this->endDate->format('d/m/Y');

        $total = count($this->projects);
        $onTrack = count(array_filter($this->projects, fn ($p) => $p['status'] === 'Đúng tiến độ'));
        $atRisk = count(array_filter($this->projects, fn ($p) => $p['status'] === 'Rủi ro'));
        $overdue = count(array_filter($this->projects, fn ($p) => $p['status'] === 'Trễ hạn'));

        $content = "📈 BÁO CÁO CUỐI TUẦN (từ {$periodLabel})\n";
        $content .= "👤 Leader: {$this->leader->name}\n";
        $content .= "📊 Tổng quan: {$total} dự án đang chủ trì\n";
        $content .= "✅ {$onTrack} đúng tiến độ | 🟠 {$atRisk} rủi ro | 🔴 {$overdue} trễ hạn\n";
        $content .= "─────────────────\n";

        foreach ($this->projects as $i => $project) {
            $content .= '📁 '.($i + 1).". Dự án \"{$project['name']}\" — Tiến độ tổng thể: {$project['progress']}%\n";
            $content .= "🗓️ Deadline: {$project['deadline']} | Trạng thái: {$project['status']}\n";
        }

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
