<?php

use App\Enums\ProjectStatus;
use App\Models\Phase;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\Projects\ProjectMutationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actor = User::factory()->create();

    // Bypass all authorization for testing
    Gate::before(fn () => true);
});

it('does not delete phases when updating project with phasePayloads as null', function () {
    // 1. Create a project with a phase and a task
    $project = Project::factory()->create([
        'status' => ProjectStatus::Init,
        'start_date' => '2024-01-01',
    ]);

    $phase = Phase::factory()->create([
        'project_id' => $project->id,
        'name' => 'Existing Phase',
        'weight' => 100,
    ]);

    Task::factory()->create([
        'phase_id' => $phase->id,
        'name' => 'Existing Task',
    ]);

    $service = app(ProjectMutationService::class);

    $attributes = [
        'name' => 'Updated Project Name',
        'type' => $project->type->value,
        'start_date' => '2024-02-01', // Update start date
    ];

    // 2. Update project with phasePayloads: null (should NOT touch phases)
    $updatedProject = $service->update($this->actor, $project, $attributes, [], null);

    // 3. Assertions
    expect($updatedProject->name)->toBe('Updated Project Name');
    expect($updatedProject->start_date->toDateString())->toBe('2024-02-01');
    expect($updatedProject->phases)->toHaveCount(1);
    expect($updatedProject->phases->first()->name)->toBe('Existing Phase');
});

it('fails to update project when phasePayloads is [] and phases have tasks', function () {
    // 1. Create a project with a phase and a task
    $project = Project::factory()->create();

    $phase = Phase::factory()->create([
        'project_id' => $project->id,
        'name' => 'Existing Phase',
        'weight' => 100,
    ]);

    Task::factory()->create([
        'phase_id' => $phase->id,
    ]);

    $service = app(ProjectMutationService::class);

    $attributes = [
        'name' => 'Updated Project Name',
        'type' => $project->type->value,
    ];

    // 2. Update project with phasePayloads: [] (should attempt to delete all phases)
    // This should throw a ValidationException because the phase has tasks
    try {
        $service->update($this->actor, $project, $attributes, [], []);
        $this->fail('Expected ValidationException was not thrown');
    } catch (\Illuminate\Validation\ValidationException $e) {
        expect($e->validator->errors()->has('phases'))->toBeTrue();
        expect($e->validator->errors()->first('phases'))->toContain('Không thể xóa phase');
    }
});
