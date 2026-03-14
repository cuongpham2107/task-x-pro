<?php

use App\Enums\TaskStatus;
use App\Models\Phase;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskDeadlineReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use NotificationChannels\Telegram\TelegramChannel;

uses(RefreshDatabase::class);

it('prefers mail when telegram id is missing for deadline reminders', function () {
    $project = Project::factory()->create();
    $phase = Phase::factory()->create(['project_id' => $project->id]);
    $task = Task::factory()->create([
        'phase_id' => $phase->id,
        'status' => TaskStatus::InProgress,
        'deadline' => now()->addDays(2),
    ]);

    $user = User::factory()->create([
        'telegram_id' => null,
        'email' => 'pic@example.com',
    ]);

    $notification = new TaskDeadlineReminderNotification($task, 2);

    $channels = $notification->via($user);

    expect($channels)->toContain('mail')
        ->and($channels)->not->toContain(TelegramChannel::class);
});
