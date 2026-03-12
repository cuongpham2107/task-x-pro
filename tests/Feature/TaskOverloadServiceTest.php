<?php

use App\Enums\TaskStatus;
use App\Models\Phase;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\PicOverloadWarningNotification;
use App\Services\Tasks\TaskOverloadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();

    $this->service = new TaskOverloadService;
    $this->project = Project::factory()->create();
    $this->phase = Phase::factory()->create(['project_id' => $this->project->id]);
    $this->actor = User::factory()->create();
    $this->pic = User::factory()->create(['telegram_id' => '123456789']);
});

it('sends telegram warning to pic when overload detected', function () {
    $deadline = now()->addDay();

    $task = Task::factory()->create([
        'phase_id' => $this->phase->id,
        'pic_id' => $this->pic->id,
        'status' => TaskStatus::InProgress,
        'deadline' => $deadline,
    ]);

    Task::factory()->count(3)->create([
        'phase_id' => $this->phase->id,
        'pic_id' => $this->pic->id,
        'status' => TaskStatus::InProgress,
        'deadline' => $deadline->copy()->addHours(3),
    ]);

    $this->service->warnIfPicOverloaded($this->actor, $task);

    Notification::assertSentTo($this->pic, PicOverloadWarningNotification::class);
});

it('does not send telegram warning when pic has no telegram id', function () {
    $this->pic->forceFill(['telegram_id' => null])->save();

    $deadline = now()->addDay();

    $task = Task::factory()->create([
        'phase_id' => $this->phase->id,
        'pic_id' => $this->pic->id,
        'status' => TaskStatus::InProgress,
        'deadline' => $deadline,
    ]);

    Task::factory()->count(3)->create([
        'phase_id' => $this->phase->id,
        'pic_id' => $this->pic->id,
        'status' => TaskStatus::InProgress,
        'deadline' => $deadline->copy()->addHours(3),
    ]);

    $this->service->warnIfPicOverloaded($this->actor, $task);

    Notification::assertNotSentTo($this->pic, PicOverloadWarningNotification::class);
});
