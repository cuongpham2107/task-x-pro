<?php

use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Models\Phase;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\CeoWeeklyReportNotification;
use App\Notifications\LeaderWeeklyReportNotification;
use App\Notifications\PicDailySummaryNotification;
use App\Notifications\PicOverdueTasksNotification;
use App\Notifications\PicWeeklyReportNotification;
use App\Notifications\ProjectOverdueNotification;
use App\Notifications\TaskApprovalPendingReminderNotification;
use App\Notifications\TaskDeadlineReminderNotification;
use App\Services\Projects\ProjectPhaseService;
use App\Services\Tasks\TaskService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schedule;

Artisan::command('tasks:mark-late', function (TaskService $taskService): void {
    $affectedTasks = $taskService->markLateTasks();

    $this->info("Đã cập nhập {$affectedTasks} công việc sang trạng thái trễ hạn.");
})->purpose('Cập công việc trễ hạn theo deadline');

Artisan::command('projects:mark-overdue', function (ProjectPhaseService $phaseService): void {
    $projects = Project::query()
        ->whereIn('status', [ProjectStatus::Init, ProjectStatus::Running, ProjectStatus::Paused])
        ->whereNotNull('end_date')
        ->where('end_date', '<', now()->startOfDay())
        ->get();

    foreach ($projects as $project) {
        $project->update(['status' => ProjectStatus::Overdue]);
        $phaseService->syncPhaseStatusesWithProjectStatus($project);

        if ($project->relationLoaded('leaders')) {
            $leaders = $project->leaders;
        } else {
            $leaders = $project->leaders()->get();
        }

        $leaders = $leaders->filter(fn (User $user) => filled($user->telegram_id));

        Notification::send($leaders, new ProjectOverdueNotification($project));
    }

    $this->info('Đã chuyển '.$projects->count().' dự án quá hạn sang trạng thái Quá hạn (Overdue) và đồng bộ Phase.');
})->purpose('Chuyển dự án quá hạn sang trạng thái Quá hạn');

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
        $dueTodayNotCompleted = $pic->picTasks->filter(fn ($t) => $t->deadline?->isToday() && $t->status !== TaskStatus::Completed->value)->count();

        if ($todayCount === 0 && $overdueCount === 0) {
            continue;
        }

        try {
            Notification::send($pic, new PicDailySummaryNotification($todayCount, $overdueCount, $dueTodayNotCompleted));
            $sent++;
        } catch (\Throwable $e) {
            report($e);
        }
    }

    $this->info("Daily summary sent to {$sent} PICs.");
})->purpose('Gửi thông báo sáng cho PIC về task hôm nay và task quá hạn (SRS 2.4)');

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

