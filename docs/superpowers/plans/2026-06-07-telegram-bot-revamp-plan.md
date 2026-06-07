# Telegram Bot Revamp — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Sửa format 6 notification Telegram hiện có, tạo 4 notification mới, restructure console.php schedule, thêm webhook + interactive progress check theo SRS.

**Architecture:** Giữ nguyên kiến trúc notification Laravel (`Notification::send()` + `TelegramChannel`). Console commands tách theo từng khung giờ SRS. Webhook handler dùng raw Telegram Bot API gọi `sendMessage` với inline keyboard.

**Tech Stack:** `laravel-notification-channels/telegram` v6, Laravel 12 Artisan commands, Telegram Bot API (webhook)

---

## File Structure

### Create
- `app/Notifications/PicDailySummaryNotification.php` — Nhắc sáng 08:30 (SRS 2.4)
- `app/Notifications/PicWeeklyReportNotification.php` — Báo cáo tuần PIC (SRS 2.7)
- `app/Notifications/LeaderWeeklyReportNotification.php` — Báo cáo tuần Leader (SRS 2.8)
- `app/Notifications/CeoWeeklyReportNotification.php` — Báo cáo tuần CEO (SRS 2.11)
- `app/Http/Controllers/TelegramWebhookController.php` — Xử lý webhook + inline keyboard
- `docs/telegram-webhook-config.md` — Hướng dẫn cấu hình webhook

### Modify
- `app/Notifications/ApprovalResults.php` — Format mới + thêm rating/reviewNote
- `app/Notifications/TaskRejectedNotification.php` — Format mới (SRS 2.2)
- `app/Notifications/TaskAssignedNotification.php` — Format mới (SRS 2.3)
- `app/Notifications/TaskDeadlineReminderNotification.php` — Format mới (SRS 2.6) + D-2 trigger
- `app/Notifications/TaskApprovalPendingReminderNotification.php` — Format + constructor mới (SRS 2.9)
- `app/Notifications/TaskApprovalRequestLeaderNotification.php` — Format mới (SRS 2.10)
- `routes/console.php` — Restructure schedule
- `routes/web.php` — Thêm webhook route
- `app/Services/Tasks/TaskApprovalService.php` — Cập nhật constructor gọi `ApprovalResults` mới
- `app/Services/Tasks/TaskService.php` — Giữ nguyên (constructor signatures không đổi cho TaskAssigned + TaskApprovalRequest)

### Delete
- `app/Notifications/WeeklySummaryNotification.php` — Thay thế bởi 3 báo cáo riêng
- `tests/Feature/WeeklySummaryNotificationTest.php` — Test cũ

### Tests
- `tests/Feature/Notifications/ApprovalResultsTest.php` — Update test với constructor mới
- `tests/Feature/TaskDeadlineReminderNotificationTest.php` — Giữ nguyên
- `tests/Feature/Notifications/PicDailySummaryNotificationTest.php` — Test mới
- `tests/Feature/Notifications/PicWeeklyReportNotificationTest.php` — Test mới
- `tests/Feature/Notifications/LeaderWeeklyReportNotificationTest.php` — Test mới
- `tests/Feature/Notifications/CeoWeeklyReportNotificationTest.php` — Test mới

---

### Task 1: Sửa ApprovalResults — format + rating/reviewNote (SRS 2.1)

**Files:**
- Modify: `app/Notifications/ApprovalResults.php`
- Modify: `app/Services/Tasks/TaskApprovalService.php:27,104-106,350`
- Modify: `tests/Feature/Notifications/ApprovalResultsTest.php`

- [ ] **Step 1: Cập nhật `sendCEOApprovalNotification` để truyền rating + comment**

Trong `TaskApprovalService::approve()` (line 27), `$starRating` và `$comment` đã có sẵn. Truyền chúng vào `sendCEOApprovalNotification`:

```php
$this->sendCEOApprovalNotification($task, $actor, $starRating, $comment);
```

Sửa signature `sendCEOApprovalNotification`:

```php
public function sendCEOApprovalNotification(Task $task, User $leader, ?int $starRating = null, ?string $comment = null): void
```

Sửa constructor call tại line 350:
```php
Notification::send($recipient, new ApprovalResults($task, $leader, $starRating, $comment));
```

- [ ] **Step 2: Cập nhật ApprovalResults — constructor + format mới**

```php
public function __construct(
    public Task $task,
    public User $leader,
    public ?int $rating = null,
    public ?string $reviewNote = null,
) {}
```

Sửa `toTelegram`:

