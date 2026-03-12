<?php

use App\Models\ApprovalLog;
use App\Models\KpiScore;
use App\Models\Phase;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('phase and project progress are recalculated from task progress', function (): void {
    $creator = User::factory()->create();
    $pic = User::factory()->create();

    $project = Project::factory()->create([
        'created_by' => $creator->id,
        'progress' => 0,
    ]);

    $phaseA = Phase::factory()->create([
        'project_id' => $project->id,
        'weight' => 60,
        'progress' => 0,
        'status' => 'pending',
    ]);

    $phaseB = Phase::factory()->create([
        'project_id' => $project->id,
        'weight' => 40,
        'progress' => 0,
        'status' => 'pending',
    ]);

    $taskA1 = Task::factory()->create([
        'phase_id' => $phaseA->id,
        'pic_id' => $pic->id,
        'created_by' => $creator->id,
        'status' => 'in_progress',
        'progress' => 40,
        'dependency_task_id' => null,
    ]);

    Task::factory()->create([
        'phase_id' => $phaseA->id,
        'pic_id' => $pic->id,
        'created_by' => $creator->id,
        'status' => 'in_progress',
        'progress' => 80,
        'dependency_task_id' => null,
    ]);

    Task::factory()->create([
        'phase_id' => $phaseB->id,
        'pic_id' => $pic->id,
        'created_by' => $creator->id,
        'status' => 'in_progress',
        'progress' => 25,
        'dependency_task_id' => null,
    ]);

    $phaseA->refresh();
    $phaseB->refresh();
    $project->refresh();

    expect($phaseA->progress)->toBe(60)
        ->and($phaseB->progress)->toBe(25)
        ->and($project->progress)->toBe(46);

    $taskA1->update(['progress' => 100]);

    $phaseA->refresh();
    $project->refresh();

    expect($phaseA->progress)->toBe(90)
        ->and($project->progress)->toBe(64);
});

test('kpi score follows br002 formula from completed tasks', function (): void {
    $creator = User::factory()->create();
    $pic = User::factory()->create();

    $project = Project::factory()->create([
        'created_by' => $creator->id,
    ]);

    $phase = Phase::factory()->create([
        'project_id' => $project->id,
        'weight' => 100,
    ]);

    $completedAtOnTime = now()->subHours(2);
    $completedAtLate = now()->subHour();

    $taskOnTime = Task::factory()->create([
        'phase_id' => $phase->id,
        'pic_id' => $pic->id,
        'created_by' => $creator->id,
        'status' => 'completed',
        'progress' => 100,
        'started_at' => now()->subHours(10),
        'completed_at' => $completedAtOnTime,
        'deadline' => now()->addHour(),
        'sla_standard_hours' => 12,
        'dependency_task_id' => null,
    ]);

    $taskLate = Task::factory()->create([
        'phase_id' => $phase->id,
        'pic_id' => $pic->id,
        'created_by' => $creator->id,
        'status' => 'completed',
        'progress' => 100,
        'started_at' => now()->subHours(20),
        'completed_at' => $completedAtLate,
        'deadline' => now()->subHours(5),
        'sla_standard_hours' => 8,
        'dependency_task_id' => null,
    ]);

    ApprovalLog::factory()->create([
        'task_id' => $taskOnTime->id,
        'reviewer_id' => $creator->id,
        'action' => 'approved',
        'star_rating' => 4,
        'created_at' => now(),
    ]);

    ApprovalLog::factory()->create([
        'task_id' => $taskLate->id,
        'reviewer_id' => $creator->id,
        'action' => 'approved',
        'star_rating' => 2,
        'created_at' => now(),
    ]);

    KpiScore::syncForUser($pic->id);

    $monthlyScore = KpiScore::query()
        ->where('user_id', $pic->id)
        ->where('period_type', 'monthly')
        ->where('period_year', (int) now()->format('Y'))
        ->where('period_value', (int) now()->format('n'))
        ->firstOrFail();

    expect($monthlyScore->total_tasks)->toBe(2)
        ->and($monthlyScore->on_time_tasks)->toBe(1)
        ->and((float) $monthlyScore->on_time_rate)->toBe(50.0)
        ->and($monthlyScore->sla_met_tasks)->toBe(1)
        ->and((float) $monthlyScore->sla_rate)->toBe(50.0)
        ->and((float) $monthlyScore->avg_star)->toBe(3.0)
        ->and((float) $monthlyScore->final_score)->toBe(52.0);
});

test('kpi actual score and period id are calculated on save', function (): void {
    $score = KpiScore::factory()->create([
        'period_type' => 'yearly',
        'period_year' => 2025,
        'period_value' => 1,
        'period_id' => null,
        'target_score' => 120,
        'total_tasks' => 10,
        'on_time_tasks' => 10,
        'sla_met_tasks' => 10,
        'avg_star' => 2.5,
        'actual_score' => 0,
    ]);

    expect($score->period_id)->toBe('yearly-2025-1')
        ->and((float) $score->actual_score)->toBe(75.0);
});
