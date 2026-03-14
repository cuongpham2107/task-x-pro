<?php

use App\Enums\TaskStatus;
use App\Enums\UserStatus;
use App\Models\KpiScore;
use App\Models\Task;
use App\Models\User;
use App\Notifications\MonthlyKpiSummaryNotification;
use App\Notifications\PicOverdueTasksNotification;
use App\Notifications\TaskApprovalPendingReminderNotification;
use App\Notifications\TaskDeadlineReminderNotification;
use App\Notifications\WeeklySummaryNotification;
use App\Services\Tasks\TaskService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schedule;

Artisan::command('tasks:mark-late', function (TaskService $taskService): void {
    $affectedTasks = $taskService->markLateTasks();

    $this->info("Đã cập nhập {$affectedTasks} công việc sang trạng thái trễ hạn.");
})->purpose('Cập công việc trễ hạn theo deadline');

Artisan::command('tasks:daily-reminders', function (): void {
    $now = now();

    $deadlineFrom = $now->copy()->startOfDay();
    $deadlineTo = $now->copy()->addDays(3)->endOfDay();

    $deadlineTasks = Task::query()
        ->whereNotNull('deadline')
        ->whereBetween('deadline', [$deadlineFrom, $deadlineTo])
        ->where('status', '!=', TaskStatus::Completed->value)
        ->with(['pic:id,name,telegram_id,email', 'phase.project:id,name'])
        ->get();

    $deadlineNotifications = 0;
    foreach ($deadlineTasks as $task) {
        $pic = $task->pic;
        if ($pic === null) {
            continue;
        }

        $hasTelegram = trim((string) $pic->telegram_id) !== '';
        $hasEmail = trim((string) $pic->email) !== '';

        if (! $hasTelegram && ! $hasEmail) {
            continue;
        }

        $daysLeft = (int) $now->startOfDay()->diffInDays($task->deadline, false);
        try {
            Notification::send($pic, new TaskDeadlineReminderNotification($task, $daysLeft));
            $deadlineNotifications++;
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    $pendingTasks = Task::query()
        ->where('status', TaskStatus::WaitingApproval->value)
        ->with([
            'pic:id,name',
            'phase.project.leaders:id,name,telegram_id',
        ])
        ->get();

    $pendingNotifications = 0;
    foreach ($pendingTasks as $task) {
        $leaders = $task->phase?->project?->leaders ?? collect();
        $telegramLeaders = $leaders->filter(function (User $leader): bool {
            return trim((string) $leader->telegram_id) !== '';
        });

        if ($telegramLeaders->isEmpty()) {
            continue;
        }

        $pendingHours = $task->updated_at?->diffInHours($now) ?? 0;
        foreach ($telegramLeaders as $leader) {
            try {
                Notification::send($leader, new TaskApprovalPendingReminderNotification($task, $pendingHours));
                $pendingNotifications++;
            } catch (\Throwable $exception) {
                report($exception);
            }
        }
    }

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

    $overdueNotifications = 0;
    foreach ($overdueGroups as $row) {
        $pic = $pics->firstWhere('id', (int) $row->pic_id);
        if ($pic === null || trim((string) $pic->telegram_id) === '') {
            continue;
        }

        try {
            Notification::send($pic, new PicOverdueTasksNotification($pic, (int) $row->total));
            $overdueNotifications++;
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    $this->info("Deadline reminders: {$deadlineNotifications}");
    $this->info("Pending approval reminders: {$pendingNotifications}");
    $this->info("Overdue PIC reminders: {$overdueNotifications}");
})->purpose('Gửi nhắc việc hàng ngày theo logic deadline và chờ duyệt');

Artisan::command('reports:weekly', function (): void {
    $now = now();
    $periodStart = $now->copy()->subWeek()->startOfDay();
    $periodEnd = $now->copy()->endOfDay();

    $summary = [
        'completed' => Task::query()
            ->where('status', TaskStatus::Completed->value)
            ->whereBetween('completed_at', [$periodStart, $periodEnd])
            ->count(),
        'late' => Task::query()
            ->where('status', TaskStatus::Late->value)
            ->whereNotNull('deadline')
            ->whereBetween('deadline', [$periodStart, $periodEnd])
            ->count(),
        'waiting_approval' => Task::query()
            ->where('status', TaskStatus::WaitingApproval->value)
            ->count(),
        'due_soon' => Task::query()
            ->whereNotNull('deadline')
            ->whereBetween('deadline', [$now->copy()->startOfDay(), $now->copy()->addDays(3)->endOfDay()])
            ->where('status', '!=', TaskStatus::Completed->value)
            ->count(),
    ];

    $recipients = User::role(['leader', 'ceo'])->get(['id', 'telegram_id', 'email']);
    $validRecipients = $recipients->filter(function (User $user): bool {
        return trim((string) $user->telegram_id) !== '' || trim((string) $user->email) !== '';
    });

    if ($validRecipients->isNotEmpty()) {
        foreach ($validRecipients as $recipient) {
            try {
                Notification::send($recipient, new WeeklySummaryNotification($summary, $periodStart, $periodEnd));
            } catch (\Throwable $exception) {
                report($exception);
            }
        }
    }

    $this->info('Weekly report sent.');
})->purpose('Gửi báo cáo task hàng tuần cho leader và ceo');

Artisan::command('kpi:monthly-sync', function (): void {
    $now = now();

    $users = User::query()
        ->where('status', UserStatus::Active->value)
        ->get(['id']);

    foreach ($users as $user) {
        KpiScore::syncForUser($user->id);
    }

    $recipients = User::role(['leader', 'ceo'])->get(['id', 'telegram_id']);
    $telegramRecipients = $recipients->filter(function (User $user): bool {
        return trim((string) $user->telegram_id) !== '';
    });

    if ($telegramRecipients->isNotEmpty()) {
        foreach ($telegramRecipients as $recipient) {
            try {
                Notification::send($recipient, new MonthlyKpiSummaryNotification($users->count(), $now));
            } catch (\Throwable $exception) {
                report($exception);
            }
        }
    }

    $this->info("Monthly KPI synced for {$users->count()} users.");
})->purpose('Đồng bộ KPI hàng tháng và gửi thông báo');

Artisan::command('kpi:daily-sync', function (): void {
    $users = User::query()
        ->where('status', UserStatus::Active->value)
        ->get(['id']);

    foreach ($users as $user) {
        KpiScore::syncForUser($user->id);
    }

    $this->info("Daily KPI synced for {$users->count()} users.");
})->purpose('Đồng bộ KPI hàng ngày để tránh sync đồng bộ khi lưu task');

Schedule::command('tasks:mark-late')->everyFiveMinutes();
Schedule::command('tasks:daily-reminders')->daily()->at('07:00');
Schedule::command('reports:weekly')->weekly()->fridays()->at('17:00');
Schedule::command('kpi:daily-sync')->daily()->at('01:00');
Schedule::command('kpi:monthly-sync')->lastDayOfMonth('23:59');
