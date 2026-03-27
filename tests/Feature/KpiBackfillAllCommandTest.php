<?php

use App\Enums\TaskStatus;
use App\Enums\UserStatus;
use App\Models\KpiScore;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

it('backfills kpi for all members with historical task data across activity timeline', function (): void {
    $activePic = User::factory()->pic()->create(['status' => UserStatus::Active]);
    $resignedPic = User::factory()->pic()->create(['status' => UserStatus::Resigned]);

    Task::factory()->create([
        'pic_id' => $activePic->id,
        'status' => TaskStatus::Completed,
        'progress' => 100,
        'started_at' => Carbon::parse('2024-01-10 09:00:00'),
        'completed_at' => Carbon::parse('2024-01-20 18:00:00'),
        'deadline' => Carbon::parse('2024-01-21 18:00:00'),
        'sla_met' => true,
    ]);

    Task::factory()->create([
        'pic_id' => $resignedPic->id,
        'status' => TaskStatus::Completed,
        'progress' => 100,
        'started_at' => Carbon::parse('2023-06-05 09:00:00'),
        'completed_at' => Carbon::parse('2023-06-25 18:00:00'),
        'deadline' => Carbon::parse('2023-06-24 18:00:00'),
        'sla_met' => false,
    ]);

    Artisan::call('kpi:backfill-all');

    expect(KpiScore::query()->where('user_id', $activePic->id)->exists())->toBeTrue()
        ->and(KpiScore::query()->where('user_id', $resignedPic->id)->exists())->toBeTrue()
        ->and(Artisan::output())->toContain('Đã đồng bộ KPI lịch sử');

    expect(
        KpiScore::query()
            ->where('user_id', $activePic->id)
            ->where('period_type', 'monthly')
            ->where('period_year', 2024)
            ->where('period_value', 1)
            ->exists()
    )->toBeTrue();

    expect(
        KpiScore::query()
            ->where('user_id', $resignedPic->id)
            ->where('period_type', 'yearly')
            ->where('period_year', 2023)
            ->where('period_value', 1)
            ->exists()
    )->toBeTrue();
});

it('skips members without historical pic task or existing kpi', function (): void {
    $memberWithoutTask = User::factory()->leader()->create(['status' => UserStatus::Active]);

    Artisan::call('kpi:backfill-all');

    expect(KpiScore::query()->where('user_id', $memberWithoutTask->id)->exists())->toBeFalse();
});
