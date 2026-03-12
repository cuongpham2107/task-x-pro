<?php

use App\Models\Phase;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\TaskXProSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('loads the tasks index for authorized users', function (): void {
    $this->seed(TaskXProSeeder::class);

    $ceo = User::query()->where('email', 'ceo@taskxpro.vn')->first();
    $task = Task::query()->first();

    expect($ceo)->not->toBeNull();
    expect($task)->not->toBeNull();

    $this->actingAs($ceo)
        ->get(route('tasks.index'))
        ->assertSuccessful()
        ->assertSee('Danh sách công việc')
        ->assertSee('Thêm công việc')
        ->assertSee($task->name);
});

it('filters phases by selected project in the task form', function (): void {
    $this->seed(TaskXProSeeder::class);

    $ceo = User::query()->where('email', 'ceo@taskxpro.vn')->first();
    expect($ceo)->not->toBeNull();

    $projectAlpha = Project::factory()->create([
        'name' => 'Project Alpha Filter',
        'created_by' => $ceo->id,
    ]);
    $projectBeta = Project::factory()->create([
        'name' => 'Project Beta Filter',
        'created_by' => $ceo->id,
    ]);

    $phaseAlpha = Phase::factory()->create([
        'project_id' => $projectAlpha->id,
        'name' => 'Phase Alpha Filter',
    ]);
    $phaseBeta = Phase::factory()->create([
        'project_id' => $projectBeta->id,
        'name' => 'Phase Beta Filter',
    ]);

    Livewire::actingAs($ceo)
        ->test('task.form')
        ->set('project_id', $projectAlpha->id)
        ->assertSee($phaseAlpha->name)
        ->assertDontSee($phaseBeta->name);
});
