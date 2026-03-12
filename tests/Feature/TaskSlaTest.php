<?php

use App\Enums\SlaProjectType;
use App\Enums\SlaTaskType;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Phase;
use App\Models\Project;
use App\Models\SlaConfig;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('sla_met is calculated correctly when task is completed within SLA', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->create(['type' => SlaProjectType::Software->value]);
    $phase = Phase::factory()->create(['project_id' => $project->id]);

    // Create SLA configuration
    SlaConfig::factory()->create([
        'task_type' => SlaTaskType::Technical->value,
        'project_type' => SlaProjectType::Software->value,
        'department_id' => $user->department_id,
        'standard_hours' => 8.0,
        'effective_date' => now()->subDay(),
    ]);

    $deadline = now()->addHours(2);
    $task = Task::factory()->create([
        'phase_id' => $phase->id,
        'pic_id' => $user->id,
        'type' => TaskType::Technical,
        'status' => TaskStatus::InProgress,
        'started_at' => now()->subHours(6),
        'sla_standard_hours' => 8.0,
        'deadline' => $deadline,
    ]);

    // Complete task within SLA (6 hours < 8 hours)
    // Completed 2 hours before deadline
    $completedAt = now();
    $task->update([
        'status' => TaskStatus::Completed,
        'completed_at' => $completedAt,
    ]);

    // Expected delay_days: 2 hours / 24 = 0.0833 -> 0.08
    $expectedDelayDays = round($completedAt->diffInSeconds($deadline, false) / 86400, 2);

    expect($task->fresh()->sla_met)->toBeTrue()
        ->and((float) $task->fresh()->delay_days)->toBe($expectedDelayDays);
});

test('sla_met is false when task exceeds SLA hours', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->create(['type' => SlaProjectType::Software->value]);
    $phase = Phase::factory()->create(['project_id' => $project->id]);

    // Create SLA configuration
    SlaConfig::factory()->create([
        'task_type' => SlaTaskType::Technical->value,
        'project_type' => SlaProjectType::Software->value,
        'department_id' => $user->department_id,
        'standard_hours' => 8.0,
        'effective_date' => now()->subDay(),
    ]);

    $task = Task::factory()->create([
        'phase_id' => $phase->id,
        'pic_id' => $user->id,
        'type' => TaskType::Technical,
        'status' => TaskStatus::InProgress,
        'started_at' => now()->subHours(10),
        'sla_standard_hours' => 8.0,
        'deadline' => now()->subHours(2),
    ]);

    // Complete task exceeding SLA (10 hours > 8 hours)
    $task->update([
        'status' => TaskStatus::Completed,
        'completed_at' => now(),
    ]);

    expect($task->fresh()->sla_met)->toBeFalse();
});

test('delay_days is calculated correctly when task exceeds deadline', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->create(['type' => SlaProjectType::Software->value]);
    $phase = Phase::factory()->create(['project_id' => $project->id]);

    $deadline = now()->subDays(2);
    $task = Task::factory()->create([
        'phase_id' => $phase->id,
        'pic_id' => $user->id,
        'type' => TaskType::Technical,
        'status' => TaskStatus::InProgress,
        'deadline' => $deadline,
        'started_at' => now()->subDays(3),
    ]);

    // Complete task 2 days after deadline
    // Completed - Deadline = 2 days late
    // Formula: Deadline - Completed = -2 days
    $completedAt = now();
    $task->update([
        'status' => TaskStatus::Completed,
        'completed_at' => $completedAt,
    ]);

    // Expect -2.0 because completed after deadline
    expect((float) $task->fresh()->delay_days)->toBe(-2.0);
});

test('delay_days is positive when task completed before deadline', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->create(['type' => SlaProjectType::Software->value]);
    $phase = Phase::factory()->create(['project_id' => $project->id]);

    $deadline = now()->addDays(5);
    $task = Task::factory()->create([
        'phase_id' => $phase->id,
        'pic_id' => $user->id,
        'type' => TaskType::Technical,
        'status' => TaskStatus::InProgress,
        'deadline' => $deadline,
        'started_at' => now()->subDays(1),
    ]);

    // Complete task 5 days before deadline
    $completedAt = now();
    $task->update([
        'status' => TaskStatus::Completed,
        'completed_at' => $completedAt,
    ]);

    // Expect 5.0 because completed before deadline
    expect((float) $task->fresh()->delay_days)->toBe(5.0);
});

test('sla fields are reset when task is un-completed', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->create(['type' => SlaProjectType::Software->value]);
    $phase = Phase::factory()->create(['project_id' => $project->id]);

    $task = Task::factory()->create([
        'phase_id' => $phase->id,
        'pic_id' => $user->id,
        'type' => TaskType::Technical,
        'status' => TaskStatus::Completed,
        'sla_met' => true,
        'delay_days' => 1.5,
        'completed_at' => now(),
    ]);

    // Reopen task
    $task->update([
        'status' => TaskStatus::InProgress,
    ]);

    expect($task->fresh()->sla_met)->toBeNull()
        ->and((float) $task->fresh()->delay_days)->toBe(0.0)
        ->and($task->fresh()->completed_at)->toBeNull();
});

test('sla calculation handles missing started_at gracefully', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->create(['type' => SlaProjectType::Software->value]);
    $phase = Phase::factory()->create(['project_id' => $project->id]);

    $task = Task::factory()->create([
        'phase_id' => $phase->id,
        'pic_id' => $user->id,
        'type' => TaskType::Technical,
        'status' => TaskStatus::InProgress,
        'started_at' => null,
        'sla_standard_hours' => 8.0,
    ]);

    // Complete task without started_at
    $task->update([
        'status' => TaskStatus::Completed,
        'completed_at' => now(),
    ]);

    // Should use completed_at as fallback for started_at
    expect($task->fresh()->sla_met)->toBeTrue(); // 0 hours < 8 hours
});

test('progress is set to 100 when task is completed', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->create(['type' => SlaProjectType::Software->value]);
    $phase = Phase::factory()->create(['project_id' => $project->id]);

    $task = Task::factory()->create([
        'phase_id' => $phase->id,
        'pic_id' => $user->id,
        'type' => TaskType::Technical,
        'status' => TaskStatus::InProgress,
        'progress' => 75,
    ]);

    $task->update([
        'status' => TaskStatus::Completed,
    ]);

    expect($task->fresh()->progress)->toBe(100);
});

test('sla fields are not updated for non-completed status changes', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->create(['type' => SlaProjectType::Software->value]);
    $phase = Phase::factory()->create(['project_id' => $project->id]);

    $task = Task::factory()->create([
        'phase_id' => $phase->id,
        'pic_id' => $user->id,
        'type' => TaskType::Technical,
        'status' => TaskStatus::Pending,
        'sla_met' => null,
        'delay_days' => 0,
    ]);

    // Change to in progress
    $task->update([
        'status' => TaskStatus::InProgress,
    ]);

    expect($task->fresh()->sla_met)->toBeNull()
        ->and((float) $task->fresh()->delay_days)->toBe(0.0);
});
