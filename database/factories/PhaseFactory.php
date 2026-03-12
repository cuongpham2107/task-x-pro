<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Phase>
 */
class PhaseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('-40 days', '+20 days');
        $endDate = (clone $startDate)->modify('+'.fake()->numberBetween(10, 60).' days');

        return [
            'project_id' => Project::factory(),
            'name' => fake()->randomElement([
                'Khoi dong du an',
                'Phan tich nghiep vu',
                'Thuc hien trien khai',
                'Danh gia chat luong',
                'Nghiem thu va dong du an',
            ]),
            'description' => fake()->optional()->sentence(),
            'weight' => fake()->randomFloat(2, 10, 40),
            'order_index' => fake()->numberBetween(1, 8),
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'progress' => fake()->numberBetween(0, 100),
            'status' => fake()->randomElement(['pending', 'active', 'completed']),
            'is_template' => false,
        ];
    }
}