```php
public function toTelegram(object $notifiable): TelegramMessage
{
    $this->task->loadMissing(['phase.project']);

    $taskName = trim((string) $this->task->name) !== '' ? $this->task->name : "Task #{$this->task->id}";
    $projectName = $this->task->phase?->project?->name;
    $phaseName = $this->task->phase?->name;

    $parts = ["✅ Task \"{$taskName}\""];
    if ($phaseName !== null && trim((string) $phaseName) !== '') {
        $parts[] = "thuộc Phase \"{$phaseName}\"";
    }
    if ($projectName !== null && trim((string) $projectName) !== '') {
        $parts[] = "của Dự án \"{$projectName}\"";
    }
    $parts[] = 'đã được phê duyệt.';

    $content = implode(' ', $parts);

    if ($this->rating !== null) {
        $content .= "\n📝 Đánh giá: {$this->rating}/5";
        if ($this->reviewNote !== null && trim($this->reviewNote) !== '') {
            $content .= "\n    ".trim($this->reviewNote);
        }
    }

    $message = TelegramMessage::create()
        ->to((string) $notifiable->telegram_id)
        ->content($content);

    $taskUrl = $this->resolveTaskUrl();
    if ($taskUrl !== null) {
        $message->button('Xem công việc', $taskUrl);
    }

    return $message;
}
```

- [ ] **Step 3: Update test — truyền tham số mới**

```php
$notification = new ApprovalResults($task, $leader, 4, 'Hoàn thành tốt');
```

- [ ] **Step 4: Chạy test**

Run: `php artisan test --compact --filter=ApprovalResultsTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Notifications/ApprovalResults.php app/Services/Tasks/TaskApprovalService.php tests/Feature/Notifications/ApprovalResultsTest.php
git commit -m "feat: update ApprovalResults with rating/review format per SRS 2.1"
```

---

### Task 2: Sửa TaskRejectedNotification — format (SRS 2.2)

**Files:**
- Modify: `app/Notifications/TaskRejectedNotification.php`

- [ ] **Step 1: Sửa `toTelegram` format**

```php
public function toTelegram(object $notifiable): TelegramMessage
{
    $this->task->loadMissing(['phase.project']);

    $taskName = trim((string) $this->task->name) !== '' ? $this->task->name : "Task #{$this->task->id}";
    $projectName = $this->task->phase?->project?->name;
    $phaseName = $this->task->phase?->name;

    $parts = ["❌ Task \"{$taskName}\""];
    if ($phaseName !== null && trim((string) $phaseName) !== '') {
        $parts[] = "thuộc Phase \"{$phaseName}\"";
    }
    if ($projectName !== null && trim((string) $projectName) !== '') {
        $parts[] = "của Dự án \"{$projectName}\"";
    }
    $parts[] = 'không đạt.';

    $content = implode(' ', $parts);

    $reason = trim($this->reason);
    if ($reason !== '') {
        $content .= "\n⚠️ Lý do: {$reason}";
    }

    $message = TelegramMessage::create()
        ->to((string) $notifiable->telegram_id)
        ->content($content);

    $taskUrl = $this->resolveTaskUrl();
    if ($taskUrl !== null) {
        $message->button('Xem công việc', $taskUrl);
    }

    return $message;
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Notifications/TaskRejectedNotification.php
git commit -m "feat: update TaskRejectedNotification format per SRS 2.2"
```

---

### Task 3: Sửa TaskAssignedNotification — format (SRS 2.3)

**Files:**
- Modify: `app/Notifications/TaskAssignedNotification.php`

- [ ] **Step 1: Sửa `toTelegram` format**

```php
public function toTelegram(object $notifiable): TelegramMessage
{
    $this->task->loadMissing(['phase.project']);

    $taskName = trim((string) $this->task->name) !== '' ? $this->task->name : "Công việc #{$this->task->id}";
    $assignerName = trim((string) $this->assigner->name) !== '' ? $this->assigner->name : 'Người giao việc';
    $deadlineText = $this->task->deadline?->format('d/m/Y H:i') : 'N/A';
    $projectName = $this->task->phase?->project?->name;
    $phaseName = $this->task->phase?->name;

    $content = "🆕 Task \"{$taskName}\" vừa được giao cho bạn bởi {$assignerName}.";
    $content .= "\n📁 Dự án: ".($projectName ?? 'N/A');
    $content .= "\n📋 Phase: ".($phaseName ?? 'N/A');
    $content .= "\n⏳ Deadline: {$deadlineText}";

    $message = TelegramMessage::create()
        ->to((string) $notifiable->telegram_id)
        ->content($content);

    $taskUrl = $this->resolveTaskUrl();
    if ($taskUrl !== null) {
        $message->button('Xem công việc', $taskUrl);
    }

    return $message;
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Notifications/TaskAssignedNotification.php
git commit -m "feat: update TaskAssignedNotification format per SRS 2.3"
```

---

### Task 4: Sửa TaskDeadlineReminderNotification — format + D-2 trigger (SRS 2.6)

**Files:**
- Modify: `app/Notifications/TaskDeadlineReminderNotification.php`
- Modify: `routes/console.php` (sẽ làm ở Task 9 — phần tách command)
- Modify: `tests/Feature/TaskDeadlineReminderNotificationTest.php`

