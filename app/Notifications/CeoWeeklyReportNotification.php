<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use NotificationChannels\Telegram\TelegramChannel;
use NotificationChannels\Telegram\TelegramMessage;

class CeoWeeklyReportNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<int, array{name: string, date: string}>  $completedProjects
     * @param  array<int, array{name: string, progress: int, deadline: string}>  $inProgressProjects
     * @param  array<int, array{name: string, progress: int, daysLeft: int}>  $atRiskProjects
     * @param  array<int, array{name: string, progress: int, overdueDays: int}>  $overdueProjects
     */
    public function __construct(
        public Carbon $startDate,
        public Carbon $endDate,
        public int $totalProjects,
        public array $completedProjects,
        public array $inProgressProjects,
        public array $atRiskProjects,
        public array $overdueProjects,
    ) {}

    public function via(object $notifiable): array
    {
        return [TelegramChannel::class];
    }

    public function toTelegram(object $notifiable): TelegramMessage
    {
        $periodLabel = $this->startDate->format('d/m/Y').' - '.$this->endDate->format('d/m/Y');

        $completedCount = count($this->completedProjects);
        $inProgressCount = count($this->inProgressProjects);
        $atRiskCount = count($this->atRiskProjects);
        $overdueCount = count($this->overdueProjects);
        $activeTotal = $this->totalProjects;

        $content = "🏢 BÁO CÁO CUỐI TUẦN (từ {$periodLabel})\n";
        $content .= "📊 TỔNG QUAN\n";
        $content .= "  • Tổng dự án đang theo dõi: {$activeTotal}\n";
        $content .= "  • ✅ Hoàn thành trong tuần: {$completedCount}\n";
        $content .= "  • 🔄 Đang tiến độ: {$inProgressCount}\n";
        $content .= "  • 🟠 Chậm tiến độ: {$atRiskCount}\n";
        $content .= "  • 🔴 Trễ hạn: {$overdueCount}\n";
        $content .= "─────────────────\n";

        if ($completedCount > 0) {
            $content .= "✅ DỰ ÁN HOÀN THÀNH TRONG TUẦN ({$completedCount})\n";
            foreach ($this->completedProjects as $p) {
                $content .= "  • \"{$p['name']}\" — hoàn thành ngày {$p['date']}\n";
            }
        }

        if ($inProgressCount > 0) {
            $content .= "🔄 DỰ ÁN ĐANG TIẾN ĐỘ ({$inProgressCount})\n";
            foreach ($this->inProgressProjects as $p) {
                $content .= "  • \"{$p['name']}\" — đạt {$p['progress']}% | Deadline {$p['deadline']}\n";
            }
        }

        if ($atRiskCount > 0) {
            $content .= "🟠 DỰ ÁN CHẬM TIẾN ĐỘ ({$atRiskCount})\n";
            foreach ($this->atRiskProjects as $p) {
                $content .= "  • \"{$p['name']}\" — đạt {$p['progress']}% | còn {$p['daysLeft']} ngày\n";
            }
        }

        if ($overdueCount > 0) {
            $content .= "🔴 DỰ ÁN TRỄ HẠN ({$overdueCount})\n";
            foreach ($this->overdueProjects as $p) {
                $content .= "  • \"{$p['name']}\" — đạt {$p['progress']}% | trễ {$p['overdueDays']} ngày\n";
            }
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
