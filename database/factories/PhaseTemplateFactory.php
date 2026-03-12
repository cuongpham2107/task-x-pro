<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PhaseTemplate>
 */
class PhaseTemplateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $phaseNames = [
            'Khoi tao',
            'Phan tich yeu cau',
            'Thuc thi',
            'Kiem thu nghiem thu',
            'Ban giao',
        ];

        return [
            'project_type' => fake()->randomElement(['warehouse', 'customs', 'trucking', 'software', 'gms', 'tower']),
            'phase_name' => fake()->randomElement($phaseNames),
            'phase_description' => fake()->optional()->sentence(),
            'order_index' => fake()->numberBetween(1, 6),
            'default_weight' => fake()->randomFloat(2, 10, 45),
            'default_duration_days' => fake()->optional(0.8)->numberBetween(7, 60),
            'is_active' => fake()->boolean(90),
        ];
    }
}
