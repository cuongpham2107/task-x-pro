<?php

use App\Enums\TaskStatus;
use App\Enums\TaskWorkflowType;
use App\Models\Phase;
use App\Models\Project;
use App\Models\ProjectLeader;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskApprovalRequestLeaderNotification;
use App\Services\Tasks\TaskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('task.update', 'web');

    $this->service = app(TaskService::class);

    $this->project = Project::factory()->create();
    $this->phase = Phase::factory()->create(['project_id' => $this->project->id]);

    $this->pic = User::factory()->create();
    $this->pic->givePermissionTo('task.update');
});

it('sends telegram notification to leaders when task is submitted for approval', function () {
    $leaderWithTelegram = User::factory()->create(['telegram_id' => '123456789']);
    $leaderWithoutTelegram = User::factory()->create(['telegram_id' => null]);

    ProjectLeader::factory()->create([
        'project_id' => $this->project->id,
        'user_id' => $leaderWithTelegram->id,
        'assigned_by' => $this->pic->id,
    ]);

    ProjectLeader::factory()->create([
        'project_id' => $this->project->id,
        'user_id' => $leaderWithoutTelegram->id,
        'assigned_by' => $this->pic->id,
    ]);

    $task = Task::factory()->create([
        'phase_id' => $this->phase->id,
        'pic_id' => $this->pic->id,
        'created_by' => $this->pic->id,
        'status' => TaskStatus::InProgress,
        'workflow_type' => TaskWorkflowType::Single,
    ]);

    $this->service->submitForApproval($this->pic, $task);

    Notification::assertSentTo($leaderWithTelegram, TaskApprovalRequestLeaderNotification::class);
    Notification::assertNotSentTo($leaderWithoutTelegram, TaskApprovalRequestLeaderNotification::class);
});
