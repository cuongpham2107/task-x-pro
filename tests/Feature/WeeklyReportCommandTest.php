<?php

use App\Models\User;
use App\Notifications\WeeklySummaryNotification;
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

it('sends weekly summary to leaders and ceos', function () {
    $leader = User::factory()->create(['telegram_id' => '2048746443']);
    $leader->assignRole('leader');

    $ceo = User::factory()->create(['telegram_id' => '2048746443']);
    $ceo->assignRole('ceo');

    Artisan::call('reports:weekly');

    Notification::assertSentTo($leader, WeeklySummaryNotification::class);
    Notification::assertSentTo($ceo, WeeklySummaryNotification::class);
});
