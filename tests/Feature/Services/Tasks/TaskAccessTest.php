<?php

use App\Models\Phase;
use App\Models\Project;
use App\Models\ProjectLeader;
use App\Models\Task;
use App\Models\User;
use App\Services\Tasks\TaskQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Setup roles
    Role::create(['name' => 'super_admin']);
    Role::create(['name' => 'ceo']);
    Role::create(['name' => 'leader']);
    Role::create(['name' => 'pic']);
});

it('allows super_admin to see all tasks', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    Task::factory()->count(5)->create();

    $service = new TaskQueryService;
    $tasks = $service->taskScopeForActor($admin)->get();

    expect($tasks)->toHaveCount(5);
});

it('allows ceo to see all tasks', function () {
    $ceo = User::factory()->create();
    $ceo->assignRole('ceo');

    Task::factory()->count(5)->create();

    $service = new TaskQueryService;
    $tasks = $service->taskScopeForActor($ceo)->get();

    expect($tasks)->toHaveCount(5);
});

it('restricts leader to only their managed projects', function () {
    $leader = User::factory()->create();
    $leader->assignRole('leader');

    $projectManaged = Project::factory()->create();
    ProjectLeader::create([
        'project_id' => $projectManaged->id,
        'user_id' => $leader->id,
        'assigned_by' => User::factory()->create()->id,
        'assigned_at' => now(),
    ]);

    $phaseA = Phase::factory()->create(['project_id' => $projectManaged->id]);
    $taskA = Task::factory()->create(['phase_id' => $phaseA->id]);

    $projectNotManaged = Project::factory()->create();
    $phaseB = Phase::factory()->create(['project_id' => $projectNotManaged->id]);
    $taskB = Task::factory()->create(['phase_id' => $phaseB->id]);

    $service = new TaskQueryService;
    $tasks = $service->taskScopeForActor($leader)->get();

    expect($tasks)->toHaveCount(1);
    expect($tasks->pluck('id'))->toContain($taskA->id);
    expect($tasks->pluck('id'))->not->toContain($taskB->id);
});

it('allows leader to see tasks where they are PIC even if project is not managed', function () {
    $leader = User::factory()->create();
    $leader->assignRole('leader');

    $projectNotManaged = Project::factory()->create();
    $phase = Phase::factory()->create(['project_id' => $projectNotManaged->id]);
    $taskAsPic = Task::factory()->create([
        'phase_id' => $phase->id,
        'pic_id' => $leader->id,
    ]);

    $service = new TaskQueryService;
    $tasks = $service->taskScopeForActor($leader)->get();

    expect($tasks)->toHaveCount(1);
    expect($tasks->first()->id)->toBe($taskAsPic->id);
});

it('restricts regular PIC to only their involved tasks', function () {
    $pic = User::factory()->create();
    $pic->assignRole('pic');

    $phase = Phase::factory()->create();
    $taskOwned = Task::factory()->create(['phase_id' => $phase->id, 'pic_id' => $pic->id]);
    $taskOther = Task::factory()->create(['phase_id' => $phase->id]);

    $service = new TaskQueryService;
    $tasks = $service->taskScopeForActor($pic)->get();

    expect($tasks)->toHaveCount(1);
    expect($tasks->first()->id)->toBe($taskOwned->id);
});
