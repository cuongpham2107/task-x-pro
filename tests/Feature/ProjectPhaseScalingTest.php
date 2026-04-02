<?php

use App\Models\PhaseTemplate;
use App\Models\Project;
use App\Models\User;
use App\Services\Projects\ProjectPhaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actor = User::factory()->create();
    Gate::define('create', fn () => true);
    Gate::define('project.create', fn () => true);
    Gate::define('update', fn () => true);
    Gate::define('syncPhases', fn () => true);
});

it('scales phase dates proportionally when both start and end dates are provided', function () {
    // Template: 2 phases, 10 days each (Total 20 days)
    PhaseTemplate::create([
        'project_type' => 'software',
        'phase_name' => 'Phase 1',
        'default_duration_days' => 10,
        'default_weight' => 50,
        'order_index' => 1,
        'is_active' => true,
    ]);
    PhaseTemplate::create([
        'project_type' => 'software',
        'phase_name' => 'Phase 2',
        'default_duration_days' => 10,
        'default_weight' => 50,
        'order_index' => 2,
        'is_active' => true,
    ]);

    // Project: 30 days total (Scaling up from 20 to 30)
    // 30 / 20 = 1.5 scale factor.
    // Phase 1: 10 * 1.5 = 15 days.
    // Phase 2: 10 * 1.5 = 15 days.
    $startDate = Carbon::parse('2024-01-01');
    $endDate = Carbon::parse('2024-01-30'); // 30 days inclusive

    $project = Project::factory()->create([
        'type' => 'software',
        'start_date' => $startDate->toDateString(),
        'end_date' => $endDate->toDateString(),
    ]);

    $service = app(ProjectPhaseService::class);
    $service->createPhasesFromTemplate($project);

    $phases = $project->phases()->orderBy('order_index')->get();

    expect($phases)->toHaveCount(2);

    // Phase 1: 2024-01-01 -> 2024-01-15 (15 days)
    expect($phases[0]->start_date->toDateString())->toBe('2024-01-01');
    expect($phases[0]->end_date->toDateString())->toBe('2024-01-15');

    // Phase 2: 2024-01-16 -> 2024-01-30 (15 days)
    expect($phases[1]->start_date->toDateString())->toBe('2024-01-16');
    expect($phases[1]->end_date->toDateString())->toBe('2024-01-30');
});

it('handles remainders by distributing them to the first phases', function () {
    // Template: 3 phases, 10 days each (Total 30 days)
    foreach (range(1, 3) as $i) {
        PhaseTemplate::create([
            'project_type' => 'software',
            'phase_name' => "Phase $i",
            'default_duration_days' => 10,
            'default_weight' => $i === 3 ? 34 : 33,
            'order_index' => $i,
            'is_active' => true,
        ]);
    }

    // Project: 32 days total
    // 32 / 30 = 1.066...
    // Floor durations: 10, 10, 10 (Total 30)
    // Remainder: 2 days. Should go to Phase 1 and Phase 2.
    // Final durations: 11, 11, 10.
    $startDate = Carbon::parse('2024-01-01');
    $endDate = Carbon::parse('2024-02-01'); // 32 days inclusive (Jan has 31 days)

    $project = Project::factory()->create([
        'type' => 'software',
        'start_date' => $startDate->toDateString(),
        'end_date' => $endDate->toDateString(),
    ]);

    $service = app(ProjectPhaseService::class);
    $service->createPhasesFromTemplate($project);

    $phases = $project->phases()->orderBy('order_index')->get();

    // Durations: 11, 11, 10
    expect($phases[0]->start_date->diffInDays($phases[0]->end_date) + 1)->toEqual(11);
    expect($phases[1]->start_date->diffInDays($phases[1]->end_date) + 1)->toEqual(11);
    expect($phases[2]->start_date->diffInDays($phases[2]->end_date) + 1)->toEqual(10);
    expect($phases[2]->end_date->toDateString())->toBe($endDate->toDateString());
});

it('falls back to sequential allocation when end_date is missing', function () {
    PhaseTemplate::create([
        'project_type' => 'software',
        'phase_name' => 'Sequential Phase',
        'default_duration_days' => 10,
        'default_weight' => 100,
        'order_index' => 1,
        'is_active' => true,
    ]);

    $project = Project::factory()->create([
        'type' => 'software',
        'start_date' => '2024-01-01',
        'end_date' => null,
    ]);

    $service = app(ProjectPhaseService::class);
    $service->createPhasesFromTemplate($project);

    $phase = $project->phases()->first();

    expect($phase->start_date->toDateString())->toBe('2024-01-01');
    expect($phase->end_date->toDateString())->toBe('2024-01-10');
});
