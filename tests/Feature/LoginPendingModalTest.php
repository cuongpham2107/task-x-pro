<?php

use App\Models\User;
use App\Enums\UserStatus;
use Livewire\Volt\Volt;

it('shows pending popup when session has showPendingPopup', function () {
    session()->flash('showPendingPopup', true);

    Volt::test('pages.auth.login')
        ->assertSet('showPendingPopup', true);
});

it('shows pending popup when pending query parameter is present', function () {
    Volt::withQueryParams(['pending' => 1])
        ->test('pages.auth.login')
        ->assertSet('showPendingPopup', true);
});

it('does not show pending popup by default', function () {
    Volt::test('pages.auth.login')
        ->assertSet('showPendingPopup', false);
});
