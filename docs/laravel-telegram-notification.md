# Gửi Tin Nhắn Telegram từ Laravel bằng Notification Channel

> Sử dụng package `laravel-notification-channels/telegram` — hoạt động giống SMTP, tích hợp hoàn toàn với hệ thống Notification của Laravel.

---

## Mục lục

1. [Cài đặt](#1-cài-đặt)
2. [Cấu hình](#2-cấu-hình)
3. [Tạo Bot & Lấy Token](#3-tạo-bot--lấy-token)
4. [Chuẩn bị Model User](#4-chuẩn-bị-model-user)
5. [Tạo Notification](#5-tạo-notification)
6. [Gửi Notification](#6-gửi-notification)
7. [Các loại tin nhắn](#7-các-loại-tin-nhắn)
8. [Gửi qua Queue](#8-gửi-qua-queue)
9. [Dùng nhiều Channel cùng lúc (Mail + Telegram)](#9-dùng-nhiều-channel-cùng-lúc-mail--telegram)
10. [Xử lý lỗi](#10-xử-lý-lỗi)

---

## 1. Cài đặt

```bash
composer require laravel-notification-channels/telegram
```

---

## 2. Cấu hình

### `.env`

```env
TELEGRAM_BOT_TOKEN=123456789:ABCdefGHIjklMNOpqrsTUVwxyz
```

### `config/services.php`

```php
'telegram-bot-api' => [
    'token' => env('TELEGRAM_BOT_TOKEN'),
],
```

---

## 3. Tạo Bot & Lấy Token

### Bước 1 — Tạo bot

1. Mở Telegram, tìm **@BotFather**
2. Gửi lệnh `/newbot`
3. Đặt tên bot (ví dụ: `MyApp Bot`)
4. Đặt username bot (phải kết thúc bằng `bot`, ví dụ: `myapp_bot`)
5. BotFather sẽ trả về **token** dạng:

```
123456789:ABCdefGHIjklMNOpqrsTUVwxyz
```

### Bước 2 — Lấy Chat ID của người dùng

Sau khi người dùng nhắn tin cho bot, gọi API:

```
https://api.telegram.org/bot<TOKEN>/getUpdates
```

Tìm trường `message.chat.id` trong response JSON — đó chính là `chat_id` cần lưu vào database.

---

## 4. Chuẩn bị Model User

### Thêm cột `telegram_chat_id` vào bảng `users`

```bash
php artisan make:migration add_telegram_chat_id_to_users_table
```

```php
// database/migrations/xxxx_add_telegram_chat_id_to_users_table.php

public function up(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->string('telegram_chat_id')->nullable()->after('email');
    });
}
```

```bash
php artisan migrate
```

### Cập nhật Model `User`

```php
// app/Models/User.php

use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'telegram_chat_id', // thêm vào đây
    ];

    // Để Notification Channel biết gửi đến chat_id nào
    public function routeNotificationForTelegram(): string
    {
        return $this->telegram_chat_id;
    }
}
```

---

## 5. Tạo Notification

```bash
php artisan make:notification OrderShipped
```

```php
// app/Notifications/OrderShipped.php

<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramChannel;
use NotificationChannels\Telegram\TelegramMessage;

class OrderShipped extends Notification
{
    public function __construct(private $order) {}

    // Khai báo kênh gửi
    public function via($notifiable): array
    {
        return [TelegramChannel::class];
    }

    // Nội dung tin nhắn Telegram
    public function toTelegram($notifiable): TelegramMessage
    {
        return TelegramMessage::create()
            ->content(
                "🚀 *Đơn hàng #{$this->order->id} đã được giao!*\n\n" .
                "📦 Sản phẩm: {$this->order->product_name}\n" .
                "💰 Tổng tiền: " . number_format($this->order->total) . " VND"
            )
            ->button('Xem đơn hàng', route('orders.show', $this->order->id));
    }
}
```

---

## 6. Gửi Notification

### Gửi cho một user

```php
use App\Notifications\OrderShipped;

$user->notify(new OrderShipped($order));
```

### Gửi cho nhiều user

```php
use Illuminate\Support\Facades\Notification;
use App\Notifications\OrderShipped;

$users = User::whereNotNull('telegram_chat_id')->get();

Notification::send($users, new OrderShipped($order));
```

### Gửi tức thì không qua model (On-demand)

```php
use Illuminate\Support\Facades\Notification;
use NotificationChannels\Telegram\TelegramChannel;

Notification::route(TelegramChannel::class, '123456789')
    ->notify(new OrderShipped($order));
```

---

## 7. Các loại tin nhắn

### Tin nhắn văn bản đơn giản

```php
TelegramMessage::create()
    ->content('Xin chào từ Laravel! 👋');
```

### Markdown (in đậm, nghiêng, code)

```php
TelegramMessage::create()
    ->content(
        "*In đậm*\n" .
        "_In nghiêng_\n" .
        "`code inline`\n" .
        "```\ncode block\n```"
    );
```

### Thêm nút bấm (Inline Button)

```php
TelegramMessage::create()
    ->content('Đơn hàng của bạn đã sẵn sàng!')
    ->button('✅ Xác nhận', 'https://yourapp.com/confirm')
    ->button('❌ Hủy', 'https://yourapp.com/cancel');
```

### Gửi ảnh kèm caption

```php
use NotificationChannels\Telegram\TelegramFile;

TelegramFile::create()
    ->to($notifiable->telegram_chat_id)
    ->content('🖼 Ảnh hóa đơn của bạn:')
    ->photo('https://yourapp.com/invoice.png');
```

### Gửi file/tài liệu

```php
TelegramFile::create()
    ->to($notifiable->telegram_chat_id)
    ->content('📄 Hóa đơn PDF:')
    ->document('/path/to/invoice.pdf');
```

---

## 8. Gửi qua Queue

Implement interface `ShouldQueue` để đẩy vào hàng đợi, tránh làm chậm request:

```php
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Queueable;

class OrderShipped extends Notification implements ShouldQueue
{
    use Queueable;

    // Chỉ định queue riêng (tuỳ chọn)
    public string $queue = 'notifications';

    // Retry nếu thất bại
    public int $tries = 3;

    public function __construct(private $order) {}

    public function via($notifiable): array
    {
        return [TelegramChannel::class];
    }

    public function toTelegram($notifiable): TelegramMessage
    {
        return TelegramMessage::create()
            ->content("📦 Đơn #{$this->order->id} đã được xử lý!");
    }
}
```

Chạy queue worker:

```bash
php artisan queue:work --queue=notifications
```

---

## 9. Dùng nhiều Channel cùng lúc (Mail + Telegram)

```php
use Illuminate\Notifications\Messages\MailMessage;
use NotificationChannels\Telegram\TelegramChannel;
use NotificationChannels\Telegram\TelegramMessage;

class OrderShipped extends Notification
{
    public function via($notifiable): array
    {
        $channels = ['mail'];

        // Chỉ gửi Telegram nếu user đã kết nối
        if ($notifiable->telegram_chat_id) {
            $channels[] = TelegramChannel::class;
        }

        return $channels;
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Đơn hàng đã giao')
            ->line('Đơn hàng của bạn đã được giao thành công.');
    }

    public function toTelegram($notifiable): TelegramMessage
    {
        return TelegramMessage::create()
            ->content('🚀 Đơn hàng đã giao thành công!');
    }
}
```

---

## 10. Xử lý lỗi

### Lắng nghe sự kiện thất bại

```php
// app/Providers/EventServiceProvider.php

use Illuminate\Notifications\Events\NotificationFailed;

protected $listen = [
    NotificationFailed::class => [
        \App\Listeners\HandleNotificationFailed::class,
    ],
];
```

```php
// app/Listeners/HandleNotificationFailed.php

class HandleNotificationFailed
{
    public function handle(NotificationFailed $event): void
    {
        // $event->channel  → 'telegram'
        // $event->notifiable → User model
        // $event->notification → Notification instance

        Log::error('Telegram notification failed', [
            'user_id' => $event->notifiable->id,
            'channel' => $event->channel,
        ]);
    }
}
```

### Kiểm tra trước khi gửi

```php
// Chỉ gửi nếu user có chat_id hợp lệ
if ($user->telegram_chat_id) {
    $user->notify(new OrderShipped($order));
}
```

---

## Tóm tắt Flow

```
User nhắn bot → Lưu chat_id vào DB → App gọi $user->notify() 
→ TelegramChannel::send() → Telegram Bot API → User nhận tin nhắn
```

---

## Tài liệu tham khảo

- [laravel-notification-channels/telegram](https://github.com/laravel-notification-channels/telegram)
- [Laravel Notifications](https://laravel.com/docs/notifications)
- [Telegram Bot API](https://core.telegram.org/bots/api)