- [ ] **Step 1: Sửa `toTelegram` format**

```php
public function toTelegram(object $notifiable): TelegramMessage
{
    $this->task->loadMissing(['phase.project']);

    $taskName = trim((string) $this->task->name) !== '' ? $this->task->name : "Công việc #{$this->task->id}";
    $deadlineText = $this->task->deadline?->format('d/m/Y H:i') ?? 'N/A';
    $projectName = $this->task->phase?->project?->name;
    $phaseName = $this->task->phase?->name;

    $daysLeft = max(0, $this->daysLeft);
    $content = "⏰ Task \"{$taskName}\" sắp đến hạn. Còn {$daysLeft} ngày.";
    $content .= "\n🗓️ Deadline: {$deadlineText}";
    $content .= "\n📁 Dự án: ".($projectName ?? 'N/A');
    $content .= "\n🔖 Giai đoạn: ".($phaseName ?? 'N/A');

    $message = TelegramMessage::create()
        ->to((string) $notifiable->telegram_id)
        ->content($content);

    $taskUrl = $this->resolveTaskUrl();
    if ($taskUrl !== null) {
        $message->button('Xem công việc', $taskUrl);
    }

    return $message;
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Notifications/TaskDeadlineReminderNotification.php
git commit -m "feat: update TaskDeadlineReminderNotification format per SRS 2.6"
```

---

### Task 5: Sửa TaskApprovalPendingReminderNotification — format + constructor mới (SRS 2.9)

**Files:**
- Modify: `app/Notifications/TaskApprovalPendingReminderNotification.php`
- Modify: `routes/console.php` (sẽ xử lý ở Task 9)

- [ ] **Step 1: Sửa constructor + toTelegram**

```php
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
```

- [ ] **Step 2: Commit**

```bash
git add app/Notifications/TaskApprovalPendingReminderNotification.php
git commit -m "feat: update TaskApprovalPendingReminderNotification format+constructor per SRS 2.9"
```

---

### Task 6: Sửa TaskApprovalRequestLeaderNotification — format (SRS 2.10)

**Files:**
- Modify: `app/Notifications/TaskApprovalRequestLeaderNotification.php`

- [ ] **Step 1: Đổi tên tham số `actor` thành `pic` + format mới**

Constructor:
```php
public function __construct(
    public Task $task,
    public User $pic,
) {}
```

Sửa `toTelegram`:
```php
public function toTelegram(object $notifiable): TelegramMessage
{
    $this->task->loadMissing(['phase.project']);

    $picName = trim((string) $this->pic->name) !== '' ? $this->pic->name : 'PIC';
    $taskName = trim((string) $this->task->name) !== '' ? $this->task->name : "Task #{$this->task->id}";
    $projectName = $this->task->phase?->project?->name;
    $phaseName = $this->task->phase?->name;

    $content = "📤 Task \"{$taskName}\" đã được {$picName} gửi và cần Leader phê duyệt.";
    $content .= "\n📁 Dự án: ".($projectName ?? 'N/A');
    $content .= "\n🔖 Giai đoạn: ".($phaseName ?? 'N/A');

    $message = TelegramMessage::create()
        ->to((string) $notifiable->telegram_id)
        ->content($content);

    $taskUrl = $this->resolveTaskUrl();
    if ($taskUrl !== null) {
        $message->button('Xem công việc', $taskUrl);
    }

    return $message;
}
```

- [ ] **Step 2: Cập nhật caller trong TaskService**

File: `app/Services/Tasks/TaskService.php:409`

Sửa `$actor` thành `$pic`:
```php
Notification::send($leaders, new TaskApprovalRequestLeaderNotification($task, $pic));
```
(Verify `$pic` là tên biến đúng ở context đó)

- [ ] **Step 3: Commit**

```bash
git add app/Notifications/TaskApprovalRequestLeaderNotification.php app/Services/Tasks/TaskService.php
git commit -m "feat: update TaskApprovalRequestLeaderNotification format per SRS 2.10"
```

---

### Task 7: Tạo 4 Notification mới

**Files:**
- Create: `app/Notifications/PicDailySummaryNotification.php`
- Create: `app/Notifications/PicWeeklyReportNotification.php`
- Create: `app/Notifications/LeaderWeeklyReportNotification.php`
- Create: `app/Notifications/CeoWeeklyReportNotification.php`

- [ ] **Step 1: Tạo PicDailySummaryNotification**

```php
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
    ) {}

    public function via(object $notifiable): array
    {
        return [TelegramChannel::class];
    }

    public function toTelegram(object $notifiable): TelegramMessage
    {
        $todayCount = max(0, $this->todayCount);
        $overdueCount = max(0, $this->overdueCount);

        $content = "☀️ Chào buổi sáng! Tổng kết công việc hôm nay:\n";
        $content .= "📋 Có {$todayCount} task hôm nay cần hoàn thành.\n";
        $content .= "🔴 Số task quá hạn: {$overdueCount}";

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
```

