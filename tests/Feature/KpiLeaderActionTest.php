<?php

use App\Models\Department;
use App\Models\KpiScore;
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
