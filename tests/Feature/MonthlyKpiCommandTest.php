<?php

use App\Enums\UserStatus;
use App\Models\KpiScore;
use App\Models\User;
use App\Notifications\MonthlyKpiSummaryNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();

    Role::firstOrCreate(['name' => 'leader']);
    Role::firstOrCreate(['name' => 'ceo']);
});

it('syncs monthly kpi and notifies leaders and ceos', function () {
    $leader = User::factory()->create(['telegram_id' => 'leader-telegram']);
    $leader->assignRole('leader');

    $ceo = User::factory()->create(['telegram_id' => 'ceo-telegram']);
    $ceo->assignRole('ceo');

    $userA = User::factory()->create(['status' => UserStatus::Active]);
    $userB = User::factory()->create(['status' => UserStatus::Active]);

    Artisan::call('kpi:monthly-sync');

    expect(KpiScore::query()->where('user_id', $userA->id)->exists())->toBeTrue();
    expect(KpiScore::query()->where('user_id', $userB->id)->exists())->toBeTrue();

    Notification::assertSentTo($leader, MonthlyKpiSummaryNotification::class);
    Notification::assertSentTo($ceo, MonthlyKpiSummaryNotification::class);
});
