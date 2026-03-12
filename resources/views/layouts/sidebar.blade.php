@php
    $navLink = fn(string $routePattern) => request()->routeIs($routePattern)
        ? 'bg-primary/10 text-primary font-bold'
        : 'text-slate-600 dark:text-slate-400 font-medium hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors';

    // System Config Visibility Logic
    $canViewPhaseTemplate = auth()->user()?->can('viewAny', App\Models\PhaseTemplate::class) ?? false;
    $canViewActivityLog = auth()->user()?->can('viewAny', App\Models\ActivityLog::class) ?? false;
    $canViewRole = auth()->user()?->can('viewAny', Spatie\Permission\Models\Role::class) ?? false;
    $canViewSlaConfig = auth()->user()?->can('viewAny', App\Models\SlaConfig::class) ?? false;

    $showSystemConfigGroup = $canViewPhaseTemplate || $canViewActivityLog || $canViewRole || $canViewSlaConfig;
@endphp

<!-- Sidebar Sidebar/Drawer Overlay -->
<div x-cloak x-show="sidebarOpen" class="z-60 fixed inset-0 xl:hidden" role="dialog" aria-modal="true">
    <!-- Backdrop -->
    <div x-show="sidebarOpen" x-transition:enter="transition-opacity ease-linear duration-300"
        x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity ease-linear duration-300" x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm"
        @click="sidebarOpen = false"></div>

    <!-- Sidebar Content -->
    <div x-show="sidebarOpen" x-transition:enter="transition ease-in-out duration-300 transform"
        x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
        x-transition:leave="transition ease-in-out duration-300 transform" x-transition:leave-start="translate-x-0"
        x-transition:leave-end="-translate-x-full"
        class="relative flex h-full w-4/5 max-w-sm flex-col bg-white shadow-2xl dark:bg-slate-950">

        <div class="border-b border-slate-100 p-6 dark:border-slate-800">
            <div class="mb-6 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div
                        class="bg-primary/10 ring-primary/20 flex size-9 items-center justify-center rounded-lg p-1.5 ring-1">
                        <img src="{{ asset('images/LOGO.png') }}" alt="ASG logo" class="size-full object-contain">
                    </div>
                    <h2 class="text-primary text-xl font-bold">{{ config('app.name') }}</h2>
                </div>
                <button @click="sidebarOpen = false"
                    class="text-slate-400 transition-colors hover:text-slate-600 dark:hover:text-slate-200">
                    <span class="material-symbols-outlined text-2xl">close</span>
                </button>
            </div>

            @auth
                <div class="flex items-center gap-3">
                    <div class="bg-primary/10 border-primary/20 rounded-full border p-0.5">
                        @if (auth()->user()->avatar_url)
                            <img class="aspect-square size-10 rounded-full object-cover" alt="{{ auth()->user()->name }}"
                                src="{{ auth()->user()->avatar_url }}" />
                        @else
                            <div
                                class="bg-primary flex aspect-square size-10 items-center justify-center rounded-full text-base font-bold text-white">
                                {{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}
                            </div>
                        @endif
                    </div>
                    <div class="flex min-w-0 flex-col">
                        <p class="truncate text-sm font-bold text-slate-900 dark:text-white">{{ auth()->user()->name }}</p>
                        <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ auth()->user()->email }}</p>
                    </div>
                </div>
            @endauth
        </div>

        <div class="flex-1 space-y-1.5 overflow-y-auto p-4">
            <a class="{{ $navLink('dashboard.index') }} flex items-center gap-4 rounded-xl px-4 py-3"
                href="{{ route('dashboard.index') }}" wire:navigate @click="sidebarOpen = false">
                <span class="material-symbols-outlined">dashboard</span>
                <span class="text-base">Tổng quan</span>
            </a>

            @can('viewAny', App\Models\Project::class)
                <a class="{{ $navLink('projects.*') }} flex items-center gap-4 rounded-xl px-4 py-3"
                    href="{{ route('projects.index') }}" wire:navigate @click="sidebarOpen = false">
                    <span class="material-symbols-outlined">folder_open</span>
                    <span class="text-base">Dự án</span>
                </a>
            @endcan
            @can('viewAny', App\Models\KpiScore::class)
                <a class="{{ $navLink('kpi-scores.*') }} flex items-center gap-4 rounded-xl px-4 py-3"
                    href="{{ route('kpi-scores.index') }}" wire:navigate @click="sidebarOpen = false">
                    <span class="material-symbols-outlined">trending_up</span>
                    <span class="text-base">KPI & Hiệu xuất</span>
                </a>
            @endcan
            @can('viewAny', App\Models\Document::class)
                <a class="{{ $navLink('documents.*') }} flex items-center gap-4 rounded-xl px-4 py-3"
                    href="{{ route('documents.index') }}" wire:navigate @click="sidebarOpen = false">
                    <span class="material-symbols-outlined">description</span>
                    <span class="text-base">Tài liệu</span>
                </a>
            @endcan

            {{-- Danh mục section --}}
            @if(auth()->user()?->can('create', App\Models\Department::class) || auth()->user()?->can('create', App\Models\User::class))
                <div class="mt-4 border-t border-slate-100 pt-4 dark:border-slate-800">
                    <p class="mb-2 px-4 text-2xs font-bold uppercase tracking-widest text-slate-400">
                        Danh mục
                    </p>
                    @can('create', App\Models\Department::class)
                        <a class="{{ $navLink('departments.*') }} flex items-center gap-4 rounded-xl px-4 py-3"
                            href="{{ route('departments.index') }}" wire:navigate @click="sidebarOpen = false">
                            <span class="material-symbols-outlined">apartment</span>
                            <span class="text-base">Phòng ban</span>
                        </a>
                    @endcan
                    @can('create', App\Models\User::class)
                        <a class="{{ $navLink('users.*') }} flex items-center gap-4 rounded-xl px-4 py-3"
                            href="{{ route('users.index') }}" wire:navigate @click="sidebarOpen = false">
                            <span class="material-symbols-outlined">group</span>
                            <span class="text-base">Người dùng</span>
                        </a>
                    @endcan
                </div>
            @endif

            @if ($showSystemConfigGroup)
                <div class="mt-4 border-t border-slate-100 pt-4 dark:border-slate-800">
                    <p class="mb-2 px-4 text-2xs font-bold uppercase tracking-widest text-slate-400">
                        Hệ thống
                    </p>
                    @if ($canViewSlaConfig)
                        <a class="{{ request()->routeIs('sla-configs.*') ? 'bg-primary/10 text-primary font-bold' : 'text-slate-600 dark:text-slate-400 font-medium hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors' }} flex items-center gap-4 rounded-xl px-4 py-3"
                            href="{{ route('sla-configs.index') }}" wire:navigate @click="sidebarOpen = false">
                            <span class="material-symbols-outlined">schedule</span>
                            <span class="text-base">Cấu hình SLA</span>
                        </a>
                    @endif
                    @if ($canViewPhaseTemplate)
                        <a class="{{ request()->routeIs('phase-templates.*') ? 'bg-primary/10 text-primary font-bold' : 'text-slate-600 dark:text-slate-400 font-medium hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors' }} flex items-center gap-4 rounded-xl px-4 py-3"
                            href="{{ route('phase-templates.index') }}" wire:navigate @click="sidebarOpen = false">
                            <span class="material-symbols-outlined">schema</span>
                            <span class="text-base">Mẫu phase</span>
                        </a>
                    @endif

                    @if ($canViewActivityLog)
                        <a class="{{ request()->routeIs('activity-logs.*') ? 'bg-primary/10 text-primary font-bold' : 'text-slate-600 dark:text-slate-400 font-medium hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors' }} flex items-center gap-4 rounded-xl px-4 py-3"
                            href="{{ route('activity-logs.index') }}" wire:navigate @click="sidebarOpen = false">
                            <span class="material-symbols-outlined">history</span>
                            <span class="text-base">Nhật ký</span>
                        </a>
                    @endif

                    @if ($canViewRole)
                        <a class="{{ request()->routeIs('roles.*') ? 'bg-primary/10 text-primary font-bold' : 'text-slate-600 dark:text-slate-400 font-medium hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors' }} flex items-center gap-4 rounded-xl px-4 py-3"
                            href="{{ route('roles.index') }}" wire:navigate @click="sidebarOpen = false">
                            <span class="material-symbols-outlined">shield_person</span>
                            <span class="text-base">Phân quyền</span>
                        </a>
                    @endif


                </div>
            @endif
        </div>

        @auth
            <div class="mt-auto border-t border-slate-100 p-4 dark:border-slate-800">
                <div class="space-y-1.5">
                    <a href="{{ route('users.show', auth()->id()) }}" wire:navigate @click="sidebarOpen = false"
                        class="flex items-center gap-4 rounded-xl px-4 py-3 text-slate-600 transition-colors hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-slate-800">
                        <span class="material-symbols-outlined">person</span>
                        <span class="text-base font-medium">Tài khoản của tôi</span>
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"
                            class="flex w-full items-center gap-4 rounded-xl px-4 py-3 font-bold text-red-500 transition-colors hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20">
                            <span class="material-symbols-outlined">logout</span>
                            <span class="text-base">Đăng xuất</span>
                        </button>
                    </form>
                </div>
            </div>
        @endauth
    </div>
</div>