- [ ] **Step 2: Tạo PicWeeklyReportNotification**

```php
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
```

- [ ] **Step 3: Tạo LeaderWeeklyReportNotification**

```php
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
            $content .= "📁 ".($i + 1).". Dự án \"{$project['name']}\" — Tiến độ tổng thể: {$project['progress']}%\n";
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
```

- [ ] **Step 4: Tạo CeoWeeklyReportNotification**

```php
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
```

- [ ] **Step 5: Commit**

```bash
git add app/Notifications/PicDailySummaryNotification.php app/Notifications/PicWeeklyReportNotification.php app/Notifications/LeaderWeeklyReportNotification.php app/Notifications/CeoWeeklyReportNotification.php
git commit -m "feat: add 4 new notifications per SRS 2.4 2.7 2.8 2.11"
```

---

### Task 8: Restructure console.php

**Files:**
- Modify: `routes/console.php`
- Delete: `app/Notifications/WeeklySummaryNotification.php`

- [ ] **Step 1: Viết command `tasks:daily-summary` (SRS 2.4 - 08:30)**

```php
use App\Notifications\PicDailySummaryNotification;

Artisan::command('tasks:daily-summary', function (): void {
    $now = now();

    $pics = User::query()
        ->whereHas('picTasks', function ($q) use ($now): void {
            $q->where(function ($q2) use ($now): void {
                $q2->whereDate('deadline', $now->toDateString())
                    ->orWhere('status', TaskStatus::Late->value);
            });
        })
        ->with(['picTasks' => function ($q) use ($now): void {
            $q->where(function ($q2) use ($now): void {
                $q2->whereDate('deadline', $now->toDateString())
                    ->orWhere('status', TaskStatus::Late->value);
            });
        }])
        ->get(['id', 'name', 'telegram_id']);

    $sent = 0;
    foreach ($pics as $pic) {
        if (trim((string) $pic->telegram_id) === '') {
            continue;
        }

        $todayCount = $pic->picTasks->filter(fn ($t) => $t->deadline?->isToday())->count();
        $overdueCount = $pic->picTasks->filter(fn ($t) => $t->status === TaskStatus::Late)->count();

        if ($todayCount === 0 && $overdueCount === 0) {
            continue;
        }

        try {
            Notification::send($pic, new PicDailySummaryNotification($todayCount, $overdueCount));
            $sent++;
        } catch (\Throwable $e) {
            report($e);
        }
    }

    $this->info("Daily summary sent to {$sent} PICs.");
})->purpose('Gửi thông báo sáng cho PIC về task hôm nay và task quá hạn');
```

- [ ] **Step 2: Viết command `tasks:deadline-reminders` (SRS 2.6 - D-2 trigger)**

```php
Artisan::command('tasks:deadline-reminders', function (): void {
    $now = now();
    $deadlineFrom = $now->copy()->startOfDay();
    $deadlineTo = $now->copy()->addDays(2)->endOfDay();

    $tasks = Task::query()
        ->whereNotNull('deadline')
        ->whereBetween('deadline', [$deadlineFrom, $deadlineTo])
        ->where('status', '!=', TaskStatus::Completed->value)
        ->with(['pic:id,name,telegram_id,email', 'phase.project:id,name'])
        ->get();

    $sent = 0;
    foreach ($tasks as $task) {
        $pic = $task->pic;
        if ($pic === null) {
            continue;
        }

        $hasTelegram = trim((string) $pic->telegram_id) !== '';
        $hasEmail = trim((string) $pic->email) !== '';
        if (! $hasTelegram && ! $hasEmail) {
            continue;
        }

        $daysLeft = (int) $now->copy()->startOfDay()->diffInDays($task->deadline, false);
        try {
            Notification::send($pic, new TaskDeadlineReminderNotification($task, $daysLeft));
            $sent++;
        } catch (\Throwable $e) {
            report($e);
        }
    }

    $this->info("Deadline reminders sent to {$sent} PICs.");
})->purpose('Nhắc deadline task còn 0-2 ngày (SRS 2.6)');
```

- [ ] **Step 3: Viết command `tasks:pending-approval-reminder` (SRS 2.9 - 17:00)**

Query tất cả task `WaitingApproval`, đếm số lượng, gửi cho từng Leader:

