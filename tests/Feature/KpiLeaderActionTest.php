<?php

use App\Models\ApprovalLog;
use App\Models\Department;
use App\Models\KpiScore;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    Permission::findOrCreate('kpi.view', 'web');
    Permission::findOrCreate('kpi.manage', 'web');

    Role::findOrCreate('leader', 'web')->syncPermissions(['kpi.view']);
    Role::findOrCreate('ceo', 'web')->syncPermissions(['kpi.view', 'kpi.manage']);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('allows leader to approve pending kpi score in same department', function (): void {
    $department = Department::factory()->create();
    $leader = User::factory()->leader()->create(['department_id' => $department->id]);
    $leader->assignRole('leader');

    $pic = User::factory()->pic()->create(['department_id' => $department->id]);
    $score = KpiScore::factory()->create([
        'user_id' => $pic->id,
        'status' => 'pending',
        'approved_at' => null,
    ]);

    Livewire::actingAs($leader)
        ->test('kpi.leader')
        ->call('approveScore', $score->id)
        ->assertHasNoErrors();

    $score->refresh();

    expect($score->status)->toBe('approved')
        ->and($score->approved_at)->not->toBeNull();
});

it('allows leader to reject pending kpi score in same department', function (): void {
    $department = Department::factory()->create();
    $leader = User::factory()->leader()->create(['department_id' => $department->id]);
    $leader->assignRole('leader');

    $pic = User::factory()->pic()->create(['department_id' => $department->id]);
    $score = KpiScore::factory()->create([
        'user_id' => $pic->id,
        'status' => 'pending',
        'approved_at' => now(),
    ]);

    Livewire::actingAs($leader)
        ->test('kpi.leader')
        ->call('rejectScore', $score->id)
        ->assertHasNoErrors();

    $score->refresh();

    expect($score->status)->toBe('rejected')
        ->and($score->approved_at)->toBeNull();
});

it('prevents leader from approving kpi score outside own department', function (): void {
    $leaderDepartment = Department::factory()->create();
    $otherDepartment = Department::factory()->create();

    $leader = User::factory()->leader()->create(['department_id' => $leaderDepartment->id]);
    $leader->assignRole('leader');

    $otherPic = User::factory()->pic()->create(['department_id' => $otherDepartment->id]);
    $score = KpiScore::factory()->create([
        'user_id' => $otherPic->id,
        'status' => 'pending',
    ]);

    Livewire::actingAs($leader)
        ->test('kpi.leader')
        ->call('approveScore', $score->id)
        ->assertForbidden();

    expect($score->fresh()?->status)->toBe('pending');
});

it('shows task review modal with in-period tasks before approval', function (): void {
    $department = Department::factory()->create();
    $leader = User::factory()->leader()->create(['department_id' => $department->id]);
    $leader->assignRole('leader');

    $pic = User::factory()->pic()->create(['department_id' => $department->id]);
    $score = KpiScore::factory()->create([
        'user_id' => $pic->id,
        'period_type' => 'monthly',
        'period_year' => (int) now()->format('Y'),
        'period_value' => (int) now()->format('n'),
        'status' => 'pending',
    ]);

    $taskInPeriod = Task::factory()->create([
        'name' => 'Task trong ky KPI',
        'pic_id' => $pic->id,
        'status' => 'completed',
        'progress' => 100,
        'started_at' => now()->startOfMonth()->addDays(2),
        'completed_at' => now()->startOfMonth()->addDays(8),
        'deadline' => now()->startOfMonth()->addDays(10),
        'sla_met' => true,
    ]);

    ApprovalLog::factory()->create([
        'task_id' => $taskInPeriod->id,
        'reviewer_id' => $leader->id,
        'approval_level' => 'leader',
        'action' => 'approved',
    ]);

    $taskWaiting = Task::factory()->create([
        'name' => 'Task cho duyet',
        'pic_id' => $pic->id,
        'status' => 'completed',
        'progress' => 100,
        'started_at' => now()->startOfMonth()->addDays(3),
        'completed_at' => now()->startOfMonth()->addDays(9),
        'deadline' => now()->startOfMonth()->addDays(12),
        'sla_met' => true,
    ]);

    ApprovalLog::factory()->create([
        'task_id' => $taskWaiting->id,
        'reviewer_id' => $leader->id,
        'approval_level' => 'leader',
        'action' => 'submitted',
    ]);

    $taskOutPeriod = Task::factory()->create([
        'name' => 'Task ngoai ky KPI',
        'pic_id' => $pic->id,
        'status' => 'completed',
        'progress' => 100,
        'started_at' => now()->subMonths(2)->startOfMonth()->addDays(1),
        'completed_at' => now()->subMonths(2)->startOfMonth()->addDays(3),
        'deadline' => now()->subMonths(2)->startOfMonth()->addDays(5),
        'sla_met' => true,
    ]);

    Livewire::actingAs($leader)
        ->test('kpi.leader')
        ->call('openTaskReview', $score->id)
        ->assertSet('showTaskReviewModal', true)
        ->assertSee($taskInPeriod->name)
        ->assertSee($taskWaiting->name)
        ->assertDontSee($taskOutPeriod->name)
        ->set('reviewApprovalFilter', 'approved')
        ->assertSee($taskInPeriod->name)
        ->assertDontSee($taskWaiting->name);
});

it('closes review modal after approving from review flow', function (): void {
    $department = Department::factory()->create();
    $leader = User::factory()->leader()->create(['department_id' => $department->id]);
    $leader->assignRole('leader');

    $pic = User::factory()->pic()->create(['department_id' => $department->id]);
    $score = KpiScore::factory()->create([
        'user_id' => $pic->id,
        'status' => 'pending',
    ]);

    Livewire::actingAs($leader)
        ->test('kpi.leader')
        ->call('openTaskReview', $score->id)
        ->assertSet('showTaskReviewModal', true)
        ->call('approveScore', $score->id)
        ->assertSet('showTaskReviewModal', false)
        ->assertHasNoErrors();

    expect($score->fresh()?->status)->toBe('approved');
});
