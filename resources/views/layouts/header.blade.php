<header
    class="sticky top-0 z-50 flex items-center justify-between whitespace-nowrap border-b border-solid border-slate-200 bg-white px-4 py-3 lg:px-8 dark:border-slate-800 dark:bg-slate-900">
    <div class="flex items-center gap-4 lg:gap-8">
        <button @click="sidebarOpen = true"
            class="rounded-lg p-1 text-slate-600 transition-colors hover:bg-slate-100 lg:hidden dark:text-slate-400 dark:hover:bg-slate-800">
            <span class="material-symbols-outlined text-2xl">menu</span>
        </button>
        <a href="{{route('dashboard.index')}}" class="text-primary hidden items-center gap-3 lg:flex">
            <div class="bg-primary/10 ring-primary/20 flex size-9 items-center justify-center rounded-lg p-1.5 ring-1">
                <img src="{{ asset('images/logo.png') }}" alt="ASG logo" class="size-full object-contain">
            </div>
            <h2 class="text-lg font-bold leading-tight tracking-tight text-slate-900 dark:text-slate-100">
                {{ config('app.name') }}
            </h2>
        </a>
        <div class="relative hidden w-80 2xl:flex" x-data="{
            query: '',
            open: false,
            loading: false,
            projects: [],
            tasks: [],
            debounceTimer: null,
            get hasResults() { return this.projects.length > 0 || this.tasks.length > 0; },
            get empty() { return !this.loading && this.query.trim().length >= 1 && !this.hasResults; },
            search() {
                clearTimeout(this.debounceTimer);
                const q = this.query.trim();
                if (q.length < 1) {
                    this.projects = [];
                    this.tasks = [];
                    return;
                }
                this.loading = true;
                this.debounceTimer = setTimeout(() => {
                    fetch('/api/search?q=' + encodeURIComponent(q))
                        .then(r => r.json())
                        .then(data => {
                            this.projects = data.projects;
                            this.tasks = data.tasks;
                        })
                        .catch(() => {
                            this.projects = [];
                            this.tasks = [];
                        })
                        .finally(() => { this.loading = false; });
                }, 300);
            },
            go(url) {
                this.open = false;
                this.query = '';
                this.projects = [];
                this.tasks = [];
                Livewire.navigate(url);
            },
        }" @click.outside="open = false"
            @keydown.escape.window="open = false">
            <label class="flex h-10 w-full flex-col">
                <div class="flex h-full w-full flex-1 items-stretch rounded-lg">
                    <div
                        class="flex items-center justify-center rounded-l-lg border-none bg-slate-100 pl-4 text-slate-400 dark:bg-slate-800">
                        <span class="material-symbols-outlined text-xl">search</span>
                    </div>
                    <input x-model="query" @focus="open = true" @input="open = true; search()"
                        @keydown.escape="open = false"
                        class="form-input flex h-full w-full min-w-0 flex-1 resize-none overflow-hidden rounded-r-lg border-none bg-slate-100 px-4 pl-2 text-sm font-normal text-slate-900 placeholder:text-slate-500 focus:outline-0 focus:ring-0 dark:bg-slate-800 dark:text-slate-100"
                        placeholder="Tìm kiếm dự án, công việc..." />
                </div>

            </label>
            {{-- Dropdown results --}}
            <div x-show="open && query.trim().length >= 1" x-transition.opacity
                class="absolute left-0 top-full z-50 mt-1.5 max-h-96 overflow-hidden overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-xl dark:border-slate-700 dark:bg-slate-800">

                {{-- Loading --}}
                <template x-if="loading">
                    <div class="flex items-center gap-2 px-4 py-3 text-sm text-slate-500">
                        <svg class="size-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                stroke-width="4">
                            </circle>
                            <path class="opacity-75" fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        Đang tìm kiếm...
                    </div>
                </template>

                {{-- Projects --}}
                <template x-if="!loading && projects.length > 0">
                    <div>
                        <div
                            class="text-2xs bg-slate-50 px-4 py-2 font-bold uppercase tracking-wider text-slate-400 dark:bg-slate-700/50">
                            Dự án</div>
                        <ul>
                            <template x-for="item in projects" :key="'p' + item.id">
                                <li>
                                    <button @click="go(item.url)"
                                        class="flex w-full items-center gap-3 px-4 py-2.5 text-left transition-colors hover:bg-slate-100 dark:hover:bg-slate-700">
                                        <span
                                            class="material-symbols-outlined text-primary/60 text-lg">folder_open</span>
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-medium text-slate-800 dark:text-slate-200"
                                                x-text="item.name"></p>
                                            <p class="truncate text-xs text-slate-500"><span x-text="item.code"></span>
                                                · <span x-text="item.status"></span></p>
                                        </div>
                                    </button>
                                </li>
                            </template>
                        </ul>
                    </div>
                </template>

                {{-- Tasks --}}
                <template x-if="!loading && tasks.length > 0">
                    <div>
                        <div
                            class="text-2xs bg-slate-50 px-4 py-2 font-bold uppercase tracking-wider text-slate-400 dark:bg-slate-700/50">
                            Công việc</div>
                        <ul>
                            <template x-for="item in tasks" :key="'t' + item.id">
                                <li>
                                    <button @click="item.url && go(item.url)"
                                        class="flex w-full items-center gap-3 px-4 py-2.5 text-left transition-colors hover:bg-slate-100 dark:hover:bg-slate-700">
                                        <span
                                            class="material-symbols-outlined text-lg text-emerald-500/70">task_alt</span>
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-medium text-slate-800 dark:text-slate-200"
                                                x-text="item.title"></p>
                                            <p class="truncate text-xs text-slate-500">
                                                <template x-if="item.project"><span x-text="item.project"></span> ·
                                                </template>
                                                <span x-text="item.status"></span>
                                            </p>
                                        </div>
                                    </button>
                                </li>
                            </template>
                        </ul>
                    </div>
                </template>

                {{-- Empty --}}
                <template x-if="empty">
                    <div class="px-4 py-6 text-center">
                        <span
                            class="material-symbols-outlined text-3xl text-slate-300 dark:text-slate-600">search_off</span>
                        <p class="mt-1 text-sm text-slate-500">Không tìm thấy kết quả</p>
                    </div>
                </template>
            </div>


        </div>
    </div>
    <div class="flex flex-1 items-center justify-end gap-4">
        <nav class="hidden items-center gap-4 xl:flex">
            @php
                $navLink = fn(string $routePattern) => request()->routeIs($routePattern)
                    ? 'text-primary text-sm font-semibold leading-normal hover:text-primary/80'
                    : 'text-slate-600 dark:text-slate-400 text-sm font-medium leading-normal hover:text-primary transition-colors';

                // System Config Visibility Logic
                // Phase Template: Check 'viewAny' (mapped to 'phase_template.view').
                $canViewPhaseTemplate = auth()->user()?->can('viewAny', App\Models\PhaseTemplate::class) ?? false;

                // Activity Log: Check 'viewAny' (mapped to 'activity_log.view').
                $canViewActivityLog = auth()->user()?->can('viewAny', App\Models\ActivityLog::class) ?? false;

                // Role: Check 'viewAny' (mapped to 'super_admin').
                $canViewRole = auth()->user()?->can('viewAny', Spatie\Permission\Models\Role::class) ?? false;

                // SLA Config: Check 'viewAny' (mapped to 'sla.view'). Leader has 'sla.view'.
                $canViewSlaConfig = auth()->user()?->can('viewAny', App\Models\SlaConfig::class) ?? false;

                $showSystemConfigGroup =
                    $canViewPhaseTemplate || $canViewActivityLog || $canViewRole || $canViewSlaConfig;
                $isSystemConfigActive =
                    request()->routeIs('phase-templates.*') ||
                    request()->routeIs('activity-logs.*') ||
                    request()->routeIs('roles.*') ||
                    request()->routeIs('sla-configs.*');
            @endphp
            <a class="{{ $navLink('dashboard.index') }} inline-flex items-center gap-1.5"
                href="{{ route('dashboard.index') }}" wire:navigate>
                <span class="material-symbols-outlined text-[18px] leading-none">dashboard</span>
                Tổng quan
            </a>
            @can('viewAny', App\Models\Project::class)
                <a class="{{ $navLink('projects.*') }} inline-flex items-center gap-1.5"
                    href="{{ route('projects.index') }}" wire:navigate>
                    <span class="material-symbols-outlined text-[18px] leading-none">folder_open</span>
                    Dự án
                </a>
            @endcan
            @can('viewAny', App\Models\Task::class)
                <a class="{{ $navLink('tasks.*') }} inline-flex items-center gap-1.5"
                    href="{{ route('tasks.index') }}" wire:navigate>
                    <span class="material-symbols-outlined text-[18px] leading-none">document_scanner</span>
                    Công việc
                </a>
            @endcan
            @can('viewAny', App\Models\KpiScore::class)
                <a class="{{ $navLink('kpi-scores.*') }} inline-flex items-center gap-1.5"
                    href="{{ route('kpi-scores.index') }}" wire:navigate>
                    <span class="material-symbols-outlined text-[18px] leading-none">trending_up</span>
                    KPI & Hiệu xuất
                </a>
            @endcan
            @can('viewAny', App\Models\Document::class)
                <a class="{{ $navLink('documents.*') }} inline-flex items-center gap-1.5"
                    href="{{ route('documents.index') }}" wire:navigate>
                    <span class="material-symbols-outlined text-[18px] leading-none">description</span>
                    Tài liệu
                </a>
            @endcan

            {{-- dropdown-style Danh mục group --}}
            @php
                $canViewDepartments = auth()->user()?->can('create', App\Models\Department::class) ?? false;
                $canViewUsers = auth()->user()?->can('create', App\Models\User::class) ?? false;
            @endphp
            @if ($canViewDepartments || $canViewUsers)
                <div class="relative flex items-center" x-data="{ open: false }" @click.outside="open = false">
                    <button type="button" @click="open = !open"
                        class="{{ (request()->routeIs('departments.*') || request()->routeIs('users.*'))
                            ? 'text-primary text-sm font-semibold leading-normal hover:text-primary/80'
                            : 'text-slate-600 dark:text-slate-400 text-sm font-medium leading-normal hover:text-primary transition-colors' }} inline-flex items-center gap-1">
                        <span class="material-symbols-outlined text-[18px] leading-none">category</span>
                        Danh mục
                        <span class="material-symbols-outlined text-[18px] leading-none transition-transform"
                            :class="open ? 'rotate-180' : ''">expand_more</span>
                    </button>

                    <div x-cloak x-show="open" x-transition:enter="transition ease-out duration-150"
                        x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                        x-transition:leave="transition ease-in duration-100"
                        x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                        x-transition:leave-end="opacity-0 scale-95 -translate-y-1"
                        class="absolute right-0 top-full z-40 mt-2 w-40 space-y-2 rounded-xl border border-slate-200 bg-white p-1.5 shadow-xl dark:border-slate-700 dark:bg-slate-900">
                        @if ($canViewDepartments)
                            <a href="{{ route('departments.index') }}" wire:navigate @click="open = false"
                                class="{{ request()->routeIs('departments.*')
                                    ? 'bg-primary/10 text-primary'
                                    : 'text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800' }} flex items-center rounded-lg px-3 py-2 text-sm transition-colors">
                                <span class="material-symbols-outlined mr-2 text-[18px] leading-none">apartment</span>
                                Phòng ban
                            </a>
                        @endif
                        @if ($canViewUsers)
                            <a href="{{ route('users.index') }}" wire:navigate @click="open = false"
                                class="{{ request()->routeIs('users.*')
                                    ? 'bg-primary/10 text-primary'
                                    : 'text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800' }} flex items-center rounded-lg px-3 py-2 text-sm transition-colors">
                                <span class="material-symbols-outlined mr-2 text-[18px] leading-none">group</span>
                                Người dùng
                            </a>
                        @endif
                    </div>
                </div>
            @endif

            @if ($showSystemConfigGroup)
                <div class="relative flex items-center" x-data="{ open: false }" @click.outside="open = false">
                    <button type="button" @click="open = !open"
                        class="{{ $isSystemConfigActive
                            ? 'text-primary text-sm font-semibold leading-normal hover:text-primary/80'
                            : 'text-slate-600 dark:text-slate-400 text-sm font-medium leading-normal hover:text-primary transition-colors' }} inline-flex items-center gap-1">
                        <span class="material-symbols-outlined text-[18px] leading-none">settings</span>
                        Hệ thống
                        <span class="material-symbols-outlined text-[18px] leading-none transition-transform"
                            :class="open ? 'rotate-180' : ''">expand_more</span>
                    </button>

                    <div x-cloak x-show="open" x-transition:enter="transition ease-out duration-150"
                        x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                        x-transition:leave="transition ease-in duration-100"
                        x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                        x-transition:leave-end="opacity-0 scale-95 -translate-y-1"
                        class="absolute right-0 top-full z-40 mt-2 w-52 space-y-2 rounded-xl border border-slate-200 bg-white p-1.5 shadow-xl dark:border-slate-700 dark:bg-slate-900">
                        @if ($canViewSlaConfig)
                            <a href="{{ route('sla-configs.index') }}" wire:navigate @click="open = false"
                                class="{{ request()->routeIs('sla-configs.*')
                                    ? 'bg-primary/10 text-primary'
                                    : 'text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800' }} flex items-center rounded-lg px-3 py-2 text-sm transition-colors">
                                <span class="material-symbols-outlined mr-2 text-[18px] leading-none">schedule</span>
                                Cấu hình SLA
                            </a>
                        @endif
                        @if ($canViewPhaseTemplate)
                            <a href="{{ route('phase-templates.index') }}" wire:navigate @click="open = false"
                                class="{{ request()->routeIs('phase-templates.*')
                                    ? 'bg-primary/10 text-primary'
                                    : 'text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800' }} flex items-center rounded-lg px-3 py-2 text-sm transition-colors">
                                <span class="material-symbols-outlined mr-2 text-[18px] leading-none">schema</span>
                                Mẫu phase
                            </a>
                        @endif

                        @if ($canViewActivityLog)
                            <a href="{{ route('activity-logs.index') }}" wire:navigate @click="open = false"
                                class="{{ request()->routeIs('activity-logs.*')
                                    ? 'bg-primary/10 text-primary'
                                    : 'text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800' }} flex items-center rounded-lg px-3 py-2 text-sm transition-colors">
                                <span class="material-symbols-outlined mr-2 text-[18px] leading-none">history</span>
                                Nhật ký
                            </a>
                        @endif

                        @if ($canViewRole)
                            <a href="{{ route('roles.index') }}" wire:navigate @click="open = false"
                                class="{{ request()->routeIs('roles.*')
                                    ? 'bg-primary/10 text-primary'
                                    : 'text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800' }} flex items-center rounded-lg px-3 py-2 text-sm transition-colors">
                                <span
                                    class="material-symbols-outlined mr-2 text-[18px] leading-none">shield_person</span>
                                Phân quyền
                            </a>
                        @endif


                    </div>
            @endif
        </nav>
        <div class="flex gap-2" x-data>
            <livewire:notification.badge />
            <!-- <button
                class="flex size-10 items-center justify-center rounded-lg bg-slate-100 text-slate-700 transition-colors hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700">
                <span class="material-symbols-outlined">settings</span>
            </button> -->
        </div>
        @auth
            <div class="relative hidden border-l border-slate-200 pl-2 lg:block dark:border-slate-800"
                x-data="{ open: false, timeout: null }">
                {{-- Trigger --}}
                <button @click="open = !open"
                    class="flex cursor-pointer items-center gap-2.5 transition-opacity hover:opacity-80">
                    <div class="bg-primary/10 border-primary/20 rounded-full border p-0.5">
                        @if (auth()->user()->avatar_url)
                            <img class="aspect-square size-8 rounded-full object-cover" alt="{{ auth()->user()->name }}"
                                src="{{ auth()->user()->avatar_url }}" />
                        @else
                            <div
                                class="bg-primary flex aspect-square size-8 items-center justify-center rounded-full text-sm font-bold text-white">
                                {{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}
                            </div>
                        @endif
                    </div>
                    <div class="hidden flex-col text-left leading-tight xl:flex">
                        <span
                            class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ auth()->user()->name }}</span>
                        <span class="text-xs text-slate-500 dark:text-slate-400">{{ auth()->user()->email }}</span>
                    </div>
                    <span class="material-symbols-outlined text-lg text-slate-400 transition-transform duration-200"
                        :class="open ? 'rotate-180' : ''">expand_more</span>
                </button>

                {{-- Dropdown --}}
                <div x-cloak x-show="open" x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-100"
                    x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 translate-y-1"
                    class="absolute right-0 top-full z-50 mt-2 w-56 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl dark:border-slate-700 dark:bg-slate-800"
                    @click.outside="open = false">

                    {{-- User info --}}
                    <div class="border-b border-slate-100 px-4 py-3 dark:border-slate-700">
                        <p class="truncate text-sm font-semibold text-slate-900 dark:text-slate-100">
                            {{ auth()->user()->name }}
                        </p>
                        <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ auth()->user()->email }}</p>
                    </div>

                    {{-- Menu items --}}
                    <div class="py-1.5">
                        <a href="{{ route('users.show', auth()->id()) }}" wire:navigate @click="open = false"
                            class="flex items-center gap-3 px-4 py-2.5 text-sm text-slate-700 transition-colors hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-700/50">
                            <span class="material-symbols-outlined text-lg text-slate-400">person</span>
                            Tài khoản của tôi
                        </a>
                    </div>

                    {{-- Logout --}}
                    <div class="border-t border-slate-100 py-1.5 dark:border-slate-700">
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit"
                                class="flex w-full items-center gap-3 px-4 py-2.5 text-sm text-red-600 transition-colors hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20">
                                <span class="material-symbols-outlined text-lg">logout</span>
                                Đăng xuất
                            </button>
                        </form>
                    </div>
                </div>
            @endauth
        </div>
</header>
