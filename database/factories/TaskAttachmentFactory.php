<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TaskAttachment>
 */
class TaskAttachmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $extension = fake()->randomElement(['pdf', 'docx', 'xlsx', 'png']);
        $baseName = fake()->slug(3);

        return [
            'task_id' => Task::factory(),
            'uploader_id' => User::factory()->pic(),
            'original_name' => $baseName.'.'.$extension,
            'stored_path' => 'task-attachments/'.date('Y/m').'/'.$baseName.'.'.$extension,
            'disk' => 'local',
            'mime_type' => match ($extension) {
                'pdf' => 'application/pdf',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                default => 'image/png',
            },
            'size_bytes' => fake()->numberBetween(50000, 8000000),
            'version' => fake()->numberBetween(1, 4),
            'google_drive_id' => fake()->optional(0.25)->bothify('gd_################'),
            'created_at' => fake()->dateTimeBetween('-15 days', 'now'),
        ];
    }
}
