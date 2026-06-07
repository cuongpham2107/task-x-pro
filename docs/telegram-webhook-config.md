# Cấu hình Webhook Telegram Bot

## 1. Tạo Bot Telegram

1. Mở Telegram, tìm @BotFather
2. Gửi lệnh `/newbot`
3. Đặt tên bot (VD: `TaskXPro Bot`)
4. Đặt username (VD: `taskxpro_bot`)
5. BotFather sẽ trả về **token** — copy token này

## 2. Cấu hình .env

Thêm vào file `.env`:

```
TELEGRAM_BOT_NAME=taskxpro_bot
TELEGRAM_TOKEN=<token từ BotFather>
```

> **Lưu ý:** `TELEGRAM_TOKEN` tương ứng với config key `services.telegram-bot-api.token` trong `config/services.php`.

## 3. Set Webhook URL

Telegram yêu cầu HTTPS. Gửi request đến Telegram API để set webhook:

```
GET https://api.telegram.org/bot<TOKEN>/setWebhook?url=https://domain.com/telegram/webhook
```

Thay `<TOKEN>` bằng token từ BotFather, `domain.com` bằng domain thật của bạn.

**Response thành công:**
```json
{"ok": true, "result": true, "description": "Webhook was set"}
```

## 4. Local Development với ngrok

Dùng ngrok để expose local server ra HTTPS:

1. Cài ngrok: `brew install ngrok` (macOS) hoặc tải từ https://ngrok.com
2. Chạy Laravel: `php artisan serve`
3. Chạy ngrok: `ngrok http 8000`
4. Copy URL từ ngrok (VD: `https://abc123.ngrok.io`)
5. Set webhook:

```
GET https://api.telegram.org/bot<TOKEN>/setWebhook?url=https://abc123.ngrok.io/telegram/webhook
```

## 5. Kiểm tra Webhook

Gửi tin nhắn `/start` đến bot trên Telegram.

Kiểm tra webhook status:
```
GET https://api.telegram.org/bot<TOKEN>/getWebhookInfo
```

## 6. Xoá Webhook

Khi không cần dùng nữa:
```
GET https://api.telegram.org/bot<TOKEN>/deleteWebhook
```

## 7. Lưu ý

- Webhook chỉ hoạt động với HTTPS (Telegram yêu cầu)
- Mỗi bot chỉ có một webhook duy nhất
- Laravel route: `POST /telegram/webhook` → `TelegramWebhookController`
- Controller xử lý: `/start` → danh sách dự án → chọn dự án → báo cáo tiến độ
