<?php

use App\Models\User;
use App\Services\Dashboard\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    Role::findOrCreate('pic', 'web');
    Role::findOrCreate('leader', 'web');
    Role::findOrCreate('ceo', 'web');
    Role::findOrCreate('super_admin', 'web');

    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('exports the ceo dashboard report for the whole company', function (): void {
    $ceo = User::factory()->ceo()->create();
    $ceo->assignRole('ceo');
    $data = app(DashboardService::class)->getIndexData($ceo);

    Excel::fake();

    Livewire::actingAs($ceo)
        ->test('dashboard.ceo-view', ['data' => $data])
        ->call('exportReport', 'xlsx')
        ->assertDispatched('toast');

    Excel::assertDownloaded('dashboard-ceo-'.now()->format('Y-m-d-His').'.xlsx');
});
