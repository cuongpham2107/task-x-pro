@props([
    'users' => collect(), // Collection of User models
    'max' => 4, // Max avatars to show before +N
    'size' => 7, // Tailwind size unit (e.g. 7 = size-7 = 28px)
    'placement' => 'top', // top | bottom
])

@php
    $users = collect($users);
    $shown = $users->take($max);
    $extra = $users->count() - $shown->count();
    $ring = "size-{$size} rounded-full ring-2 ring-white dark:ring-slate-900";
    $__avatarColorOptions = [
        'bg-blue-600 text-white',
        'bg-emerald-600 text-white',
        'bg-amber-500 text-white',
        'bg-indigo-600 text-white',
        'bg-purple-600 text-white',
        'bg-pink-600 text-white',
        'bg-rose-600 text-white',
        'bg-teal-600 text-white',
        'bg-slate-700 text-white',
    ];
@endphp

@if ($users->isEmpty())
    <span class="text-xs text-slate-400">—</span>
@else
    <div class="flex items-center gap-1.5">
        <div class="flex -space-x-2">
            @foreach ($shown as $user)
                @php
                    $key = $user->id ?? $user->email ?? $user->name;
                    $hash = is_int($key) ? (int) $key : crc32((string) $key);
                    $avatarColorClass = $__avatarColorOptions[$hash % count($__avatarColorOptions)];
                @endphp
                <div class="relative shrink-0" x-data="{
                    show: false,
                    pos: { top: 0, left: 0 },
                    placement: @js($placement),
                    updatePos() {
                        const rect = $el.getBoundingClientRect();
                        this.pos = {
                            top: this.placement === 'top' ? rect.top : rect.bottom,
                            left: rect.left + (rect.width / 2)
                        };
                    }
                }" @mouseenter="updatePos(); show = true"
                    @mouseleave="show = false">
                    @if ($user->avatar_url)
                        <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}"
                            class="{{ $ring }} object-cover" />
                    @else
                        <div
                            class="{{ $ring }} {{ $avatarColorClass }} flex items-center justify-center text-[11px] font-bold">
                            {{ strtoupper(substr($user->name, 0, 1)) }}
                        </div>
                    @endif

                    {{-- Rich Popover - Teleported to Body to escape overflow:hidden --}}
                    <template x-teleport="body">
                        <div x-show="show" x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-150"
                            x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                            class="z-9999 fixed w-64 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl dark:border-slate-700 dark:bg-slate-800"
                            :style="`top: ${pos.top + (placement === 'top' ? -12 : 12)}px; left: ${pos.left}px; transform: translate(-50%, ${placement === 'top' ? '-100%' : '0'});`"
                            style="display: none;">
                            <div class="p-4">
                                <div class="mb-3 flex items-start justify-between">
                                    @if ($user->avatar_url)
                                        <img class="size-12 rounded-full border-2 border-white shadow-sm dark:border-slate-700"
                                            src="{{ $user->avatar_url }}" alt="{{ $user->name }}">
                                    @else
                                        <div
                                            class="{{ $avatarColorClass }} flex size-12 items-center justify-center rounded-full border-2 border-white text-lg font-bold shadow-sm dark:border-slate-700">
                                            {{ strtoupper(substr($user->name, 0, 1)) }}
                                        </div>
                                    @endif
                                    <span @class([
                                        'inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider',
                                        'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' =>
                                            $user->status?->value === 'active',
                                        'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300' =>
                                            $user->status?->value !== 'active',
                                    ])>
                                        {{ $user->status?->label() ?? 'N/A' }}
                                    </span>
                                </div>

                                <div class="space-y-1">
                                    <h4 class="font-bold text-slate-600 dark:text-white">{{ $user->name }}</h4>
                                    <p class="text-primary text-xs font-medium">
                                        {{ $user->job_title ?? 'Chưa cập nhật chức vụ' }}</p>
                                    <p class="text-[11px] font-normal text-slate-500 dark:text-slate-400">
                                        {{ $user->email }}
                                    </p>
                                </div>

                                @if ($user->department)
                                    <div
                                        class="mt-4 flex items-center gap-2 border-t border-slate-100 pt-3 dark:border-slate-700">
                                        <span
                                            class="material-symbols-outlined text-sm text-slate-400">corporate_fare</span>
                                        <span
                                            class="text-xs text-slate-600 dark:text-slate-400">{{ $user->department->name }}</span>
                                    </div>
                                @endif
                            </div>
                            {{-- Arrow --}}
                            <div
                                class="absolute -bottom-1 left-1/2 size-2 -translate-x-1/2 rotate-45 border-b border-r border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800">
                            </div>
                        </div>
                    </template>
                </div>
            @endforeach
        </div>

        @if ($extra > 0)
            <span class="text-xs font-medium text-slate-500 dark:text-slate-400">+{{ $extra }}</span>
        @endif
    </div>
@endif
