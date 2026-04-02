<?php

use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\Document;
use App\Models\PhaseTemplate;
use App\Models\Project;
use App\Models\SlaConfig;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;

// Guest-only routes (redirect to dashboard if already authenticated)
Route::middleware('guest')->group(function () {
    Route::livewire('/auth/login', 'pages::auth.login')->name('login');
});

// Social Login & Linking Routes (Accessible to both Guest and Auth)
Route::get('/auth/{driver}/redirect', function (string $driver) {
    return \Laravel\Socialite\Facades\Socialite::driver($driver)->redirect();
})->name('social.redirect');

Route::get('/auth/{driver}/callback', function (string $driver, \App\Services\Auth\AuthService $authService) {
    try {
        $isLinking = Auth::check();
        $user = $authService->handleSocialCallback($driver);

        if ($isLinking) {
            return redirect()->route('users.show', $user->id)->with('success', 'Đã liên kết tài khoản '.$driver.' thành công!');
        }

        if ($user->status === \App\Enums\UserStatus::Pending || $user->status === 'pending') {
            return redirect()->route('login', ['pending' => 1])->with('showPendingPopup', true);
        }

        Auth::login($user, true);
        session()->regenerate();

        return redirect()->intended(route('dashboard.index'));
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error('Social auth failed: '.$e->getMessage(), [
            'driver' => $driver,
            'exception' => $e,
        ]);

        if (Auth::check()) {
            return redirect()->route('users.show', Auth::id())->with('error', 'Lỗi liên kết tài khoản: '.$e->getMessage());
        }

        return redirect()->route('login')->with('error', 'Đăng nhập không thành công: '.$e->getMessage());
    }
})->name('social.callback');

// Auth-only routes (redirect to login if not authenticated)
Route::middleware('auth')->group(function () {
    // Logout route
    Route::post('/logout', function () {
        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();

        return redirect()->route('login');
    })->name('logout');

    Route::livewire('/dashboard', 'pages::dashboard.index')->name('dashboard.index');

    Route::prefix('projects')->name('projects.')->group(function () {
        Route::livewire('/', 'pages::projects.index')
            ->middleware('can:viewAny,'.Project::class)
            ->name('index');
        Route::livewire('/{project}/phases', 'pages::phases.index')
            ->middleware('can:view,project')
            ->name('phases.index');
        Route::livewire('/{project}/phases/{phase}/tasks', 'pages::tasks.index')
            ->middleware('can:view,phase')
            ->name('phases.tasks.index');
    });
    Route::prefix('tasks')
        ->name('tasks.')
        ->middleware('can:viewAny,'.Task::class)
        ->group(function () {
            Route::livewire('/', 'pages::tasks.table')->name('index');
        });
    Route::prefix('documents')
        ->name('documents.')
        ->middleware('can:viewAny,'.Document::class)
        ->group(function () {
            Route::livewire('/', 'pages::documents.index')->name('index');
        });

    Route::prefix('departments')
        ->name('departments.')
        ->middleware('can:viewAny,'.Department::class)
        ->group(function () {
            Route::livewire('/', 'pages::departments.index')->name('index');
        });

    Route::prefix('users')
        ->name('users.')
        ->group(function () {
            Route::livewire('/', 'pages::users.index')
                ->middleware('can:viewAny,'.User::class)
                ->name('index');
            Route::livewire('/{user}', 'pages::users.show')
                ->name('show');
        });

    Route::prefix('roles')
        ->name('roles.')
        ->middleware('can:viewAny,'.Role::class)
        ->group(function () {
            Route::livewire('/', 'pages::roles.index')->name('index');
        });

    Route::prefix('phase-templates')
        ->name('phase-templates.')
        ->middleware('can:viewAny,'.PhaseTemplate::class)
        ->group(function () {
            Route::livewire('/', 'pages::phase-templates.index')->name('index');
        });

    Route::prefix('sla-configs')
        ->name('sla-configs.')
        ->middleware('can:viewAny,'.SlaConfig::class)
        ->group(function () {
            Route::livewire('/', 'pages::sla-configs.index')->name('index');
        });

    Route::prefix('activity-logs')
        ->name('activity-logs.')
        ->middleware('can:viewAny,'.ActivityLog::class)
        ->group(function () {
            Route::livewire('/', 'pages::activity-logs.index')->name('index');
        });
    Route::prefix('kpi-scores')
        ->name('kpi-scores.')
        ->middleware('can:viewAny,'.App\Models\KpiScore::class)
        ->group(function () {
            Route::livewire('/', 'pages::kpi-scores.index')->name('index');
        });

    Route::get('/api/search', \App\Http\Controllers\Api\GlobalSearchController::class)->name('api.search');
});

Route::fallback(function () {
    return redirect()->route('dashboard.index');
});
