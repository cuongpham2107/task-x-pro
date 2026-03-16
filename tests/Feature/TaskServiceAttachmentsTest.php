<?php

use App\Models\Task;
use App\Models\User;
use App\Services\Tasks\TaskService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('skips null uploaded files when adding attachments', function () {
    $service = app(TaskService::class);
    $task = Task::factory()->create();
    $actor = User::factory()->create();

    $attachments = $service->addAttachments($actor, $task, [null]);

    expect($attachments)->toHaveCount(0);
});
