# Telegram Bot Revamp — Design Spec

**Ngày:** 07/06/2026
**Mục tiêu:** Sửa nội dung tin nhắn Telegram, thêm notification mới, restructure schedule, thêm interactive project progress check theo SRS.

## Phạm vi

1. Sửa format 6 notification hiện có
2. Tạo mới 4 notification
3. Restructure `routes/console.php` theo đúng schedule SRS
4. Webhook + interactive project progress check (Section 3)

---

## 1. Format 6 Notification hiện có

### 1.1 ApprovalResults (kịch bản 2.1 - Duyệt ĐẠT)

```
✅ Task "{name}" thuộc Phase "{phase}" của Dự án "{project}" đã được phê duyệt.
📝 Đánh giá: {rating}/5
    {reviewNote}
```

- Thêm `rating: int`, `reviewNote: ?string` vào constructor
- Button: "Xem công việc"

### 1.2 TaskRejectedNotification (kịch bản 2.2 - KHÔNG ĐẠT)

```
❌ Task "{name}" thuộc Phase "{phase}" của Dự án "{project}" không đạt.
⚠️ Lý do: {reason}
```

- Button: "Xem công việc"

### 1.3 TaskAssignedNotification (kịch bản 2.3 - Giao task mới)

```
🆕 Task "{name}" vừa được giao cho bạn bởi {assigner}.
📁 Dự án: {project}
📋 Phase: {phase}
⏳ Deadline: {deadline}
```

- Giữ `isCoAssignee` để hiển thị role nếu cần
- Button: "Xem công việc"

### 1.4 TaskDeadlineReminderNotification (kịch bản 2.6 - Nhắc deadline)

```
⏰ Task "{name}" sắp đến hạn. Còn {days} ngày.
🗓️ Deadline: {deadline}
📁 Dự án: {project}
🔖 Giai đoạn: {phase}
```

- Trigger tại D-2 đến D-0 (không còn chạy D-3)
- Button: "Xem công việc"

### 1.5 TaskApprovalPendingReminderNotification (kịch bản 2.9 - Nhắc duyệt)

```
⚠️ Cảnh báo — còn {count} task chưa được phê duyệt.
👉 Vui lòng xử lý để PIC không bị chậm tiến độ.
```

- **Thay đổi constructor:** nhận `count: int` thay vì `task` + `pendingHours`
- Gửi lúc 17:00 thay vì 07:00
- Button: không có, hoặc "Mở Dashboard"

### 1.6 TaskApprovalRequestLeaderNotification (kịch bản 2.10 - Task cần duyệt)

```
📤 Task "{name}" đã được {pic} gửi và cần Leader phê duyệt.
📁 Dự án: {project}
🔖 Giai đoạn: {phase}
```

- Đổi label từ "actor" thành "pic" trong constructor để rõ nghĩa
- Button: "Xem công việc"

---

## 2. 4 Notification mới

### 2.1 PicDailySummaryNotification (kịch bản 2.4 - 08:30 daily)

```
☀️ Chào buổi sáng! Tổng kết công việc hôm nay:
📋 Có {todayCount} task hôm nay cần hoàn thành.
🔴 Số task quá hạn: {overdueCount}
```

- Constructor: `todayCount: int, overdueCount: int`
- Chỉ gửi cho PIC có task hôm nay hoặc task quá hạn
- Button: "Xem danh sách công việc" → tasks.index

### 2.2 PicWeeklyReportNotification (kịch bản 2.7 - 08:00 Thứ 7)

```
📊 BÁO CÁO CUỐI TUẦN (từ {start} đến {end})
✅ Tổng số task ĐÃ LÀM trong tuần: {total}
🟢 Số task ĐẠT: {approved}
🔴 Số task KHÔNG ĐẠT: {rejected}
🟡 Số task CHƯA được phê duyệt: {pending}
```

- Constructor: `startDate: Carbon, endDate: Carbon, total: int, approved: int, rejected: int, pending: int`
- Button: "Mở Dashboard"

### 2.3 LeaderWeeklyReportNotification (kịch bản 2.8 - 08:00 Thứ 7)

```
📈 BÁO CÁO CUỐI TUẦN (từ {start} đến {end})
👤 Leader: {leaderName}
📊 Tổng quan: {total} dự án đang chủ trì
✅ {onTrack} đúng tiến độ | 🟠 {atRisk} rủi ro | 🔴 {overdue} trễ hạn
─────────────────
📁 1. Dự án "{name}" — Tiến độ tổng thể: {progress}%
🗓️ Deadline: {date} | Trạng thái: {status}
```

- Mỗi item project: name, progress, deadline, status
- Status: "Đúng tiến độ" / "Rủi ro" / "Trễ hạn"
- Button: "Mở Dashboard"

### 2.4 CeoWeeklyReportNotification (kịch bản 2.11 - 08:00 Thứ 7)

```
🏢 BÁO CÁO CUỐI TUẦN (từ {start} đến {end})
📊 TỔNG QUAN
  • Tổng dự án đang theo dõi: {total}
  • ✅ Hoàn thành trong tuần: {completed}
  • 🔄 Đang tiến độ: {inProgress}
  • 🟠 Chậm tiến độ: {atRisk}
  • 🔴 Trễ hạn: {overdue}
─────────────────
✅ DỰ ÁN HOÀN THÀNH ({completed})
  • "{name}" — hoàn thành ngày {date}
🔄 DỰ ÁN ĐANG TIẾN ĐỘ ({inProgress})
  • "{name}" — đạt {progress}% | Deadline {date}
🟠 DỰ ÁN CHẬM TIẾN ĐỘ ({atRisk})
  • "{name}" — đạt {progress}% | còn {days} ngày
🔴 DỰ ÁN TRỄ HẠN ({overdue})
  • "{name}" — đạt {progress}% | trễ {days} ngày
```

