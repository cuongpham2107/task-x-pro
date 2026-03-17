<?php

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    Role::firstOrCreate(['name' => 'super_admin']);
    Role::firstOrCreate(['name' => 'ceo']);
    Role::firstOrCreate(['name' => 'leader']);
    Role::firstOrCreate(['name' => 'pic']);
});

test('can switch between table and gantt views', function () {
    $user = User::factory()->create();
    $start = now()->subDays(3)->startOfDay();

    Task::factory()->create([
        'pic_id' => $user->id,
        'created_by' => $user->id,
        'started_at' => $start,
        'deadline' => $start->copy()->addDays(5),
        'status' => 'in_progress',
    ]);

    Livewire::actingAs($user)
        ->test('pages::tasks.table')
        ->assertSet('viewMode', 'table')
        ->assertSee('Tên công việc')
        ->call('switchView', 'gantt')
        ->assertSet('viewMode', 'gantt')
        ->assertSee('Tổng quan tiến độ theo thời gian');
});