```php
Artisan::command('tasks:pending-approval-reminder', function (): void {
    $now = now();

    $pendingTasks = Task::query()
        ->where('status', TaskStatus::WaitingApproval->value)
        ->with(['phase.project.leaders:id,name,telegram_id'])
        ->get();

    $leaderCounts = [];
    foreach ($pendingTasks as $task) {
        $leaders = $task->phase?->project?->leaders ?? collect();
        foreach ($leaders as $leader) {
            if (trim((string) $leader->telegram_id) !== '') {
                $leaderId = (int) $leader->id;
                $leaderCounts[$leaderId] = ($leaderCounts[$leaderId] ?? 0) + 1;
            }
        }
    }

    $leaders = User::query()
        ->whereIn('id', array_keys($leaderCounts))
        ->get(['id', 'name', 'telegram_id']);

    $sent = 0;
    foreach ($leaders as $leader) {
        $count = $leaderCounts[(int) $leader->id] ?? 0;
        if ($count === 0) {
            continue;
        }
        try {
            Notification::send($leader, new TaskApprovalPendingReminderNotification($count));
            $sent++;
        } catch (\Throwable $e) {
            report($e);
        }
    }

    $this->info("Pending approval reminders sent to {$sent} leaders.");
})->purpose('Nhắc leader duyệt task lúc 17:00 (SRS 2.9)');
```

- [ ] **Step 4: Viết command `tasks:pic-overdue-warning` (giữ nguyên logic cũ)**

```php
Artisan::command('tasks:pic-overdue-warning', function (): void {
    $now = now();

    $overdueGroups = Task::query()
        ->whereNotNull('deadline')
        ->where('deadline', '<', $now)
        ->where('status', '!=', TaskStatus::Completed->value)
        ->selectRaw('pic_id, COUNT(*) as total')
        ->groupBy('pic_id')
        ->having('total', '>', 3)
        ->get();

    $pics = User::query()
        ->whereIn('id', $overdueGroups->pluck('pic_id')->filter())
        ->get(['id', 'name', 'telegram_id']);

    $sent = 0;
    foreach ($overdueGroups as $row) {
        $pic = $pics->firstWhere('id', (int) $row->pic_id);
        if ($pic === null || trim((string) $pic->telegram_id) === '') {
            continue;
        }
        try {
            Notification::send($pic, new PicOverdueTasksNotification($pic, (int) $row->total));
            $sent++;
        } catch (\Throwable $e) {
            report($e);
        }
    }

    $this->info("Overdue PIC warnings sent to {$sent} users.");
})->purpose('Cảnh báo PIC có >3 task quá hạn');
```

- [ ] **Step 5: Viết command `reports:weekly-pic` (SRS 2.7 - 08:00 Thứ 7)**

```php
use App\Notifications\PicWeeklyReportNotification;
use Illuminate\Support\Carbon;

Artisan::command('reports:weekly-pic', function (): void {
    $now = now();
    $weekStart = $now->copy()->startOfWeek(Carbon::MONDAY);
    $weekEnd = $now->copy()->startOfWeek(Carbon::MONDAY)->addDays(5); // T2-T7

    $pics = User::query()
        ->whereHas('picTasks', function ($q) use ($weekStart, $weekEnd): void {
            $q->where('progress', 100);
        })
        ->with(['picTasks' => function ($q) use ($weekStart, $weekEnd): void {
            $q->where('progress', 100);
        }])
        ->get(['id', 'name', 'telegram_id']);

    $sent = 0;
    foreach ($pics as $pic) {
        if (trim((string) $pic->telegram_id) === '') {
            continue;
        }

        $tasks = $pic->picTasks;
        $total = $tasks->count();

        $approved = $tasks->filter(fn ($t) =>
            $t->status === TaskStatus::Completed->value
            && $t->completed_at !== null
            && $t->completed_at->between($weekStart, $weekEnd)
        )->count();

        $rejected = $tasks->filter(fn ($t) =>
            $t->status === TaskStatus::InProgress->value
            && $t->approvalLogs()->where('action', 'rejected')->whereBetween('created_at', [$weekStart, $weekEnd])->exists()
        )->count();

        $pending = $tasks->filter(fn ($t) =>
            $t->status === TaskStatus::WaitingApproval->value
            && $t->updated_at !== null
            && $t->updated_at->between($weekStart, $weekEnd)
        )->count();

        if ($total === 0) {
            continue;
        }

        try {
            Notification::send($pic, new PicWeeklyReportNotification(
                $weekStart, $weekEnd, $total, $approved, $rejected, $pending
            ));
            $sent++;
        } catch (\Throwable $e) {
            report($e);
        }
    }

    $this->info("Weekly PIC reports sent to {$sent} PICs.");
})->purpose('Gửi báo cáo tuần cho PIC (SRS 2.7)');
```

- [ ] **Step 6: Viết command `reports:weekly-leader` (SRS 2.8 - 08:00 Thứ 7)**

