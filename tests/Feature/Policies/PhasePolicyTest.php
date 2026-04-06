<?php

use App\Models\Phase;
use App\Models\Project;
use App\Models\ProjectLeader;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    Permission::findOrCreate('phase.view', 'web');
    Permission::findOrCreate('phase.update', 'web');

    Role::findOrCreate('ceo', 'web')->syncPermissions([
        'phase.view',
        'phase.update',
    ]);

    Role::findOrCreate('leader', 'web')->syncPermissions([
        'phase.view',
        'phase.update',
    ]);

    Role::findOrCreate('pic', 'web')->syncPermissions([
        'phase.view',
    ]);
});

it('allows ceo to update and delete phase', function () {
    $ceo = User::factory()->create();
    $ceo->assignRole('ceo');

    // Case 1: Active project - CEO must also be creator of phase to pass existing policy
    $project = Project::factory()->create(['created_by' => $ceo->id, 'status' => 'init']);
    $phase = Phase::factory()->create(['project_id' => $project->id, 'created_by' => $ceo->id]);

    expect($ceo->can('update', $phase))->toBeTrue();
    expect($ceo->can('delete', $phase))->toBeTrue();

    // Case 2: Completed project - deny
    $project->update(['status' => \App\Enums\ProjectStatus::Completed]);
    expect($ceo->can('update', $phase))->toBeFalse();
    expect($ceo->can('delete', $phase))->toBeFalse();
});

it('allows leader in project to update and delete phase', function () {
    $leader = User::factory()->create();
    $leader->assignRole('leader');

    $project = Project::factory()->create(['status' => 'init']);
    $phase = Phase::factory()->create(['project_id' => $project->id]);

    ProjectLeader::factory()->create([
        'project_id' => $phase->project_id,
        'user_id' => $leader->id,
        'assigned_by' => $leader->id,
    ]);

    expect($leader->can('update', $phase))->toBeTrue();
    expect($leader->can('delete', $phase))->toBeTrue();

    // Completed project - should NOT allow
    $project->update(['status' => \App\Enums\ProjectStatus::Completed]);
    expect($leader->can('update', $phase))->toBeFalse();
    expect($leader->can('delete', $phase))->toBeFalse();
});

it('allows leader to update and delete phase even when not assigned to project', function () {
    $leader = User::factory()->create();
    $leader->assignRole('leader');

    $phase = Phase::factory()->create();

    expect($leader->can('update', $phase))->toBeTrue();
    expect($leader->can('delete', $phase))->toBeTrue();
});

it('prevents pic from updating and deleting phase', function () {
    $pic = User::factory()->create();
    $pic->assignRole('pic');

    $phase = Phase::factory()->create();

    expect($pic->can('update', $phase))->toBeFalse();
    expect($pic->can('delete', $phase))->toBeFalse();
});

it('still allows assigned leader even when phase.update permission is removed from role', function () {
    $leaderRole = Role::findByName('leader', 'web');
    $leaderRole->syncPermissions(['phase.view']);

    $leader = User::factory()->create();
    $leader->assignRole('leader');

    $phase = Phase::factory()->create();

    ProjectLeader::factory()->create([
        'project_id' => $phase->project_id,
        'user_id' => $leader->id,
        'assigned_by' => $leader->id,
    ]);

    expect($leader->can('update', $phase))->toBeTrue();
    expect($leader->can('delete', $phase))->toBeTrue();
});
