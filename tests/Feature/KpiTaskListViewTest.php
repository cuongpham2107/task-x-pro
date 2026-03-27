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

    Role::findOrCreate('pic', 'web')->syncPermissions(['kpi.view']);
    Role::findOrCreate('leader', 'web')->syncPermissions(['kpi.view']);
    Role::findOrCreate('ceo', 'web')->syncPermissions(['kpi.view', 'kpi.manage']);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('shows task approval list on pic kpi page', function (): void {
    $pic = User::factory()->pic()->create();
    $pic->assignRole('pic');

    KpiScore::factory()->create([
        'user_id' => $pic->id,
        'period_type' => 'monthly',
        'period_year' => (int) now()->format('Y'),
        'period_value' => (int) now()->format('n'),
        'status' => 'approved',
    ]);

    $leader = User::factory()->leader()->create();

    $taskInPeriod = Task::factory()->create([
        'name' => 'Task KPI PIC',
        'pic_id' => $pic->id,
        'status' => 'completed',
        'progress' => 100,
        'started_at' => now()->startOfMonth()->addDays(1),
        'completed_at' => now()->startOfMonth()->addDays(3),
        'deadline' => now()->startOfMonth()->addDays(5),
        'sla_met' => true,
    ]);

    ApprovalLog::factory()->create([
        'task_id' => $taskInPeriod->id,
        'reviewer_id' => $leader->id,
        'approval_level' => 'leader',
        'action' => 'approved',
        'star_rating' => 5,
    ]);

    $taskOutPeriod = Task::factory()->create([
        'name' => 'Task Out PIC',
        'pic_id' => $pic->id,
        'status' => 'completed',
        'progress' => 100,
        'started_at' => now()->subMonths(2)->startOfMonth()->addDay(),
        'completed_at' => now()->subMonths(2)->startOfMonth()->addDays(2),
        'deadline' => now()->subMonths(2)->startOfMonth()->addDays(4),
        'sla_met' => true,
    ]);

    Livewire::actingAs($pic)
        ->test('kpi.pic')
        ->assertSee($taskInPeriod->name)
        ->assertSee($leader->name)
        ->assertDontSee($taskOutPeriod->name);
});

it('shows task kpi and approver data on ceo kpi page', function (): void {
    $department = Department::factory()->create();
    $ceo = User::factory()->ceo()->create();
    $ceo->assignRole('ceo');

    $pic = User::factory()->pic()->create(['department_id' => $department->id]);
    $leader = User::factory()->leader()->create(['department_id' => $department->id]);

    KpiScore::factory()->create([
        'user_id' => $pic->id,
        'period_type' => 'monthly',
        'period_year' => (int) now()->format('Y'),
        'period_value' => (int) now()->format('n'),
        'total_tasks' => 10,
        'on_time_tasks' => 9,
        'sla_met_tasks' => 9,
        'avg_star' => 4.5,
        'status' => 'approved',
    ]);

    $taskInPeriod = Task::factory()->create([
        'name' => 'Task KPI CEO',
        'pic_id' => $pic->id,
        'status' => 'completed',
        'progress' => 100,
        'started_at' => now()->startOfMonth()->addDays(2),
        'completed_at' => now()->startOfMonth()->addDays(5),
        'deadline' => now()->startOfMonth()->addDays(7),
        'sla_met' => true,
    ]);

    ApprovalLog::factory()->create([
        'task_id' => $taskInPeriod->id,
        'reviewer_id' => $leader->id,
        'approval_level' => 'leader',
        'action' => 'approved',
        'star_rating' => 4,
    ]);

    $taskOutPeriod = Task::factory()->create([
        'name' => 'Task Out CEO',
        'pic_id' => $pic->id,
        'status' => 'completed',
        'progress' => 100,
        'started_at' => now()->subMonths(2)->startOfMonth()->addDay(),
        'completed_at' => now()->subMonths(2)->startOfMonth()->addDays(2),
        'deadline' => now()->subMonths(2)->startOfMonth()->addDays(4),
        'sla_met' => true,
    ]);

    Livewire::actingAs($ceo)
        ->test('kpi.ceo')
        ->assertSee($taskInPeriod->name)
        ->assertSee('90.0')
        ->assertSee($leader->name)
        ->assertDontSee($taskOutPeriod->name);
});
