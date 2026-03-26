<?php

use App\Enums\PhaseStatus;
use App\Enums\TaskStatus;
use App\Models\Phase;
use App\Models\Task;
use App\Models\User;
use App\Services\Phases\PhaseMutationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(PhaseMutationService::class);
    $this->actor = User::factory()->leader()->create();
});

it('prevents completing a phase when there are no tasks', function () {
    $phase = Phase::factory()->create([
        'weight' => 35,
        'status' => PhaseStatus::Active->value,
    ]);

    expect(function () use ($phase): void {
        $this->service->update($this->actor, $phase, [
            'status' => PhaseStatus::Completed->value,
        ]);
    })->toThrow(ValidationException::class);
});

it('prevents completing a phase when at least one task is not approved yet', function () {
    $phase = Phase::factory()->create([
        'weight' => 35,
        'status' => PhaseStatus::Active->value,
    ]);

    Task::factory()->create([
        'phase_id' => $phase->id,
        'status' => TaskStatus::WaitingApproval->value,
        'progress' => 100,
    ]);

    expect(function () use ($phase): void {
        $this->service->update($this->actor, $phase, [
            'status' => PhaseStatus::Completed->value,
        ]);
    })->toThrow(ValidationException::class);
});

it('allows completing a phase only when all tasks are completed and 100 percent', function () {
    $phase = Phase::factory()->create([
        'weight' => 35,
        'status' => PhaseStatus::Active->value,
    ]);

    Task::factory()->count(2)->create([
        'phase_id' => $phase->id,
        'status' => TaskStatus::Completed->value,
        'progress' => 100,
    ]);

    $updatedPhase = $this->service->update($this->actor, $phase, [
        'status' => PhaseStatus::Completed->value,
    ]);

    expect($updatedPhase->refresh()->status)->toBe(PhaseStatus::Completed->value);
});

it('keeps phase active when tasks are 100 percent but still waiting approval', function () {
    $phase = Phase::factory()->create([
        'status' => PhaseStatus::Pending->value,
    ]);

    Task::factory()->create([
        'phase_id' => $phase->id,
        'status' => TaskStatus::WaitingApproval->value,
        'progress' => 100,
    ]);

    $phase->refreshProgressFromTasks();

    expect($phase->refresh()->status)->toBe(PhaseStatus::Active->value);
    expect($phase->refresh()->progress)->toBe(100);
});

it('marks phase completed when every task is completed and 100 percent', function () {
    $phase = Phase::factory()->create([
        'status' => PhaseStatus::Pending->value,
    ]);

    Task::factory()->count(2)->create([
        'phase_id' => $phase->id,
        'status' => TaskStatus::Completed->value,
        'progress' => 100,
    ]);

    $phase->refreshProgressFromTasks();

    expect($phase->refresh()->status)->toBe(PhaseStatus::Completed->value);
    expect($phase->refresh()->progress)->toBe(100);
});
