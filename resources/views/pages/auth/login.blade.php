<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Layout('layouts.auth')] #[Title('Đăng nhập')] class extends Component {
    #[Validate('required|email', as: 'email')]
    public string $email = '';

    #[Validate('required|min:6', as: 'mật khẩu')]
    public string $password = '';

    public bool $remember = false;

    public bool $showPassword = false;

    public bool $showPendingPopup = false;

    #[Url]
    public bool $pending = false;

    protected \App\Services\Auth\AuthService $authService;

    public function boot(\App\Services\Auth\AuthService $authService): void
    {
        $this->authService = $authService;
    }

    public function mount(): void
    {
        if (session()->has('showPendingPopup') || $this->pending) {
            $this->showPendingPopup = true;
        }
    }

    public function login(): void
    {
        $this->validate();

        if (!$this->authService->login($this->email, $this->password, $this->remember)) {
            throw ValidationException::withMessages([
                'email' => 'Thông tin đăng nhập không chính xác.',
            ]);
        }

        $user = Auth::user();
        if ($user->status === \App\Enums\UserStatus::Pending || $user->status === 'pending') {
            Auth::logout();
            session()->invalidate();
            session()->regenerateToken();

            $this->showPendingPopup = true;

            return;
        }

        session()->regenerate();
        $this->redirect(route('dashboard.index'), navigate: true);
    }

    public function togglePasswordVisibility(): void
    {
        $this->showPassword = !$this->showPassword;
    }
}; ?>

<div>
    <x-slot:title>Đăng nhập - ProManager</x-slot:title>

    <x-slot:header>
        <div>
            <h2 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-slate-100">Chào mừng trở lại 👋
            </h2>
            <p class="mt-1.5 text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                Vui lòng nhập thông tin để truy cập hệ thống
            </p>
        </div>
    </x-slot:header>

    <!-- Status Messages -->
    @if (session('status'))
        <div
            class="mb-4 rounded-lg bg-emerald-50 p-4 text-sm text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400">
            {{ session('status') }}
        </div>
    @endif

    @if (session('error'))
        <div class="mb-4 rounded-lg bg-rose-50 p-4 text-sm text-rose-600 dark:bg-rose-900/30 dark:text-rose-400">
            {{ session('error') }}
        </div>
    @endif
    <!-- Form -->
    <form wire:submit="login" class="flex flex-col gap-4">
        {{-- Email --}}
        <x-ui.input label="Email" name="email" type="email" wire:model="email" placeholder="Nhập email của bạn"
            icon="mail" autofocus />

        {{-- Password --}}
        <x-ui.input label="Mật khẩu" name="password" type="{{ $showPassword ? 'text' : 'password' }}"
            wire:model="password" placeholder="Nhập mật khẩu của bạn" icon="lock">
            <x-slot:suffix>
                <button type="button" wire:click="togglePasswordVisibility"
                    class="material-symbols-outlined text-[20px] text-slate-400 transition-colors hover:text-slate-600 dark:hover:text-slate-300">
                    {{ $showPassword ? 'visibility_off' : 'visibility' }}
                </button>
            </x-slot:suffix>
        </x-ui.input>

        <!-- Remember & Forgot -->
        <div class="flex items-center justify-between">
            <label class="flex cursor-pointer select-none items-center gap-2">
                <input wire:model="remember" type="checkbox"
                    class="text-primary focus:ring-primary/30 size-4 rounded border-slate-300 dark:border-slate-600 dark:bg-slate-800" />
                <span class="text-sm text-slate-600 dark:text-slate-400">Ghi nhớ đăng nhập</span>
            </label>
            <!-- <a href="#" class="text-primary hover:text-primary/80 text-sm font-semibold transition-colors">
                Quên mật khẩu?
            </a> -->
        </div>

        <!-- Submit Button -->
        <x-ui.button type="submit" :full="true" size="xl" loading="login"
            class="rounded-xl! py-3! font-bold! text-base! shadow-primary/25 hover:shadow-primary/40 mt-1 shadow-lg transition-shadow">
            Đăng nhập
        </x-ui.button>
    </form>

    <!-- Divider -->
    <div class="relative py-4">
        <div class="absolute inset-0 flex items-center">
            <div class="w-full border-t border-slate-200 dark:border-slate-800"></div>
        </div>
        <div class="relative flex justify-center text-xs uppercase">
            <span class="dark:bg-background-dark bg-white px-4 font-medium text-slate-500">Hoặc đăng nhập bằng</span>
        </div>
    </div>
    <!-- Social Logins -->
    <div class="flex flex-col items-center justify-center gap-4">
        {!! Socialite::driver('telegram')->getButton() !!}
    </div>

    {{-- Pending Approval Modal --}}
    <x-ui.modal wire:model="showPendingPopup" maxWidth="md">
        <div class="flex flex-col items-center gap-4 py-4 text-center">
            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/30">
                <span
                    class="material-symbols-outlined text-4xl text-emerald-600 dark:text-emerald-400">check_circle</span>
            </div>

            <div class="space-y-2">
                <h3 class="text-xl font-bold text-slate-900 dark:text-slate-100">Đăng ký thành công</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Bạn đã đăng ký thành công, đang chờ admin xét duyệt
                </p>
            </div>

            <x-ui.button variant="primary" :full="true" @click="isOpen = false" class="mt-2">
                Đã hiểu
            </x-ui.button>
        </div>
    </x-ui.modal>

</div>
