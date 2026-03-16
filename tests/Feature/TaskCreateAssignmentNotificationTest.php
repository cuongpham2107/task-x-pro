<?php

use App\Enums\TaskType;
use App\Models\Phase;
use App\Models\Project;
use App\Models\ProjectLeader;
use App\Models\User;
use App\Notifications\TaskAssignedNotification;
use App\Services\Tasks\TaskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('task.create', 'web');
    Permission::findOrCreate('project.update', 'web');

    $this->service = app(TaskService::class);
});

it('sends telegram notification to pic when task is created', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo('task.create', 'project.update');

    $project = Project::factory()->create(['created_by' => $actor->id]);
    ProjectLeader::factory()->create([
        'project_id' => $project->id,
        'user_id' => $actor->id,
        'assigned_by' => $actor->id,
    ]);
    $phase = Phase::factory()->create(['project_id' => $project->id]);

    $pic = User::factory()->create(['telegram_id' => 'pic-telegram']);

    $attributes = [
        'phase_id' => $phase->id,
        'name' => 'Task moi',
        'type' => TaskType::Technical->value,
        'pic_id' => $pic->id,
        'deadline' => now()->addDays(5),
    ];

    $this->service->create($actor, $attributes);

    Notification::assertSentTo($pic, TaskAssignedNotification::class);
});