- Button: "Mở Dashboard"

---

## 3. Restructure console.php

### 3.1 Command map

| Schedule | Command | Thay đổi |
|---|---|---|
| 07:00 daily | `tasks:mark-late` | Giữ nguyên |
| 07:00 daily | `projects:mark-overdue` | Giữ nguyên (gửi ProjectOverdueNotification) |
| 08:30 daily | `tasks:daily-summary` | **MỚI** — gửi PicDailySummaryNotification |
| 08:30 daily | `tasks:deadline-reminders` | **Tách từ cũ** — query D-2 đến D-0 |
| 17:00 daily | `tasks:pending-approval-reminder` | **Tách từ cũ** — gửi TaskApprovalPendingReminderNotification theo SRS 2.9 |
| 17:00 daily | `tasks:pic-overdue-warning` | **Tách từ cũ** — giữ PicOverdueTasksNotification |
| 08:00 Thứ 7 | `reports:weekly-pic` | **MỚI** — gửi PicWeeklyReportNotification |
| 08:00 Thứ 7 | `reports:weekly-leader` | **MỚI** — gửi LeaderWeeklyReportNotification |
| 08:00 Thứ 7 | `reports:weekly-ceo` | **MỚI** — thay thế reports:weekly cũ |
| 01:00 daily | `kpi:daily-sync` | Giữ nguyên |
| 01-02/mo | `kpi:monthly-sync` | Giữ nguyên |
| 01:00 1st | `kpi:backfill-missing-months` | Giữ nguyên |

### 3.2 Xoá

- `WeeklySummaryNotification.php` — thay thế bởi 3 báo cáo riêng
- `tasks:daily-reminders` command cũ — tách thành 3 command riêng

### 3.3 Sửa

- `TaskDeadlineReminderNotification`: trigger từ D-2 đến D-0 (hiện tại query 3 ngày)
- `TaskApprovalPendingReminderNotification`: chuyển schedule 17:00 + sửa constructor nhận count

---

## 4. Interactive Progress Check (Section 3)

### 4.1 Webhook Architecture

- Route: `POST /telegram/webhook` → `TelegramWebhookController`
- Controller xử lý 2 loại update:
  - `callback_query`: user chọn dự án từ inline keyboard
  - `message`: text `/start` hoặc command
- Sử dụng `Telegram::sendMessage()` với `reply_markup` (inline keyboard)

### 4.2 Flow

1. User gửi `/start` hoặc nhấn nút "Kiểm tra tiến độ dự án"
2. Bot gửi inline keyboard: `[Dự án A] [Dự án B] ...`
3. User tap 1 dự án → callback_data chứa `project_id`
4. Bot query Project/Phase/Task → gửi báo cáo format:

```
🔎 BÁO CÁO TIẾN ĐỘ DỰ ÁN: {project}
📊 Tiến độ tổng thể: {progress}% | 🗓️ Deadline: {date}
🔖 Giai đoạn "{phase}" — đạt {phaseProgress}%
   ✅ Hoàn thành: {done} | ⏳ Đang chạy: {running} | ⬜ Chưa làm: {todo} | ❌ Trễ hạn: {late}
   • "{task}" — {status} — {deadline}
```

### 4.3 Cấu hình Webhook

Cần set Telegram webhook URL trỏ đến:
```
https://domain.com/telegram/webhook
```

Các bước cấu hình chi tiết sẽ được viết trong guide riêng.

---

## 5. Các thành phần không thay đổi

- `PicOverloadWarningNotification.php` — giữ nguyên
- `MonthlyKpiSummaryNotification.php` — giữ nguyên
- `PicOverdueTasksNotification.php` — giữ nguyên (chỉ chuyển schedule)
- `ProjectOverdueNotification.php` — giữ nguyên

---

## 6. Files thay đổi

### Sửa
- `routes/console.php` — restructure schedule
- `app/Notifications/ApprovalResults.php` — format mới + thêm rating/reviewNote
- `app/Notifications/TaskRejectedNotification.php` — format mới
- `app/Notifications/TaskAssignedNotification.php` — format mới
- `app/Notifications/TaskDeadlineReminderNotification.php` — format mới + D-2 trigger
- `app/Notifications/TaskApprovalPendingReminderNotification.php` — format mới + constructor
- `app/Notifications/TaskApprovalRequestLeaderNotification.php` — format mới
- `bootstrap/app.php` — cần routeMiddleware cho webhook

### Tạo mới
- `app/Notifications/PicDailySummaryNotification.php`
- `app/Notifications/PicWeeklyReportNotification.php`
- `app/Notifications/LeaderWeeklyReportNotification.php`
- `app/Notifications/CeoWeeklyReportNotification.php`
- `app/Http/Controllers/TelegramWebhookController.php`

### Xoá
- `app/Notifications/WeeklySummaryNotification.php`

---

## 7. Rủi ro & Lưu ý

- Thay đổi constructor của `TaskApprovalPendingReminderNotification`: cần check tất cả chỗ gọi `new TaskApprovalPendingReminderNotification(...)` trong codebase
- Xoá `WeeklySummaryNotification`: đảm bảo không còn reference nào
- Telegram message có giới hạn 4096 ký tự; CEO report nhiều dự án cần kiểm soát độ dài
- Webhook cần HTTPS để Telegram chấp nhận; khi dev local dùng ngrok
