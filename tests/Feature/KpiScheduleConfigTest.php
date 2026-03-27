<?php

use Illuminate\Console\Scheduling\Schedule;

it('configures kpi schedules to prevent overlap on multi-server', function (): void {
    $events = collect(app(Schedule::class)->events());

    $dailySync = $events->first(function ($event): bool {
        return str_contains((string) $event->command, 'kpi:daily-sync');
    });

    $monthlySync = $events->first(function ($event): bool {
        return str_contains((string) $event->command, 'kpi:monthly-sync');
    });

    expect($dailySync)->not->toBeNull()
        ->and($monthlySync)->not->toBeNull()
        ->and($dailySync->withoutOverlapping)->toBeTrue()
        ->and($dailySync->onOneServer)->toBeTrue()
        ->and($monthlySync->withoutOverlapping)->toBeTrue()
        ->and($monthlySync->onOneServer)->toBeTrue();
});
