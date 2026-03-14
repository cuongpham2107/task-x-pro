<?php

use App\Models\User;
use App\Notifications\WeeklySummaryNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use NotificationChannels\Telegram\TelegramChannel;

uses(RefreshDatabase::class);

it('includes mail channel for weekly summary when email exists', function () {
    $user = User::factory()->create([
        'telegram_id' => null,
        'email' => 'leader@example.com',
    ]);

    $notification = new WeeklySummaryNotification(
        [
            'completed' => 3,
            'late' => 1,
            'waiting_approval' => 2,
            'due_soon' => 4,
        ],
        Carbon::parse('2025-01-01'),
        Carbon::parse('2025-01-07')
    );

    $channels = $notification->via($user);

    expect($channels)->toContain('mail')
        ->and($channels)->not->toContain(TelegramChannel::class);
});
