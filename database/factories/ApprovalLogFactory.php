<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ApprovalLog>
 */
class ApprovalLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $action = fake()->randomElement(['submitted', 'approved', 'rejected']);

        return [
            'task_id' => Task::factory(),
            'reviewer_id' => User::factory()->leader(),
            'approval_level' => fake()->randomElement(['leader', 'ceo']),
            'action' => $action,
            'star_rating' => $action === 'approved' ? fake()->numberBetween(3, 5) : null,
            'comment' => fake()->optional(0.8)->sentence(),
            'created_at' => fake()->dateTimeBetween('-15 days', 'now'),
        ];
    }
}
