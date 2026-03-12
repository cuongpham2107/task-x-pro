<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Project>
 */
class ProjectFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $types = ['warehouse', 'customs', 'trucking', 'software', 'gms', 'tower'];
        $statuses = ['init', 'running', 'paused', 'completed'];
        $type = fake()->randomElement($types);
        $status = fake()->randomElement($statuses);

        $startDate = fake()->dateTimeBetween('-60 days', '+20 days');
        $endDate = (clone $startDate)->modify('+'.fake()->numberBetween(45, 180).' days');

        $budget = fake()->randomFloat(2, 50000000, 2000000000);
        $budgetSpent = $status === 'init'
            ? 0
            : fake()->randomFloat(2, 0, (float) $budget * 0.85);

        $projectNames = [
            'Nang cap quy trinh kho tong',
            'Toi uu thong quan dien tu',
            'Trien khai he thong quan ly van tai',
            'So hoa du lieu van hanh',
            'Xay dung dashboard KPI noi bo',
            'Cai tien quy trinh giao nhan',
        ];

        return [
            'name' => fake()->randomElement($projectNames).' '.fake()->numberBetween(1, 99),
            'type' => $type,
            'status' => $status,
            'budget' => $budget,
            'budget_spent' => $budgetSpent,
            'objective' => fake()->randomElement([
                'Tang toc do xu ly va giam sai sot van hanh.',
                'Chuan hoa quy trinh va nang cao chat luong giao pham.',
                'Toi uu chi phi va cai thien hieu suat nhom du an.',
            ]),
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'progress' => fake()->numberBetween(0, 95),
            'created_by' => User::factory()->leader(),
        ];
    }
}