Artisan::command('reports:weekly-pic', function (): void {
    $now = now();
    $weekStart = $now->copy()->startOfWeek(Carbon::MONDAY);
    $weekEnd = $now->copy()->startOfWeek(Carbon::MONDAY)->addDays(5);

    $pics = User::query()
        ->whereHas('picTasks', function ($q): void {
            $q->where('progress', 100);
        })
        ->with(['picTasks' => function ($q): void {
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

        $approved = $tasks->filter(fn ($t) => $t->status === TaskStatus::Completed->value
            && $t->completed_at !== null
            && $t->completed_at->between($weekStart, $weekEnd)
        )->count();

        $rejected = $tasks->filter(fn ($t) => $t->status === TaskStatus::InProgress->value
            && $t->approvalLogs()->where('action', 'rejected')->whereBetween('created_at', [$weekStart, $weekEnd])->exists()
        )->count();

        $pending = $tasks->filter(fn ($t) => $t->status === TaskStatus::WaitingApproval->value
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
            $progress = (int) ($project->progress ?? 0);
            $deadline = $project->end_date;
            $status = classifyProjectStatus($project, $deadline, $progress, $now);

            $projectData[] = [
                'name' => $project->name,
                'progress' => $progress,
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

if (! function_exists('classifyProjectStatus')) {
    function classifyProjectStatus(Project $project, ?Carbon $deadline, int $progress, Carbon $now): string
    {
        if ($deadline === null) {
            return 'Đúng tiến độ';
        }

        if ($deadline->isPast()) {
            return 'Trễ hạn';
        }

        $startDate = $project->start_date ?? $project->created_at;
        $totalDuration = $startDate->diffInDays($deadline);
        $elapsed = $startDate->diffInDays($now);
        $twoThirdsPoint = $totalDuration > 0 ? $totalDuration * 2 / 3 : 0;

        if ($elapsed >= $twoThirdsPoint && $progress < 60) {
            return 'Rủi ro';
        }

        return 'Đúng tiến độ';
    }
}

Artisan::command('kpi:daily-sync', function (): void {
    $users = User::query()
        ->where('status', \App\Enums\UserStatus::Active->value)
        ->get(['id']);

    foreach ($users as $user) {
        \App\Models\KpiScore::syncForUser($user->id);
    }

    $this->info("Daily KPI synced for {$users->count()} users.");
})->purpose('Đồng bộ KPI hàng ngày');

Artisan::command('kpi:monthly-sync', function (): void {
    $now = now();

    $users = User::query()
        ->where('status', \App\Enums\UserStatus::Active->value)
        ->get(['id']);

    foreach ($users as $user) {
        \App\Models\KpiScore::syncForUser($user->id);
    }

    $recipients = User::role(['leader', 'ceo'])->get(['id', 'telegram_id']);
    $telegramRecipients = $recipients->filter(function (User $user): bool {
        return trim((string) $user->telegram_id) !== '';
    });

    if ($telegramRecipients->isNotEmpty()) {
        foreach ($telegramRecipients as $recipient) {
            try {
                Notification::send($recipient, new \App\Notifications\MonthlyKpiSummaryNotification($users->count(), $now));
            } catch (\Throwable $exception) {
                report($exception);
            }
        }
    }

    $this->info("Monthly KPI synced for {$users->count()} users.");
})->purpose('Đồng bộ KPI hàng tháng và gửi thông báo');

Artisan::command('reports:weekly-ceo', function (): void {
    $now = now();
    $weekStart = $now->copy()->startOfWeek(Carbon::MONDAY);
    $weekEnd = $now->copy()->startOfWeek(Carbon::MONDAY)->addDays(5);

    $allProjects = Project::query()->get();
    $totalActive = $allProjects->count();

    $completed = $allProjects->filter(fn ($p) => $p->status === ProjectStatus::Completed->value
        && $p->updated_at !== null
        && $p->updated_at->between($weekStart, $weekEnd)
    );

    $inProgress = $allProjects->filter(fn ($p) => ! in_array($p->status, [
        ProjectStatus::Completed->value,
        ProjectStatus::Cancelled->value,
        ProjectStatus::Overdue->value,
    ])
        && ! ($p->end_date !== null && $p->end_date->isPast())
    );

    $atRisk = $allProjects->filter(fn ($p) => $p->end_date !== null
        && ! $p->end_date->isPast()
        && $p->end_date->diffInDays($now) <= 15
        && ($p->progress ?? 0) < 60
    );

    $overdue = $allProjects->filter(fn ($p) => $p->status === ProjectStatus::Overdue->value
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

    $atRiskData = $atRisk->filter(fn ($p) => $p->end_date !== null)->map(fn ($p) => [
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

Schedule::command('tasks:mark-late')->daily()->at('07:00');
Schedule::command('projects:mark-overdue')->daily()->at('07:00');
Schedule::command('tasks:daily-summary')->daily()->at('08:30');
Schedule::command('tasks:deadline-reminders')->daily()->at('08:30');
Schedule::command('tasks:pending-approval-reminder')->daily()->at('17:00');
Schedule::command('tasks:pic-overdue-warning')->daily()->at('17:00');
Schedule::command('reports:weekly-pic')->weekly()->saturdays()->at('08:00');
Schedule::command('reports:weekly-leader')->weekly()->saturdays()->at('08:00');
Schedule::command('reports:weekly-ceo')->weekly()->saturdays()->at('08:00');
Schedule::command('kpi:daily-sync')
    ->daily()
    ->at('01:00')
    ->withoutOverlapping()
    ->onOneServer();
Schedule::command('kpi:monthly-sync')
    ->monthlyOn(1, '02:00')
    ->withoutOverlapping()
    ->onOneServer();
Schedule::command('kpi:monthly-sync')
    ->monthlyOn(2, '03:00')
    ->withoutOverlapping()
    ->onOneServer();
Schedule::command('kpi:backfill-missing-months', ['--months' => '12'])
    ->monthlyOn(1, '04:00')
    ->withoutOverlapping()
    ->onOneServer();

Artisan::command('progress:refresh-all', function (): void {
    $phases = Phase::query()->get();
    $this->info("Refreshing progress for {$phases->count()} phases...");

    foreach ($phases as $phase) {
        $phase->refreshProgressFromTasks();
    }

    $projects = Project::query()->get();
    $this->info("Refreshing progress for {$projects->count()} projects...");

    foreach ($projects as $project) {
        $project->refreshProgressFromPhases();
    }

    $this->info('Progress refreshed for all phases and projects.');
})->purpose('Đồng bộ lại % tiến độ của tất cả Phase và Project từ dữ liệu Task');

Artisan::command('projects:set-creator-admin', function (): void {
    $email = 'admin@admin.com';

    $user = User::query()->where('email', $email)->first();
    if ($user === null) {
        $this->error("User with email {$email} not found.");

        return;
    }

    $total = Project::query()->count();

    if ($total === 0) {
        $this->info('No projects found to update.');

        return;
    }

    Project::query()->update(['created_by' => $user->id]);

    $this->info("Updated {$total} projects: set created_by = {$user->id} ({$user->email}).");
})->purpose('Set created_by for all projects to admin@admin.com');
