<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DocumentVersion>
 */
class DocumentVersionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $extension = fake()->randomElement(['pdf', 'docx', 'xlsx']);

        return [
            'document_id' => Document::factory(),
            'version_number' => fake()->numberBetween(1, 4),
            'uploader_id' => User::factory()->pic(),
            'stored_path' => 'documents/'.date('Y/m').'/'.fake()->uuid().'.'.$extension,
            'google_drive_revision_id' => fake()->optional(0.4)->bothify('rev_############'),
            'change_summary' => fake()->optional(0.75)->sentence(),
            'file_size_bytes' => fake()->numberBetween(120000, 15000000),
            'created_at' => fake()->dateTimeBetween('-30 days', 'now'),
        ];
    }
}
