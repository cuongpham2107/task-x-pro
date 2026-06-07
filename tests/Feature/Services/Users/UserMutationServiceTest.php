<?php

use App\Enums\UserStatus;
use App\Models\Department;
use App\Models\Project;
use App\Models\ProjectLeader;
use App\Models\User;
use App\Services\Users\UserMutationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    Gate::before(fn () => true);
});

it('keeps existing profile data when updating only the password', function () {
    $department = Department::factory()->create();
    $actor = User::factory()->create();
    $targetUser = User::factory()->create([
        'department_id' => $department->id,
        'status' => UserStatus::OnLeave,
        'password' => Hash::make('old-password'),
    ]);

    $service = app(UserMutationService::class);

    $updatedUser = $service->update($actor, $targetUser, [
        'password' => 'new-password',
    ]);

    expect(Hash::check('new-password', $updatedUser->password))->toBeTrue();
    expect($updatedUser->department_id)->toBe($department->id);
    expect($updatedUser->status)->toBe(UserStatus::OnLeave);
    expect($updatedUser->name)->toBe($targetUser->name);
    expect($updatedUser->email)->toBe($targetUser->email);
    expect($updatedUser->phone)->toBe($targetUser->phone);
    expect($updatedUser->job_title)->toBe($targetUser->job_title);
    expect($updatedUser->telegram_id)->toBe($targetUser->telegram_id);
});

it('prevents deletion of user with project leader references', function () {
    $actor = User::factory()->create();
    $targetUser = User::factory()->create();
    $project = Project::factory()->create(['created_by' => $actor->id]);

    ProjectLeader::factory()->create([
        'project_id' => $project->id,
        'user_id' => $actor->id,
        'assigned_by' => $targetUser->id,
    ]);

    $service = app(UserMutationService::class);

    expect(fn () => $service->delete($actor, $targetUser))
        ->toThrow(Illuminate\Validation\ValidationException::class, 'Không thể xóa người dùng này vì còn dữ liệu tham chiếu.');

    expect(User::query()->whereKey($targetUser->id)->exists())->toBeTrue();
});