```php
use App\Notifications\LeaderWeeklyReportNotification;
use Illuminate\Support\Carbon;

Artisan::command('reports:weekly-leader', function (): void {
    $now = now();
    $weekStart = $now->copy()->startOfWeek(Carbon::MONDAY);
    $weekEnd = $now->copy()->startOfWeek(Carbon::MONDAY)->addDays(5);

    $leaders = User::role('leader')->with(['leadingProjects' => function ($q): void {
        $q->whereNotIn('status', [ProjectStatus::Completed->value, ProjectStatus::Cancelled->value]);
    }])->get(['id', 'name', 'telegram_id']);

    $sent = 0;
    foreach ($leaders as $leader) {
        if (trim((string) $leader->telegram_id) === '') {
            continue;
        }

        $projects = $leader->leadingProjects ?? collect();
        if ($projects->isEmpty()) {
            continue;
        }

        $projectData = [];
        foreach ($projects as $project) {
            $progress = $project->progress ?? 0;
            $deadline = $project->end_date;
            $status = $this->classifyProjectStatus($project, $deadline, $progress, $now);

            $projectData[] = [
                'name' => $project->name,
                'progress' => (int) $progress,
                'deadline' => $deadline?->format('d/m/Y') ?? 'N/A',
                'status' => $status,
            ];
        }

        try {
            Notification::send($leader, new LeaderWeeklyReportNotification(
                $leader, $weekStart, $weekEnd, $projectData
            ));
            $sent++;
        } catch (\Throwable $e) {
            report($e);
        }
    }

    $this->info("Weekly leader reports sent to {$sent} leaders.");
})->purpose('Gửi báo cáo tuần cho Leader (SRS 2.8)');

// Helper function (đặt trong cùng file console.php)
function classifyProjectStatus(Project $project, ?Carbon $deadline, int $progress, Carbon $now): string
{
    if ($deadline === null) {
        return 'Đúng tiến độ';
    }

    if ($deadline->isPast()) {
        return 'Trễ hạn';
    }

    // Tính ⅔ thời gian dự án
    $startDate = $project->start_date ?? $project->created_at;
    $totalDuration = $startDate->diffInDays($deadline);
    $elapsed = $startDate->diffInDays($now);
    $twoThirdsPoint = $totalDuration > 0 ? $totalDuration * 2 / 3 : 0;

    if ($elapsed >= $twoThirdsPoint && $progress < 60) {
        return 'Rủi ro';
    }

    return 'Đúng tiến độ';
}
```

- [ ] **Step 7: Viết command `reports:weekly-ceo` (SRS 2.11 - 08:00 Thứ 7)**

```php
use App\Notifications\CeoWeeklyReportNotification;
use Illuminate\Support\Carbon;

Artisan::command('reports:weekly-ceo', function (): void {
    $now = now();
    $weekStart = $now->copy()->startOfWeek(Carbon::MONDAY);
    $weekEnd = $now->copy()->startOfWeek(Carbon::MONDAY)->addDays(5);

    $allProjects = Project::query()->get();
    $totalActive = $allProjects->count();

    // Hoàn thành trong tuần này
    $completed = $allProjects->filter(fn ($p) =>
        $p->status === ProjectStatus::Completed->value
        && $p->updated_at !== null
        && $p->updated_at->between($weekStart, $weekEnd)
    );

    // Đang tiến độ
    $inProgress = $allProjects->filter(fn ($p) =>
        ! in_array($p->status, [
            ProjectStatus::Completed->value,
            ProjectStatus::Cancelled->value,
            ProjectStatus::Overdue->value,
        ])
        && ! ($p->end_date !== null && $p->end_date->isPast())
    );

    // Chậm tiến độ (≤15 ngày tới deadline, <60%)
    $atRisk = $allProjects->filter(fn ($p) =>
        $p->end_date !== null
        && ! $p->end_date->isPast()
        && $p->end_date->diffInDays($now) <= 15
        && ($p->progress ?? 0) < 60
    );

    // Trễ hạn
    $overdue = $allProjects->filter(fn ($p) =>
        $p->status === ProjectStatus::Overdue->value
        || ($p->end_date !== null && $p->end_date->isPast() && $p->status !== ProjectStatus::Completed->value)
    );

    $completedData = $completed->map(fn ($p) => [
        'name' => $p->name,
        'date' => $p->updated_at?->format('d/m/Y') ?? 'N/A',
    ])->values()->toArray();

    $inProgressData = $inProgress->map(fn ($p) => [
        'name' => $p->name,
        'progress' => (int) ($p->progress ?? 0),
        'deadline' => $p->end_date?->format('d/m/Y') ?? 'N/A',
    ])->values()->toArray();

    $atRiskData = $atRisk->map(fn ($p) => [
        'name' => $p->name,
        'progress' => (int) ($p->progress ?? 0),
        'daysLeft' => (int) $now->diffInDays($p->end_date),
    ])->values()->toArray();

    $overdueData = $overdue->map(fn ($p) => [
        'name' => $p->name,
        'progress' => (int) ($p->progress ?? 0),
        'overdueDays' => $p->end_date !== null ? (int) $p->end_date->diffInDays($now, false) : 0,
    ])->values()->toArray();

    $ceos = User::role('ceo')->get(['id', 'name', 'telegram_id']);
    $sent = 0;

    foreach ($ceos as $ceo) {
        if (trim((string) $ceo->telegram_id) === '') {
            continue;
        }

        try {
            Notification::send($ceo, new CeoWeeklyReportNotification(
                $weekStart, $weekEnd, $totalActive,
                $completedData, $inProgressData, $atRiskData, $overdueData
            ));
            $sent++;
        } catch (\Throwable $e) {
            report($e);
        }
    }

    $this->info("Weekly CEO report sent to {$sent} CEOs.");
})->purpose('Gửi báo cáo tuần cho CEO (SRS 2.11)');
```

