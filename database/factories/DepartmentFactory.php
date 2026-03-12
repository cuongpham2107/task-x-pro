<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Department>
 */
class DepartmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $names = [
            'Cong nghe thong tin',
            'Van hanh du an',
            'Logistics',
            'Nhan su',
            'Tai chinh ke toan',
            'Kiem soat noi bo',
            'Kinh doanh',
        ];

        return [
            'code' => 'PB'.fake()->unique()->numberBetween(10, 99),
            'name' => fake()->randomElement($names),
            'head_user_id' => null,
            'status' => fake()->randomElement(['active', 'active', 'inactive']),
        ];
    }
}
