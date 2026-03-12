<?php

use App\Enums\TaskStatus;
use App\Enums\TaskWorkflowType;
use App\Models\Phase;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskRejectedNotification;
use App\Services\Tasks\TaskApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();

    Role::firstOrCreate(['name' => 'leader']);

    $this->service = new TaskApprovalService;
    $this->project = Project::factory()->create();
    $this->phase = Phase::factory()->create(['project_id' => $this->project->id]);
    $this->leader = User::factory()->create();
    $this->leader->assignRole('leader');
});

it('sends telegram notification to pic when task is rejected', function () {
    $pic = User::factory()->create(['telegram_id' => '123456789']);
    $task = Task::factory()->create([
        'phase_id' => $this->phase->id,
        'pic_id' => $pic->id,
        'created_by' => $this->leader->id,
        'status' => TaskStatus::WaitingApproval,
        'workflow_type' => TaskWorkflowType::Single,
    ]);

    $this->service->reject($this->leader, $task, 'Khong dat');

    Notification::assertSentTo($pic, TaskRejectedNotification::class);
});

it('does not send telegram notification when pic has no telegram id', function () {
    $pic = User::factory()->create(['telegram_id' => null]);
    $task = Task::factory()->create([
        'phase_id' => $this->phase->id,
        'pic_id' => $pic->id,
        'created_by' => $this->leader->id,
        'status' => TaskStatus::WaitingApproval,
        'workflow_type' => TaskWorkflowType::Single,
    ]);

    $this->service->reject($this->leader, $task, 'Khong dat');

    Notification::assertNotSentTo($pic, TaskRejectedNotification::class);
});
