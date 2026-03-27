<?php

use App\Enums\ApprovalAction;
use App\Enums\ApprovalLevel;
use App\Models\Phase;
use App\Models\Project;
use App\Models\ProjectLeader;
use App\Models\Task;
use App\Models\User;
use App\Services\Tasks\TaskApprovalService;
use App\Services\Tasks\TaskService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach ([
        'task.view',
        'task.update',
        'task.assign',
        'task.approve',
        'phase.update',
        'project.update',
    ] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    Role::findOrCreate('leader', 'web')->syncPermissions([
        'task.view',
        'task.update',
        'task.assign',
        'task.approve',
        'phase.update',
        'project.update',
    ]);

    Role::findOrCreate('pic', 'web')->syncPermissions([
        'task.view',
        'task.update',
    ]);

    Role::findOrCreate('ceo', 'web')->syncPermissions([
        'task.view',
        'task.update',
        'task.approve',
    ]);

    $this->taskService = app(TaskService::class);
    $this->approvalService = app(TaskApprovalService::class);

    $this->responsibleLeader = User::factory()->leader()->create();
    $this->responsibleLeader->assignRole('leader');

    $this->project = Project::factory()->create([
        'created_by' => $this->responsibleLeader->id,
    ]);

    ProjectLeader::factory()->create([
        'project_id' => $this->project->id,
        'user_id' => $this->responsibleLeader->id,
        'assigned_by' => $this->responsibleLeader->id,
    ]);

    $this->phase = Phase::factory()->create([
        'project_id' => $this->project->id,
    ]);

    $this->pic = User::factory()->pic()->create();
    $this->pic->assignRole('pic');

    $this->otherPic = User::factory()->pic()->create();
    $this->otherPic->assignRole('pic');
});

it('allows responsible leader to change pic before task is started', function () {
    $task = Task::factory()->create([
        'phase_id' => $this->phase->id,
        'status' => 'pending',
        'started_at' => null,
        'pic_id' => $this->pic->id,
        'created_by' => $this->responsibleLeader->id,
    ]);

    $this->taskService->update($this->responsibleLeader, $task, [
        'pic_id' => $this->otherPic->id,
    ]);

    expect($task->refresh()->pic_id)->toBe($this->otherPic->id);
});

it('prevents changing pic after task has been started', function () {
    $task = Task::factory()->create([
        'phase_id' => $this->phase->id,
        'status' => 'in_progress',
        'started_at' => now()->subHour(),
        'pic_id' => $this->pic->id,
        'created_by' => $this->responsibleLeader->id,
    ]);

    try {
        $this->taskService->update($this->responsibleLeader, $task, [
            'pic_id' => $this->otherPic->id,
        ]);

        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('pic_id');
    }
});

it('requires progress at least 90 percent before pic can submit for approval', function () {
    $task = Task::factory()->create([
        'phase_id' => $this->phase->id,
        'status' => 'in_progress',
        'progress' => 89,
        'started_at' => now()->subDay(),
        'pic_id' => $this->pic->id,
        'created_by' => $this->responsibleLeader->id,
    ]);

    try {
        $this->taskService->submitForApproval($this->pic, $task);

        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('progress');
    }
});

it('prevents ceo from updating task details', function () {
    $ceo = User::factory()->ceo()->create();
    $ceo->assignRole('ceo');

    $task = Task::factory()->create([
        'phase_id' => $this->phase->id,
        'status' => 'pending',
        'pic_id' => $this->pic->id,
        'created_by' => $this->responsibleLeader->id,
    ]);

    expect(function () use ($ceo, $task): void {
        $this->taskService->update($ceo, $task, [
            'deadline' => now()->addDays(3),
        ]);
    })->toThrow(AuthorizationException::class);
});

it('allows ceo to open task details in read-only mode', function () {
    $ceo = User::factory()->ceo()->create();
    $ceo->assignRole('ceo');

    $task = Task::factory()->create([
        'phase_id' => $this->phase->id,
        'status' => 'pending',
        'pic_id' => $this->pic->id,
        'created_by' => $this->responsibleLeader->id,
    ]);

    $loadedTask = $this->taskService->findForEdit($ceo, $task->id);

    expect($loadedTask->id)->toBe($task->id);
});

it('lets leader as pic approve first level, then ceo finalizes in double workflow', function () {
    $leaderPic = User::factory()->leader()->create();
    $leaderPic->assignRole('leader');

    $ceo = User::factory()->ceo()->create();
    $ceo->assignRole('ceo');

    $task = Task::factory()->create([
        'phase_id' => $this->phase->id,
        'status' => 'waiting_approval',
        'workflow_type' => 'double',
        'progress' => 100,
        'started_at' => now()->subDay(),
        'pic_id' => $leaderPic->id,
        'created_by' => $this->responsibleLeader->id,
    ]);

    $this->approvalService->approve($leaderPic, $task);

    expect($task->refresh()->status->value)->toBe('waiting_approval');

    $this->approvalService->approve($ceo, $task->refresh());

    expect($task->refresh()->status->value)->toBe('completed');
});

it('prevents final approval when task progress is below 90 percent', function () {
    $task = Task::factory()->create([
        'phase_id' => $this->phase->id,
        'status' => 'waiting_approval',
        'workflow_type' => 'single',
        'progress' => 89,
        'started_at' => now()->subDay(),
        'pic_id' => $this->pic->id,
        'created_by' => $this->responsibleLeader->id,
    ]);

    try {
        $this->approvalService->approve($this->responsibleLeader, $task);

        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('progress');
    }
});

it('does not create ceo approval log when final approval fails due to progress below 90', function () {
    $ceo = User::factory()->ceo()->create();
    $ceo->assignRole('ceo');

    $task = Task::factory()->create([
        'phase_id' => $this->phase->id,
        'status' => 'waiting_approval',
        'workflow_type' => 'double',
        'progress' => 89,
        'started_at' => now()->subDay(),
        'pic_id' => $this->pic->id,
        'created_by' => $this->responsibleLeader->id,
    ]);

    $this->approvalService->approve($this->responsibleLeader, $task);

    try {
        $this->approvalService->approve($ceo, $task->refresh());
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('progress');
    }

    expect($task->refresh()->status->value)->toBe('waiting_approval');
    expect($task->approvalLogs()
        ->where('reviewer_id', $ceo->id)
        ->where('approval_level', ApprovalLevel::Ceo->value)
        ->where('action', ApprovalAction::Approved->value)
        ->count())->toBe(0);
});
