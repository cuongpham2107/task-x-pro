<?php

namespace Tests\Feature\Notifications;

use App\Models\Phase;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\ApprovalResults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovalResultsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_skips_button_when_app_url_is_localhost(): void
    {
        config(['app.url' => 'http://localhost:8000']);

        $project = Project::factory()->create();
        $phase = Phase::factory()->create(['project_id' => $project->id]);
        $task = Task::factory()->create(['phase_id' => $phase->id]);

        $leader = User::factory()->create();
        $ceo = User::factory()->create(['telegram_id' => '123456789']);

        $notification = new ApprovalResults($task, $leader);
        $payload = $notification->toTelegram($ceo)->toArray();

        $this->assertArrayNotHasKey('reply_markup', $payload);
    }
}
