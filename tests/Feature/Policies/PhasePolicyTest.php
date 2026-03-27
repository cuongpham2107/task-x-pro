<?php

use App\Models\Phase;
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

    $phase = Phase::factory()->create();

    expect($ceo->can('update', $phase))->toBeTrue();
    expect($ceo->can('delete', $phase))->toBeTrue();
});

it('allows leader in project to update and delete phase', function () {
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