- [ ] **Step 8: Cập nhật schedule block**

```php
Schedule::command('tasks:mark-late')->daily()->at('07:00');
Schedule::command('projects:mark-overdue')->daily()->at('07:00');
Schedule::command('tasks:daily-summary')->daily()->at('08:30');
Schedule::command('tasks:deadline-reminders')->daily()->at('08:30');
Schedule::command('tasks:pending-approval-reminder')->daily()->at('17:00');
Schedule::command('tasks:pic-overdue-warning')->daily()->at('17:00');
Schedule::command('reports:weekly-pic')->weekly()->saturdays()->at('08:00');
Schedule::command('reports:weekly-leader')->weekly()->saturdays()->at('08:00');
Schedule::command('reports:weekly-ceo')->weekly()->saturdays()->at('08:00');
Schedule::command('kpi:daily-sync')->daily()->at('01:00')->withoutOverlapping()->onOneServer();
Schedule::command('kpi:monthly-sync')->monthlyOn(1, '02:00')->withoutOverlapping()->onOneServer();
Schedule::command('kpi:monthly-sync')->monthlyOn(2, '03:00')->withoutOverlapping()->onOneServer();
Schedule::command('kpi:backfill-missing-months', ['--months' => '12'])->monthlyOn(1, '04:00')->withoutOverlapping()->onOneServer();
```

- [ ] **Step 9: Xoá command `tasks:daily-reminders` cũ + `reports:weekly` cũ + WeeklySummaryNotification**

Xoá toàn bộ block `Artisan::command('tasks:daily-reminders', ...)` (lines 52-150) và `Artisan::command('reports:weekly', ...)` (lines 152-193).
Xoá các `use` import không còn dùng.

- [ ] **Step 10: Commit**

```bash
git add routes/console.php
git rm app/Notifications/WeeklySummaryNotification.php
git commit -m "feat: restructure console.php schedule per SRS"
```

---

### Task 9: Xoá WeeklySummaryNotification test cũ

**Files:**
- Delete: `tests/Feature/WeeklySummaryNotificationTest.php`

- [ ] **Step 1: Xoá file test cũ**

```bash
git rm tests/Feature/WeeklySummaryNotificationTest.php
git commit -m "tests: remove old WeeklySummaryNotification test"
```

---

### Task 10: Tạo TelegramWebhookController + route

**Files:**
- Create: `app/Http/Controllers/TelegramWebhookController.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Tạo controller**

```php
<?php

namespace App\Http\Controllers;

