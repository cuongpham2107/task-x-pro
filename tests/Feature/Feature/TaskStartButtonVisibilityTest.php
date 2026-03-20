<?php

use App\Models\Phase;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\TaskXProSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(TaskXProSeeder::class);
});

it('shows start task button to a regular user who is the PIC', function (): void {
    $pic = User::query()->where('email', 'pic@taskxpro.vn')->first();

    expect($pic)->not->toBeNull();

    $task = Task::factory()->create([
        'status' => 'pending',
        'pic_id' => $pic->id,
        'created_by' => $pic->id,
    ]);

    Livewire::actingAs($pic)
        ->test('task.form')
        ->dispatch('task-edit-requested', taskId: $task->id)
        ->assertSee('Bắt đầu công việc');
});

it('hides start task button from leader role', function (): void {
    $leader = User::query()->where('email', 'leader@taskxpro.vn')->first();

    $task = Task::factory()->create(['status' => 'pending']);

    Livewire::actingAs($leader)
        ->test('task.form')
        ->dispatch('task-edit-requested', taskId: $task->id)
        ->assertDontSee('Bắt đầu công việc');
});

it('hides start task button from ceo role', function (): void {
    $ceo = User::query()->where('email', 'ceo@taskxpro.vn')->first();

    $task = Task::factory()->create(['status' => 'pending']);

    Livewire::actingAs($ceo)
        ->test('task.form')
        ->dispatch('task-edit-requested', taskId: $task->id)
        ->assertDontSee('Bắt đầu công việc');
});
