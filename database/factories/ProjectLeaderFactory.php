<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProjectLeader>
 */
class ProjectLeaderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'user_id' => User::factory()->leader(),
            'assigned_by' => User::factory()->ceo(),
            'assigned_at' => fake()->dateTimeBetween('-120 days', 'now'),
        ];
    }
}
