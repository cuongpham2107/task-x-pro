<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TaskComment>
 */
class TaskCommentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'user_id' => User::factory()->pic(),
            'content' => fake()->randomElement([
                'Da cap nhat theo y kien moi, nho anh chi xem lai giup em.',
                'Can bo sung minh chung o buoc nay de de nghiem thu.',
                'Em da xu ly xong phan viec chinh, cho phe duyet tiep theo.',
                'Task nay dang bi vuong dau vao, em de xuat doi deadline 1 ngay.',
            ]),
        ];
    }
}
