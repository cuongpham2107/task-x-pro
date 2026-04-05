@props([
    'label' => 'Chọn', // Nhãn mặc định khi chưa chọn, e.g. "Trạng thái"
    'icon' => 'tune', // Material symbol icon
    'model', // Tên wire property, e.g. "filterStatus"
    'value' => null, // Giá trị hiện tại (truyền từ $filterStatus)
    'options' => [], // [ value => ['label' => '...', 'dot' => 'bg-...', 'icon' => '...'] ]
    //   hoặc [ value => 'Label string' ] cho simple list
    'allLabel' => 'Tất cả',
    'width' => 'w-48',
    'dropWidth' => null, // override dropdown width nếu cần
    'labelKey' => 'label',
    'dotKey' => 'dot',
    'iconKey' => 'icon',
    'permitAll' => true,
])

@php
    $isActive = !empty($value);
    $dropW = $dropWidth ?? $width;

    // Normalize options: hỗ trợ cả ['label','dot','icon'] lẫn plain string
    $normalized = collect($options)->map(fn($opt, $key) => is_array($opt) ? $opt : ['label' => $opt]);

    // Hiển thị label của value đang chọn
    $selectedLabel = $isActive ? $normalized->get($value)[$labelKey] ?? ($normalized->get($value) ?? $label) : $label;
@endphp

<div class="relative" :class="open ? 'z-60' : 'z-10'" x-data="{ open: false }" @click.outside="open = false" x-cloak>

    {{-- Trigger button --}}
    <button type="button" @click="open = !open"
        class="flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-sm font-medium transition-colors hover:bg-slate-50 dark:hover:bg-slate-800"
        :class="(open || @js($isActive)) ?
        'border-primary text-primary bg-primary/5' :
        'border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-900'">
        <span class="material-symbols-outlined text-[18px] leading-none">{{ $icon }}</span>
        <span class="max-w-32 truncate">{{ $selectedLabel }}</span>
        <span class="material-symbols-outlined text-base leading-none transition-transform duration-200"
            :class="open ? 'rotate-180' : ''">expand_more</span>
    </button>

    {{-- Mobile Backdrop --}}
    <div x-show="open" x-transition.opacity @click="open = false"
        class="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-sm md:hidden" aria-hidden="true" x-cloak></div>

    {{-- Dropdown / Bottom Sheet --}}
    <div x-show="open" x-transition:enter="transition ease-out duration-200 md:duration-100"
        x-transition:enter-start="translate-y-full opacity-0 md:translate-y-0 md:scale-95"
        x-transition:enter-end="translate-y-0 opacity-100 md:scale-100"
        x-transition:leave="transition ease-in duration-200 md:duration-75"
        x-transition:leave-start="translate-y-0 opacity-100 md:scale-100"
        x-transition:leave-end="translate-y-full opacity-0 md:translate-y-0 md:scale-95"
        class="md:w-{{ $dropW }} z-60 fixed inset-x-0 bottom-0 flex max-h-[80vh] w-full flex-col overflow-hidden rounded-t-2xl border-t border-slate-200 bg-white pb-[env(safe-area-inset-bottom)] shadow-2xl md:absolute md:inset-auto md:left-0 md:top-full md:z-50 md:max-h-72 md:min-w-max md:rounded-xl md:border md:pb-0 dark:border-slate-700 dark:bg-slate-900">

        {{-- Mobile Header --}}
        <div
            class="flex items-center justify-between border-b border-slate-100 px-4 py-3 md:hidden dark:border-slate-800">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-lg text-slate-400">{{ $icon }}</span>
                <span
                    class="text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-white">{{ $label }}</span>
            </div>
            <button type="button" @click="open = false"
                class="flex h-8 w-8 items-center justify-center rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-400">
                <span class="material-symbols-outlined text-lg">close</span>
            </button>
        </div>

        <div class="flex-1 overflow-y-auto">
            {{-- All option --}}
            @if ($permitAll)
                <button type="button" wire:click="$set('{{ $model }}', '')" @click="open = false"
                    class="{{ !$isActive ? 'text-primary bg-primary/5 font-semibold' : 'text-slate-700 dark:text-slate-300' }} flex w-full items-center gap-2 whitespace-nowrap px-4 py-3 text-sm transition-colors hover:bg-slate-50 md:py-2.5 dark:hover:bg-slate-800">
                    <span class="h-2 w-2 shrink-0 rounded-full bg-slate-300"></span>
                    {{ $allLabel }}
                </button>
            @endif

            {{-- Options --}}
            @foreach ($normalized as $val => $opt)
                @php
                    $optLabel = is_array($opt) ? $opt[$labelKey] ?? $val : $opt;
                    $optDot = is_array($opt) ? $opt[$dotKey] ?? null : null;
                    $optIcon = is_array($opt) ? $opt[$iconKey] ?? null : null;
                    $isSelected = (string) $value === (string) $val;
                @endphp
                <button type="button" wire:click="$set('{{ $model }}', '{{ $val }}')"
                    @click="open = false"
                    class="{{ $isSelected ? 'text-primary bg-primary/5 font-semibold' : 'text-slate-700 dark:text-slate-300' }} flex w-full items-center gap-2 whitespace-nowrap px-4 py-3 text-sm transition-colors hover:bg-slate-50 md:py-2.5 dark:hover:bg-slate-800">
                    @if ($optIcon)
                        <span
                            class="material-symbols-outlined shrink-0 text-base leading-none">{{ $optIcon }}</span>
                    @elseif ($optDot)
                        <span class="{{ $optDot }} h-2 w-2 shrink-0 rounded-full"></span>
                    @endif
                    {{ $optLabel }}
                </button>
            @endforeach

            {{-- Extra slot (e.g. dynamic list từ DB) --}}
            {{ $slot ?? '' }}
        </div>
    </div>
</div>
