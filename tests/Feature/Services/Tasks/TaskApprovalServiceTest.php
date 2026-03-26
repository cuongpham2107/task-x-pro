<?php

namespace Tests\Feature\Services\Tasks;

use App\Enums\ApprovalAction;
use App\Enums\ApprovalLevel;
use App\Enums\TaskStatus;
use App\Enums\TaskWorkflowType;
use App\Models\ApprovalLog;
use App\Models\Phase;
use App\Models\Project;
use App\Models\ProjectLeader;
use App\Models\Task;
use App\Models\User;
use App\Notifications\ApprovalResults;
use App\Services\Tasks\TaskApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TaskApprovalServiceTest extends TestCase
{
    use RefreshDatabase;

    protected TaskApprovalService $service;

    protected Project $project;

    protected Phase $phase;

    protected User $requester;

    protected User $leader;

    protected User $ceo;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->service = new TaskApprovalService;

        // Setup roles
        if (! Role::where('name', 'leader')->exists()) {
            Role::create(['name' => 'leader']);
        }
        if (! Role::where('name', 'ceo')->exists()) {
            Role::create(['name' => 'ceo']);
        }

        $this->project = Project::factory()->create();
        $this->phase = Phase::factory()->create(['project_id' => $this->project->id]);

        $this->requester = User::factory()->create();
        $this->leader = User::factory()->create();
        $this->leader->assignRole('leader');
        ProjectLeader::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $this->leader->id,
            'assigned_by' => $this->leader->id,
        ]);

        $this->ceo = User::factory()->create([
            'telegram_id' => '123456789',
        ]);
        $this->ceo->assignRole('ceo');
    }

    public function test_send_ceo_approval_notification_sends_notification_when_task_is_double_workflow_and_leader_approved(): void
    {
        $ceoWithoutTelegram = User::factory()->create([
            'telegram_id' => null,
        ]);
        $ceoWithoutTelegram->assignRole('ceo');

        $task = Task::factory()->create([
            'phase_id' => $this->phase->id,
            'pic_id' => $this->requester->id,
            'created_by' => $this->requester->id,
            'status' => TaskStatus::WaitingApproval,
            'workflow_type' => TaskWorkflowType::Double,
        ]);

        // Create leader approval log
        ApprovalLog::create([
            'task_id' => $task->id,
            'reviewer_id' => $this->leader->id,
            'approval_level' => ApprovalLevel::Leader->value,
            'action' => ApprovalAction::Approved->value,
            'created_at' => now(),
        ]);

        $this->service->sendCEOApprovalNotification($task, $this->leader);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->ceo->id,
            'type' => 'approval_request_ceo',
            'notifiable_type' => Task::class,
            'notifiable_id' => $task->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $ceoWithoutTelegram->id,
            'type' => 'approval_request_ceo',
            'notifiable_type' => Task::class,
            'notifiable_id' => $task->id,
            'status' => 'pending',
        ]);

        Notification::assertSentTo($this->ceo, ApprovalResults::class);
        Notification::assertNotSentTo($ceoWithoutTelegram, ApprovalResults::class);
    }

    public function test_send_ceo_approval_notification_does_not_send_notification_for_single_workflow(): void
    {
        $task = Task::factory()->create([
            'phase_id' => $this->phase->id,
            'pic_id' => $this->requester->id,
            'created_by' => $this->requester->id,
            'status' => TaskStatus::WaitingApproval,
            'workflow_type' => TaskWorkflowType::Single,
        ]);

        // Create leader approval log
        ApprovalLog::create([
            'task_id' => $task->id,
            'reviewer_id' => $this->leader->id,
            'approval_level' => ApprovalLevel::Leader->value,
            'action' => ApprovalAction::Approved->value,
            'created_at' => now(),
        ]);

        $this->service->sendCEOApprovalNotification($task, $this->leader);

        $this->assertDatabaseMissing('notifications', [
            'type' => 'approval_request_ceo',
            'notifiable_id' => $task->id,
        ]);

        Notification::assertNotSentTo($this->ceo, ApprovalResults::class);
    }

    public function test_send_ceo_approval_notification_does_not_send_notification_if_leader_has_not_approved(): void
    {
        $task = Task::factory()->create([
            'phase_id' => $this->phase->id,
            'pic_id' => $this->requester->id,
            'created_by' => $this->requester->id,
            'status' => TaskStatus::WaitingApproval,
            'workflow_type' => TaskWorkflowType::Double,
        ]);

        // No approval log created

        $this->service->sendCEOApprovalNotification($task, $this->leader);

        $this->assertDatabaseMissing('notifications', [
            'type' => 'approval_request_ceo',
            'notifiable_id' => $task->id,
        ]);

        Notification::assertNotSentTo($this->ceo, ApprovalResults::class);
    }

    public function test_approve_method_triggers_ceo_notification_for_double_workflow(): void
    {
        $task = Task::factory()->create([
            'phase_id' => $this->phase->id,
            'pic_id' => $this->requester->id,
            'created_by' => $this->requester->id,
            'status' => TaskStatus::WaitingApproval,
            'workflow_type' => TaskWorkflowType::Double,
        ]);

        $this->service->approve($this->leader, $task);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->ceo->id,
            'type' => 'approval_request_ceo',
            'notifiable_type' => Task::class,
            'notifiable_id' => $task->id,
        ]);

        Notification::assertSentTo($this->ceo, ApprovalResults::class);
    }
}