use App\Models\Phase;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $token = config('services.telegram.bot_token');
        if ($token === null || $token === '') {
            return response()->json(['ok' => false, 'error' => 'Bot token not configured'], 500);
        }

        $update = $request->all();

        if (isset($update['callback_query'])) {
            $this->handleCallbackQuery($update['callback_query'], $token);
        } elseif (isset($update['message'])) {
            $this->handleMessage($update['message'], $token);
        }

        return response()->json(['ok' => true]);
    }

    private function handleMessage(array $message, string $token): void
    {
        $chatId = $message['chat']['id'];
        $text = trim($message['text'] ?? '');

        if ($text === '/start' || $text === 'Kiểm tra tiến độ dự án') {
            $this->sendProjectList($chatId, $token);
        }
    }

    private function handleCallbackQuery(array $callback, string $token): void
    {
        $chatId = $callback['message']['chat']['id'];
        $messageId = $callback['message']['message_id'];
        $data = $callback['data'] ?? '';

        if (str_starts_with($data, 'project_')) {
            $projectId = (int) str_replace('project_', '', $data);
            $this->sendProjectProgress($chatId, $projectId, $token);
        }

        try {
            Http::post("https://api.telegram.org/bot{$token}/answerCallbackQuery", [
                'callback_query_id' => $callback['id'],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to answer callback query: '.$e->getMessage());
        }
    }

    private function sendProjectList(int $chatId, string $token): void
    {
        $projects = Project::query()
            ->whereNotIn('status', [
                \App\Enums\ProjectStatus::Completed,
                \App\Enums\ProjectStatus::Cancelled,
            ])
            ->get(['id', 'name']);

        if ($projects->isEmpty()) {
            Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => 'Hiện không có dự án nào đang chạy.',
            ]);
            return;
        }

        $keyboard = [];
        foreach ($projects as $project) {
            $keyboard[] = [
                ['text' => $project->name, 'callback_data' => "project_{$project->id}"],
            ];
        }

        Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id' => $chatId,
            'text' => 'Vui lòng chọn dự án cần kiểm tra:',
            'reply_markup' => json_encode([
                'inline_keyboard' => $keyboard,
            ]),
        ]);
    }

    private function sendProjectProgress(int $chatId, int $projectId, string $token): void
    {
        $project = Project::with(['phases.tasks'])->find($projectId);
        if ($project === null) {
            Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => 'Không tìm thấy dự án.',
            ]);
            return;
        }

        $projectProgress = $project->progress ?? 0;
        $deadlineText = $project->end_date?->format('d/m/Y') ?? 'N/A';

        $content = "🔎 BÁO CÁO TIẾN ĐỘ DỰ ÁN: {$project->name}\n";
        $content .= "📊 Tiến độ tổng thể: {$projectProgress}% | 🗓️ Deadline: {$deadlineText}\n";

        foreach ($project->phases as $phase) {
            $tasks = $phase->tasks ?? collect();
            $total = $tasks->count();
            $completed = $tasks->filter(fn ($t) => $t->status === \App\Enums\TaskStatus::Completed->value)->count();
            $inProgress = $tasks->filter(fn ($t) => $t->status === \App\Enums\TaskStatus::InProgress->value || $t->status === \App\Enums\TaskStatus::WaitingApproval->value)->count();
            $todo = $tasks->filter(fn ($t) => $t->status === \App\Enums\TaskStatus::Pending->value || $t->status === \App\Enums\TaskStatus::Init->value)->count();
            $late = $tasks->filter(fn ($t) => $t->status === \App\Enums\TaskStatus::Late->value)->count();
            $phaseProgress = $total > 0 ? round(($completed / $total) * 100) : 0;

            $content .= "\n🔖 Giai đoạn \"{$phase->name}\" — đạt {$phaseProgress}%\n";
            $content .= "   ✅ Hoàn thành: {$completed} | ⏳ Đang chạy: {$inProgress} | ⬜ Chưa làm: {$todo} | ❌ Trễ hạn: {$late}\n";

            foreach ($tasks as $task) {
                $taskDeadline = $task->deadline?->format('d/m/Y') ?? 'N/A';
                $content .= "   • \"{$task->name}\" — {$task->status->label()} — {$taskDeadline}\n";
            }
        }

        // Telegram limit 4096 chars; truncate if needed
        if (mb_strlen($content) > 4000) {
            $content = mb_substr($content, 0, 3997).'...';
        }

        Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $content,
        ]);
    }
}
```

- [ ] **Step 2: Thêm route webhook**

Trong `routes/web.php`, thêm:

```php
use App\Http\Controllers\TelegramWebhookController;

Route::post('/telegram/webhook', TelegramWebhookController::class);
```

- [ ] **Step 3: Commit**

```bash
git add app/Http/Controllers/TelegramWebhookController.php routes/web.php
git commit -m "feat: add Telegram webhook handler with inline keyboard (SRS Section 3)"
```

---

### Task 11: Tạo webhook config guide

**Files:**
- Create: `docs/telegram-webhook-config.md`

- [ ] **Step 1: Tạo guide**

Guide sẽ hướng dẫn:
1. Tạo Bot Telegram qua @BotFather, lấy token
2. Set token vào `.env`: `TELEGRAM_BOT_NAME=xxx` + `TELEGRAM_TOKEN=xxx`
3. Cấu hình webhook URL gọi đến `/telegram/webhook` (yêu cầu HTTPS)
4. Set webhook bằng request: `https://api.telegram.org/bot{token}/setWebhook?url=https://domain.com/telegram/webhook`
5. Test với `/start`
6. Dùng ngrok cho local dev

- [ ] **Step 2: Commit**

```bash
git add docs/telegram-webhook-config.md
git commit -m "docs: add Telegram webhook configuration guide"
```

---

### Task 12: Chạy Pint + Kiểm tra

- [ ] **Step 1: Format PHP**

Run: `vendor/bin/pint --format agent`

- [ ] **Step 2: Chạy all tests**

Run: `php artisan test --compact`
Expected: All tests pass (có thể cần update test cho các notification đã sửa)

- [ ] **Step 3: Fix nếu có lỗi**

Nếu test fail, sửa và chạy lại.

- [ ] **Step 4: Commit cuối**

```bash
git add -A
git commit -m "chore: apply pint formatting and fix tests"
```
