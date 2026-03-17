<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('user avatar upload updates stored avatar url', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('avatar.jpg', 300, 300);

    Livewire::actingAs($user)
        ->test('pages::users.show', ['user' => $user])
        ->set('editName', $user->name)
        ->set('editEmail', $user->email)
        ->set('newAvatar', $file)
        ->call('saveUser')
        ->assertSet('showEditModal', false);

    $user->refresh();

    expect($user->avatar)->not->toBeNull();
    expect($user->avatar)->toContain('/storage/');

    $baseUrl = Storage::disk('public')->url('');
    $relativePath = Str::after($user->avatar, $baseUrl);

    Storage::disk('public')->assertExists(ltrim($relativePath, '/'));
});
