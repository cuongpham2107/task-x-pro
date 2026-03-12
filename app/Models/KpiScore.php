<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class KpiScore extends Model
{
    use HasFactory;

    /**
     * Tu dong tinh lai cac ty le va final_score truoc khi luu.
     */
    protected static function booted(): void
    {
        static::saving(function (KpiScore $kpiScore): void {
            $kpiScore->applyCalculatedRates();
            $kpiScore->applyActualScore();

            if ($kpiScore->calculated_at === null) {
                $kpiScore->calculated_at = Carbon::now();
            }

            if ($kpiScore->period_id === null) {
                $kpiScore->period_id = $kpiScore->period_type.'-'.$kpiScore->period_year.'-'.$kpiScore->period_value;
            }
        });
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'period_id',
        'period_type',
        'period_year',
        'period_value',
        'total_tasks',
        'on_time_tasks',
        'on_time_rate',
        'sla_met_tasks',
        'sla_rate',
        'avg_star',
        'target_score',
        'actual_score',
        'status',
        'approved_at',
        'final_score',
        'calculated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_id' => 'string',
            'period_year' => 'integer',
            'period_value' => 'integer',
            'total_tasks' => 'integer',
            'on_time_tasks' => 'integer',
            'on_time_rate' => 'decimal:2',
            'sla_met_tasks' => 'integer',
            'sla_rate' => 'decimal:2',
            'avg_star' => 'decimal:2',
            'target_score' => 'decimal:2',
            'actual_score' => 'decimal:2',
            'approved_at' => 'datetime',
            'final_score' => 'decimal:2',
            'calculated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Dong bo KPI thang/quy cho mot user theo du lieu task da complete.
     */
    public static function syncForUser(int $userId): void
    {
        $existingScores = static::query()
            ->where('user_id', $userId)
            ->get(['period_type', 'period_year', 'period_value']);

        $completedTasks = Task::query()
            ->where('pic_id', $userId)
            ->where('status', 'completed')
            ->whereNotNull('completed_at')
            ->get(['completed_at']);

        $periodKeys = collect();

        $completedTasks->each(function (Task $task) use ($periodKeys): void {
            $completedAt = Carbon::parse($task->completed_at);
            $year = (int) $completedAt->format('Y');
            $month = (int) $completedAt->format('n');
            $quarter = (int) ceil($month / 3);

            $periodKeys->push("monthly:{$year}:{$month}");
            $periodKeys->push("quarterly:{$year}:{$quarter}");
            $periodKeys->push("yearly:{$year}:1");
        });

        $existingScores->each(function (KpiScore $score) use ($periodKeys): void {
            $periodKeys->push("{$score->period_type}:{$score->period_year}:{$score->period_value}");
        });

        if ($periodKeys->isEmpty()) {
            $now = now();
            $periodKeys->push('monthly:'.$now->year.':'.$now->month);
            $periodKeys->push('quarterly:'.$now->year.':'.((int) ceil($now->month / 3)));
            $periodKeys->push('yearly:'.$now->year.':1');
        }

        $periodKeys->unique()->values()->each(function (string $periodKey) use ($userId): void {
            [$periodType, $periodYear, $periodValue] = explode(':', $periodKey);

            $kpiScore = static::query()->firstOrCreate(
                [
                    'user_id' => $userId,
                    'period_type' => $periodType,
                    'period_year' => (int) $periodYear,
                    'period_value' => (int) $periodValue,
                ],
                [
                    'total_tasks' => 0,
                    'on_time_tasks' => 0,
                    'on_time_rate' => 0,
                    'sla_met_tasks' => 0,
                    'sla_rate' => 0,
                    'avg_star' => 0,
                    'target_score' => 100,
                    'actual_score' => 0,
                    'status' => 'pending',
                    'final_score' => 0,
                    'calculated_at' => now(),
                ]
            );

            $kpiScore->recalculateFromSourceData();
        });
    }

    /**
     * Tinh lai KPI tu du lieu nguon cua ky hien tai.
     *
     * - on_time_rate = on_time_tasks / total_tasks * 100
     * - sla_rate = sla_met_tasks / total_tasks * 100
     * - avg_star lay trung binh tu approval_logs
     */
    public function recalculateFromSourceData(): void
    {
        [$periodStart, $periodEnd] = $this->periodDateRange();

        $completedTasks = Task::query()
            ->where('pic_id', $this->user_id)
            ->where('status', 'completed')
            ->whereNotNull('completed_at')
            ->whereBetween('completed_at', [$periodStart, $periodEnd]);

        $totalTasks = (clone $completedTasks)->count();
        $onTimeTasks = (clone $completedTasks)->whereColumn('completed_at', '<=', 'deadline')->count();
        $slaMetTasks = (clone $completedTasks)->where('sla_met', true)->count();

        $avgStar = (float) (ApprovalLog::query()
            ->where('action', 'approved')
            ->whereNotNull('star_rating')
            ->whereBetween('created_at', [$periodStart, $periodEnd])
            ->whereHas('task', function ($query): void {
                $query->where('pic_id', $this->user_id);
            })
            ->avg('star_rating') ?? 0);

        $this->forceFill([
            'total_tasks' => $totalTasks,
            'on_time_tasks' => $onTimeTasks,
            'sla_met_tasks' => $slaMetTasks,
            'avg_star' => round($avgStar, 2),
            'calculated_at' => now(),
        ]);

        $this->applyCalculatedRates();
        $this->applyActualScore();
        $this->saveQuietly();
    }

    /**
     * Ap dung cong thuc BR-002 cho final_score.
     *
     * final_score = (on_time_rate * 0.4)
     *             + (sla_rate * 0.4)
     *             + ((avg_star / 5 * 100) * 0.2)
     */
    private function applyCalculatedRates(): void
    {
        $totalTasks = (int) $this->total_tasks;
        $onTimeTasks = (int) $this->on_time_tasks;
        $slaMetTasks = (int) $this->sla_met_tasks;
        $avgStar = (float) $this->avg_star;

        $onTimeRate = $totalTasks > 0
            ? round(($onTimeTasks / $totalTasks) * 100, 2)
            : 0.0;

        $slaRate = $totalTasks > 0
            ? round(($slaMetTasks / $totalTasks) * 100, 2)
            : 0.0;

        $normalizedStar = max(0, min(100, ($avgStar / 5) * 100));

        $this->on_time_rate = $onTimeRate;
        $this->sla_rate = $slaRate;
        $this->final_score = round(
            ($onTimeRate * 0.4) + ($slaRate * 0.4) + ($normalizedStar * 0.2),
            2
        );
    }

    private function applyActualScore(): void
    {
        $targetScore = (float) ($this->target_score ?? 100);
        $resultScore = (float) $this->final_score;
        $this->actual_score = $targetScore > 0
            ? round(($resultScore / $targetScore) * 100, 2)
            : 0.0;
    }

    /**
     * Lay khoang ngay bat dau/ket thuc cho ky KPI.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function periodDateRange(): array
    {
        $year = (int) $this->period_year;
        $value = (int) $this->period_value;

        if ($this->period_type === 'yearly') {
            $start = Carbon::create($year, 1, 1)->startOfDay();
            $end = $start->copy()->endOfYear()->endOfDay();

            return [$start, $end];
        }

        if ($this->period_type === 'quarterly') {
            $startMonth = (($value - 1) * 3) + 1;
            $start = Carbon::create($year, $startMonth, 1)->startOfDay();
            $end = $start->copy()->addMonths(2)->endOfMonth()->endOfDay();

            return [$start, $end];
        }

        $start = Carbon::create($year, $value, 1)->startOfDay();
        $end = $start->copy()->endOfMonth()->endOfDay();

        return [$start, $end];
    }
}
