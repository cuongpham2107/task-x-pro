<?php

use App\Models\PhaseTemplate;
use App\Models\User;
use App\Services\Projects\ProjectMutationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actor = User::factory()->create();
    Gate::define('create', fn () => true);
    Gate::define('project.create', fn () => true);
    Gate::define('update', fn () => true);
    Gate::define('syncPhases', fn () => true);
});

it('creates a project without phases when phasePayloads is an empty array', function () {
    $service = app(ProjectMutationService::class);

    $attributes = [
        'name' => 'Project Without Phases',
        'type' => 'software',
        'start_date' => now()->toDateString(),
    ];

    // Explicitly pass empty array for phasePayloads
    $project = $service->create($this->actor, $attributes, [], []);

    expect($project->phases)->toHaveCount(0);
    expect($project->name)->toBe('Project Without Phases');
});

it('creates a project with template phases when phasePayloads is null', function () {
    // Setup a template for 'software' type
    PhaseTemplate::create([
        'project_type' => 'software',
        'phase_name' => 'Initial Phase',
        'default_weight' => 100,
        'order_index' => 1,
        'is_active' => true,
    ]);

    $service = app(ProjectMutationService::class);

    $attributes = [
        'name' => 'Project With Template',
        'type' => 'software',
        'start_date' => now()->toDateString(),
    ];

    // Pass null for phasePayloads to trigger template fallback
    $project = $service->create($this->actor, $attributes, [], null);

    expect($project->phases)->toHaveCount(1);
    expect($project->phases->first()->name)->toBe('Initial Phase');
});

it('creates a project with specific phases when phasePayloads is provided', function () {
    $service = app(ProjectMutationService::class);

    $attributes = [
        'name' => 'Project With Specific Phases',
        'type' => 'software',
        'start_date' => now()->toDateString(),
    ];

    $phasePayloads = [
        [
            'name' => 'Custom Phase',
            'weight' => 100,
            'order_index' => 1,
        ],
    ];

    $project = $service->create($this->actor, $attributes, [], $phasePayloads);

    expect($project->phases)->toHaveCount(1);
    expect($project->phases->first()->name)->toBe('Custom Phase');
});
