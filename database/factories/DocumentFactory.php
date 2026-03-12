<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Document>
 */
class DocumentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'task_id' => null,
            'uploader_id' => User::factory()->pic(),
            'name' => fake()->randomElement([
                'Tai lieu nghiem thu',
                'Bao cao tien do',
                'Mau bieu van hanh',
                'Huong dan quy trinh',
            ]).' '.fake()->numberBetween(1, 99),
            'document_type' => fake()->randomElement(['sop', 'form', 'quote', 'contract', 'technical', 'deliverable', 'other']),
            'description' => fake()->optional()->sentence(),
            'google_drive_id' => fake()->optional(0.35)->bothify('drv_################'),
            'google_drive_url' => fake()->optional(0.35)->url(),
            'current_version' => fake()->numberBetween(1, 3),
            'permission' => fake()->randomElement(['view', 'edit', 'share']),
        ];
    }
}
