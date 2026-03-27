<?php

use App\Enums\TaskStatus;
use App\Models\Phase;
use App\Models\Project;
use App\Models\ProjectLeader;
use App\Models\Task;
use App\Models\User;
use App\Notifications\PicOverdueTasksNotification;
use App\Notifications\TaskApprovalPendingReminderNotification;
use App\Notifications\TaskDeadlineReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
});

it('sends daily reminders for deadlines, pending approvals, and overdue overload', function () {
    Carbon::setTestNow(Carbon::parse('2025-01-10 07:00:00'));

    $project = Project::factory()->create();
    $phase = Phase::factory()->create(['project_id' => $project->id]);

    $leader = User::factory()->create(['telegram_id' => 'leader-telegram']);
    ProjectLeader::factory()->create([
        'project_id' => $project->id,
        'user_id' => $leader->id,
        'assigned_by' => $leader->id,
    ]);

    $pic = User::factory()->create(['telegram_id' => 'pic-telegram']);

    Task::factory()->create([
        'phase_id' => $phase->id,
        'pic_id' => $pic->id,
        'status' => TaskStatus::InProgress,
        'deadline' => now()->addDays(2),
    ]);

    $pendingTask = Task::factory()->create([
        'phase_id' => $phase->id,
        'pic_id' => $pic->id,
        'status' => TaskStatus::WaitingApproval,
        'deadline' => now()->addDays(1),
    ]);
    Task::query()
        ->whereKey($pendingTask->id)
        ->update(['updated_at' => now()->subHours(25)]);
    $pendingTask->refresh();
    expect($pendingTask->status->value ?? $pendingTask->status)->toBe(TaskStatus::WaitingApproval->value);

    $recentPendingTask = Task::factory()->create([
        'phase_id' => $phase->id,
        'pic_id' => $pic->id,
        'status' => TaskStatus::WaitingApproval,
        'deadline' => now()->addDays(1),
    ]);
    Task::query()
        ->whereKey($recentPendingTask->id)
        ->update(['updated_at' => now()->subHours(3)]);
    $recentPendingTask->refresh();
    expect($recentPendingTask->status->value ?? $recentPendingTask->status)->toBe(TaskStatus::WaitingApproval->value);

    Task::factory()->count(4)->create([
        'phase_id' => $phase->id,
        'pic_id' => $pic->id,
        'status' => TaskStatus::InProgress,
        'deadline' => now()->subDays(1),
    ]);

    $pendingCutoff = now()->subHours(24);
    $pendingTasks = Task::query()
        ->where('status', TaskStatus::WaitingApproval->value)
        ->where('updated_at', '<=', $pendingCutoff)
        ->with([
            'pic:id,name',
            'phase.project.leaders:id,name,telegram_id',
        ])
        ->get();
    expect($pendingTasks)->not->toBeEmpty();
    expect($pendingTask->updated_at->lessThanOrEqualTo($pendingCutoff))->toBeTrue();
    expect($pendingTasks->first()?->phase?->project?->leaders)->not->toBeEmpty();
    expect($pendingTasks->first()?->phase?->project?->leaders?->first()?->telegram_id)->not->toBeEmpty();
    expect($pendingTasks->first()?->phase?->project?->leaders?->first()?->id)->toBe($leader->id);
    expect(Task::query()->where('status', TaskStatus::WaitingApproval->value)->count())->toBeGreaterThan(0);
    $telegramLeaders = $pendingTasks->first()?->phase?->project?->leaders?->filter(function (User $leader): bool {
        return trim((string) $leader->telegram_id) !== '';
    });
    expect($telegramLeaders)->not->toBeEmpty();

    $pendingNotifications = 0;
    foreach ($pendingTasks as $task) {
        $pendingHours = $task->updated_at?->diffInHours(now()) ?? 0;
        if ($pendingHours < 24) {
            continue;
        }

        $leaders = $task->phase?->project?->leaders ?? collect();
        $telegramLeaders = $leaders->filter(function (User $leader): bool {
            return trim((string) $leader->telegram_id) !== '';
        });

        foreach ($telegramLeaders as $leader) {
            $pendingNotifications++;
        }
    }
    expect($pendingNotifications)->toBe(1);

    Artisan::call('tasks:daily-reminders');
    expect(Artisan::output())->toContain('Pending approval reminders: 1');

    Notification::assertSentTo($pic, TaskDeadlineReminderNotification::class);
    Notification::assertSentTo($pic, PicOverdueTasksNotification::class);
    Notification::assertSentTo($leader, TaskApprovalPendingReminderNotification::class);
    Notification::assertSentToTimes($leader, TaskApprovalPendingReminderNotification::class, 1);
    Notification::assertNotSentTo(
        $leader,
        TaskApprovalPendingReminderNotification::class,
        fn (TaskApprovalPendingReminderNotification $notification): bool => $notification->task->id === $recentPendingTask->id
    );

    Carbon::setTestNow();
});
