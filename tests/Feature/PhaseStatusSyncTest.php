<?php

use App\Models\Phase;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\Tasks\TaskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    Permission::findOrCreate('task.view', 'web');
    Permission::findOrCreate('task.update', 'web');
    Role::findOrCreate('pic', 'web')->syncPermissions(['task.view', 'task.update']);
});

it('transitions phase status to active when a task starts even with zero progress', function () {
    // 1. Setup
    $user = User::factory()->create();
    $project = Project::factory()->create(['created_by' => $user->id]);
    $phase = Phase::factory()->create([
        'project_id' => $project->id,
        'status' => 'pending',
        'progress' => 0,
    ]);

    $pic = User::factory()->create();
    $pic->assignRole('pic');
    
    $task = Task::factory()->create([
        'phase_id' => $phase->id,
        'pic_id' => $pic->id,
        'status' => 'pending',
        'progress' => 0,
        'started_at' => null,
    ]);

    // Initial check
    $phase->refreshProgressFromTasks();
    expect($phase->refresh()->status)->toBe('pending');

    // 2. Start the task via TaskService
    $taskService = app(TaskService::class);
    $taskService->start($pic, $task);

    // 3. Verify
    // Task status should be in_progress
    expect($task->refresh()->status->value)->toBe('in_progress');

    // Phase status should now be active (because task started), even though progress is still 0
    expect($phase->refresh()->status)->toBe('active');
});

it('maintains phase status as active when adding a new zero progress task to an active phase', function () {
    // 1. Setup
    $user = User::factory()->create();
    $project = Project::factory()->create(['created_by' => $user->id]);
    $phase = Phase::factory()->create([
        'project_id' => $project->id,
        'status' => 'active',
        'progress' => 10,
    ]);

    $pic = User::factory()->create();
    $pic->assignRole('pic');

    $task1 = Task::factory()->create([
        'phase_id' => $phase->id,
        'pic_id' => $pic->id,
        'status' => 'in_progress',
        'progress' => 10,
    ]);

    // Verify initial state
    $phase->refreshProgressFromTasks();
    expect($phase->refresh()->status)->toBe('active');

    // 2. Add a new pending task (0% progress)
    $task2 = Task::factory()->create([
        'phase_id' => $phase->id,
        'pic_id' => $pic->id,
        'status' => 'pending',
        'progress' => 0,
    ]);

    // 3. Verify phase status is STILL active because of task1
    $phase->refreshProgressFromTasks();
    expect($phase->refresh()->status)->toBe('active');
});
