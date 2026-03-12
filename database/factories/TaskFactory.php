<?php

namespace Database\Factories;

use App\Models\Phase;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Task>
 */
class TaskFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $status = fake()->randomElement(['pending', 'in_progress', 'waiting_approval', 'completed', 'late']);
        $deadline = $status === 'late'
            ? fake()->dateTimeBetween('-20 days', '-1 day')
            : fake()->dateTimeBetween('-2 days', '+25 days');

        $startedAt = null;
        $completedAt = null;
        $slaMet = null;
        $delayDays = 0;

        if ($status !== 'pending') {
            $startedAt = fake()->dateTimeBetween('-20 days', 'now');
        }

        if ($status === 'completed') {
            $completedAt = fake()->dateTimeBetween($startedAt ?: '-10 days', 'now');
            $slaMet = fake()->boolean(75);
            $delayDays = $slaMet ? fake()->randomFloat(2, 0, 0.5) : fake()->randomFloat(2, 0.5, 6);
        }

        if ($status === 'late') {
            $delayDays = fake()->randomFloat(2, 1, 10);
        }

        $progress = match ($status) {
            'pending' => fake()->numberBetween(0, 10),
            'in_progress' => fake()->numberBetween(20, 85),
            'waiting_approval' => fake()->numberBetween(85, 99),
            'completed' => 100,
            'late' => fake()->numberBetween(35, 95),
        };

        return [
            'phase_id' => Phase::factory(),
            'name' => fake()->randomElement([
                'Khao sat hien trang',
                'Cap nhat tai lieu quy trinh',
                'Trien khai cau hinh he thong',
                'Tong hop bao cao tien do',
                'Danh gia rui ro va de xuat',
            ]),
            'description' => fake()->sentence(14),
            'type' => fake()->randomElement(['admin', 'technical', 'operation', 'report', 'other']),
            'status' => $status,
            'priority' => fake()->randomElement(['low', 'medium', 'high', 'urgent']),
            'progress' => $progress,
            'pic_id' => User::factory()->pic(),
            'dependency_task_id' => null,
            'deadline' => $deadline,
            'started_at' => $startedAt,
            'completed_at' => $completedAt,
            'deliverable_url' => fake()->optional(0.45)->url(),
            'issue_note' => fake()->optional(0.35)->sentence(),
            'recommendation' => fake()->optional(0.35)->sentence(),
            'workflow_type' => fake()->randomElement(['single', 'double']),
            'sla_standard_hours' => fake()->randomFloat(2, 6, 72),
            'sla_met' => $slaMet,
            'delay_days' => $delayDays,
            'created_by' => User::factory()->leader(),
        ];
    }
}
