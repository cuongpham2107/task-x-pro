<?php

namespace Tests\Feature;

use App\Enums\ApprovalAction;
use App\Enums\ApprovalLevel;
use App\Enums\TaskStatus;
use App\Enums\TaskWorkflowType;
use App\Models\ApprovalLog;
use App\Models\Phase;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\Dashboard\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardApprovalFilteringTest extends TestCase
{
    use RefreshDatabase;

    protected DashboardService $service;

    protected User $leader;

    protected User $ceo;

    protected User $admin;

    protected Project $project;

    protected Phase $phase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new DashboardService;

        // Setup roles
        if (! Role::where('name', 'leader')->exists()) {
            Role::create(['name' => 'leader']);
        }
        if (! Role::where('name', 'ceo')->exists()) {
            Role::create(['name' => 'ceo']);
        }
        if (! Role::where('name', 'super_admin')->exists()) {
            Role::create(['name' => 'super_admin']);
        }

        $this->leader = User::factory()->create();
        $this->leader->assignRole('leader');

        $this->ceo = User::factory()->create();
        $this->ceo->assignRole('ceo');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('super_admin');

        $this->project = Project::factory()->create();
        // Leader managing this project
        $this->project->projectLeaders()->create([
            'user_id' => $this->leader->id,
            'assigned_by' => $this->admin->id,
        ]);

        $this->phase = Phase::factory()->create(['project_id' => $this->project->id]);
    }

    public function test_leader_sees_waiting_approval_tasks_they_have_not_approved_yet(): void
    {
        $task = Task::factory()->create([
            'phase_id' => $this->phase->id,
            'status' => TaskStatus::WaitingApproval->value,
            'workflow_type' => TaskWorkflowType::Single->value,
        ]);

        $data = $this->service->getIndexData($this->leader);

        $this->assertTrue($data['approval_tasks']->contains('id', $task->id));
    }

    public function test_leader_does_not_see_double_workflow_tasks_they_already_approved(): void
    {
        $task = Task::factory()->create([
            'phase_id' => $this->phase->id,
            'status' => TaskStatus::WaitingApproval->value,
            'workflow_type' => TaskWorkflowType::Double->value,
        ]);

        // Leader approved
        ApprovalLog::create([
            'task_id' => $task->id,
            'reviewer_id' => $this->leader->id,
            'approval_level' => ApprovalLevel::Leader->value,
            'action' => ApprovalAction::Approved->value,
        ]);

        $data = $this->service->getIndexData($this->leader);

        $this->assertFalse($data['approval_tasks']->contains('id', $task->id));
    }

    public function test_ceo_sees_double_workflow_tasks_approved_by_leader(): void
    {
        $task = Task::factory()->create([
            'phase_id' => $this->phase->id,
            'status' => TaskStatus::WaitingApproval->value,
            'workflow_type' => TaskWorkflowType::Double->value,
        ]);

        // Leader approved
        ApprovalLog::create([
            'task_id' => $task->id,
            'reviewer_id' => $this->leader->id,
            'approval_level' => ApprovalLevel::Leader->value,
            'action' => ApprovalAction::Approved->value,
        ]);

        $data = $this->service->getIndexData($this->ceo);

        $this->assertTrue($data['approval_tasks']->contains('id', $task->id));
    }

    public function test_ceo_does_not_see_double_workflow_tasks_not_yet_approved_by_leader(): void
    {
        $task = Task::factory()->create([
            'phase_id' => $this->phase->id,
            'status' => TaskStatus::WaitingApproval->value,
            'workflow_type' => TaskWorkflowType::Double->value,
        ]);

        $data = $this->service->getIndexData($this->ceo);

        $this->assertFalse($data['approval_tasks']->contains('id', $task->id));
    }

    public function test_ceo_does_not_see_tasks_they_already_approved(): void
    {
        $task = Task::factory()->create([
            'phase_id' => $this->phase->id,
            'status' => TaskStatus::WaitingApproval->value,
            'workflow_type' => TaskWorkflowType::Double->value,
        ]);

        // Leader approved
        ApprovalLog::create([
            'task_id' => $task->id,
            'reviewer_id' => $this->leader->id,
            'approval_level' => ApprovalLevel::Leader->value,
            'action' => ApprovalAction::Approved->value,
        ]);

        // CEO approved (Wait, usually CEO approval completes the task, but let's test the filter logic if status was still WaitingApproval)
        ApprovalLog::create([
            'task_id' => $task->id,
            'reviewer_id' => $this->ceo->id,
            'approval_level' => ApprovalLevel::Ceo->value,
            'action' => ApprovalAction::Approved->value,
        ]);

        $data = $this->service->getIndexData($this->ceo);

        $this->assertFalse($data['approval_tasks']->contains('id', $task->id));
    }

    public function test_super_admin_sees_all_waiting_approval_tasks(): void
    {
        $task1 = Task::factory()->create([
            'phase_id' => $this->phase->id,
            'status' => TaskStatus::WaitingApproval->value,
            'workflow_type' => TaskWorkflowType::Single->value,
        ]);

        $task2 = Task::factory()->create([
            'phase_id' => $this->phase->id,
            'status' => TaskStatus::WaitingApproval->value,
            'workflow_type' => TaskWorkflowType::Double->value,
        ]);

        $data = $this->service->getIndexData($this->admin);

        $this->assertTrue($data['approval_tasks']->contains('id', $task1->id));
        $this->assertTrue($data['approval_tasks']->contains('id', $task2->id));
    }

    public function test_regular_user_sees_empty_approval_list(): void
    {
        $user = User::factory()->create();

        Task::factory()->create([
            'phase_id' => $this->phase->id,
            'status' => TaskStatus::WaitingApproval->value,
            'pic_id' => $user->id,
        ]);

        $data = $this->service->getIndexData($user);

        $this->assertEmpty($data['approval_tasks']);
    }
}
