<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SlaConfig>
 */
class SlaConfigFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $effectiveDate = fake()->dateTimeBetween('-180 days', 'now');
        $expiredDate = fake()->boolean(35)
            ? (clone $effectiveDate)->modify('+'.fake()->numberBetween(30, 180).' days')
            : null;

        return [
            'department_id' => fake()->boolean(20) ? null : Department::factory(),
            'task_type' => fake()->randomElement(['admin', 'technical', 'operation', 'report', 'other', 'all']),
            'project_type' => fake()->randomElement(['warehouse', 'customs', 'trucking', 'software', 'gms', 'tower', 'all']),
            'standard_hours' => fake()->randomFloat(2, 4, 96),
            'effective_date' => $effectiveDate->format('Y-m-d'),
            'expired_date' => $expiredDate?->format('Y-m-d'),
            'note' => fake()->optional()->sentence(),
            'created_by' => User::factory()->leader(),
        ];
    }
}
