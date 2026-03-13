<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramChannel;
use NotificationChannels\Telegram\TelegramMessage;

class PicOverdueTasksNotification extends Notification
{
    use Queueable;

    public function __construct(
        public User $pic,
        public int $overdueCount,
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
        $overdueCount = max(0, $this->overdueCount);
        $content = "Cảnh báo: Bạn đang có {$overdueCount} công việc đã trễ hạn. Vui lòng kiểm tra và cập nhập tiến độ.";

        $message = TelegramMessage::create()
            ->to((string) $notifiable->telegram_id)
            ->content($content);

        $tasksUrl = $this->resolveTasksUrl();
        if ($tasksUrl !== null) {
            $message->button('Xem danh sách công việc', $tasksUrl);
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

    private function resolveTasksUrl(): ?string
    {
        $appUrl = (string) config('app.url');
        if ($appUrl !== '' && str_contains($appUrl, 'localhost')) {
            return null;
        }

        return route('tasks.index');
    }
}
