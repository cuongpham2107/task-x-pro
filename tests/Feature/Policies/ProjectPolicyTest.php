<?php

use App\Models\Phase;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach (['project.view', 'project.update', 'project.delete'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    Role::findOrCreate('ceo', 'web')->syncPermissions([
        'project.view',
        'project.update',
        'project.delete',
    ]);

    Role::findOrCreate('leader', 'web')->syncPermissions([
        'project.view',
        'project.update',
        'project.delete',
    ]);

    Role::findOrCreate('pic', 'web')->syncPermissions([
        'project.view',
    ]);
});

it('allows ceo to update and delete project', function () {
    $ceo = User::factory()->create();
    $ceo->assignRole('ceo');

    $project = Project::factory()->create();

    expect($ceo->can('update', $project))->toBeTrue();
    expect($ceo->can('delete', $project))->toBeTrue();
});

it('allows leader to update project', function () {
    $leader = User::factory()->create();
    $leader->assignRole('leader');

    $project = Project::factory()->create();

    expect($leader->can('update', $project))->toBeTrue();
});

it('allows leader to delete project only when leader is creator', function () {
    $leader = User::factory()->create();
    $leader->assignRole('leader');

    $projectCreatedByLeader = Project::factory()->create([
        'created_by' => $leader->id,
    ]);

    $projectCreatedByOther = Project::factory()->create([
        'created_by' => User::factory()->create()->id,
    ]);

    expect($leader->can('delete', $projectCreatedByLeader))->toBeTrue();
    expect($leader->can('delete', $projectCreatedByOther))->toBeFalse();
});

it('allows pic to view but not update or delete project', function () {
    $pic = User::factory()->create();
    $pic->assignRole('pic');

    $project = Project::factory()->create();
    $phase = Phase::factory()->create(['project_id' => $project->id]);
    Task::factory()->create([
        'phase_id' => $phase->id,
        'pic_id' => $pic->id,
    ]);

    expect($pic->can('view', $project))->toBeTrue();
    expect($pic->can('update', $project))->toBeFalse();
    expect($pic->can('delete', $project))->toBeFalse();
});
