<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

it('resets document form without missing properties', function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('document.view', 'web');

    $user = User::factory()->create();
    $user->givePermissionTo('document.view');

    Livewire::actingAs($user)
        ->test('pages::documents.index')
        ->set('editingDocumentId', 10)
        ->set('documentName', 'Tai lieu thu')
        ->call('resetDocumentFormModal')
        ->assertSet('editingDocumentId', null)
        ->assertSet('documentName', '');
});
