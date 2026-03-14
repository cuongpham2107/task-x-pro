<?php

use App\Enums\UserStatus;
use App\Models\KpiScore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

it('syncs daily kpi for active users', function () {
    $userA = User::factory()->create(['status' => UserStatus::Active]);
    $userB = User::factory()->create(['status' => UserStatus::Active]);

    Artisan::call('kpi:daily-sync');

    expect(KpiScore::query()->where('user_id', $userA->id)->exists())->toBeTrue();
    expect(KpiScore::query()->where('user_id', $userB->id)->exists())->toBeTrue();
});
