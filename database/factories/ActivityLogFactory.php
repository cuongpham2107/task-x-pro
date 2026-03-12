<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ActivityLog>
 */
class ActivityLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $entityType = fake()->randomElement([Task::class, Project::class]);

        return [
            'user_id' => fake()->boolean(90) ? User::factory() : null,
            'entity_type' => $entityType,
            'entity_id' => fake()->numberBetween(1, 300),
            'action' => fake()->randomElement(['created', 'updated', 'status_changed', 'approved']),
            'old_values' => ['status' => 'pending', 'progress' => 0],
            'new_values' => ['status' => 'in_progress', 'progress' => fake()->numberBetween(10, 90)],
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'created_at' => fake()->dateTimeBetween('-20 days', 'now'),
        ];
    }
}
