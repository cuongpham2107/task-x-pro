<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SystemNotification>
 */
class SystemNotificationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $notifiableType = fake()->randomElement([Task::class, Project::class, null]);
        $status = fake()->randomElement(['pending', 'pending', 'sent', 'failed']);

        $sentAt = $status === 'sent'
            ? fake()->dateTimeBetween('-3 days', 'now')
            : null;

        $scheduledAt = $status === 'pending'
            ? fake()->dateTimeBetween('now', '+3 days')
            : fake()->optional(0.3)->dateTimeBetween('-2 days', '+1 day');

        return [
            'user_id' => User::factory(),
            'type' => fake()->randomElement(['deadline_reminder', 'task_late', 'approval_result', 'weekly_report']),
            'channel' => fake()->randomElement(['telegram', 'email', 'both']),
            'title' => fake()->randomElement([
                'Nhac han cong viec',
                'Canh bao cong viec tre han',
                'Ket qua phe duyet',
            ]),
            'body' => fake()->sentence(12),
            'notifiable_type' => $notifiableType,
            'notifiable_id' => $notifiableType === null ? null : fake()->numberBetween(1, 300),
            'status' => $status,
            'sent_at' => $sentAt,
            'error_message' => $status === 'failed' ? 'Khong gui duoc qua kenh Telegram' : null,
            'retry_count' => $status === 'failed' ? fake()->numberBetween(1, 3) : 0,
            'scheduled_at' => $scheduledAt,
            'created_at' => fake()->dateTimeBetween('-7 days', 'now'),
        ];
    }
}
