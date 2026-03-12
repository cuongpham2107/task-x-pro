<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\KpiScore>
 */
class KpiScoreFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $periodType = fake()->randomElement(['monthly', 'quarterly', 'yearly']);
        $periodYear = fake()->numberBetween((int) now()->format('Y') - 1, (int) now()->format('Y'));
        $periodValue = $periodType === 'monthly'
            ? fake()->numberBetween(1, 12)
            : ($periodType === 'quarterly' ? fake()->numberBetween(1, 4) : 1);

        $totalTasks = fake()->numberBetween(8, 40);
        $onTimeTasks = fake()->numberBetween(0, $totalTasks);
        $slaMetTasks = fake()->numberBetween(0, $totalTasks);

        $onTimeRate = round(($onTimeTasks / max($totalTasks, 1)) * 100, 2);
        $slaRate = round(($slaMetTasks / max($totalTasks, 1)) * 100, 2);
        $avgStar = round(fake()->randomFloat(2, 3.2, 5), 2);

        $finalScore = round(($onTimeRate * 0.4) + ($slaRate * 0.4) + (($avgStar / 5) * 100 * 0.2), 2);
        $targetScore = 100;
        $actualScore = round(($finalScore / $targetScore) * 100, 2);

        return [
            'user_id' => User::factory()->pic(),
            'period_id' => $periodType.'-'.$periodYear.'-'.$periodValue,
            'period_type' => $periodType,
            'period_year' => $periodYear,
            'period_value' => $periodValue,
            'total_tasks' => $totalTasks,
            'on_time_tasks' => $onTimeTasks,
            'on_time_rate' => $onTimeRate,
            'sla_met_tasks' => $slaMetTasks,
            'sla_rate' => $slaRate,
            'avg_star' => $avgStar,
            'target_score' => $targetScore,
            'actual_score' => $actualScore,
            'status' => fake()->randomElement(['pending', 'approved', 'rejected', 'locked']),
            'approved_at' => fake()->optional()->dateTimeBetween('-10 days', 'now'),
            'final_score' => $finalScore,
            'calculated_at' => fake()->dateTimeBetween('-14 days', 'now'),
        ];
    }
}
