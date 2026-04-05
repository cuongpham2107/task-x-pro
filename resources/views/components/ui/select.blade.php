@props([
    'label' => null,
    'name' => null,
    'id' => null,
    'required' => false,
    'disabled' => false,
    'icon' => null,
    'options' => [], // [ value => label ] or [ value => ['label' => '...', 'icon' => '...'] ]
    'placeholder' => 'Chọn một tùy chọn',
    'labelKey' => 'label',
    'iconKey' => 'icon',
])

@php
    $id = $id ?? ($name ?? md5($attributes->wire('model')));
    $hasError = $name && $errors->has($name);
    $wireModel = $attributes->wire('model')->value()
        ?? $attributes->get('wire:model.live')
        ?? $attributes->get('wire:model.lazy')
        ?? $attributes->get('wire:model.defer')
        ?? $attributes->get('wire:model.blur');
    $isLive = $attributes->get('wire:model.live') !== null;

    // Normalize options
    $normalized = collect($options)
        ->mapWithKeys(fn($opt, $key) => [(string) $key => is_array($opt) ? $opt : ['label' => $opt]])
        ->all();

    $jsOptions = collect($normalized)
        ->map(function ($opt, $k) use ($labelKey, $iconKey) {
            return [
                'value' => (string) $k,
                'label' => $opt[$labelKey] ?? (is_string($opt) ? $opt : (string) $k),
                'icon' => $opt[$iconKey] ?? null,
            ];
        })
        ->values()
        ->all();
@endphp

<div class="w-full" x-data="{
    open: false,
    search: '',
    value: @entangle($wireModel){{ $isLive ? '.live' : '' }},
    optionsList: [],
    get selectedLabel() {
        if (this.value === null || this.value === undefined || this.value === '') return '{{ $placeholder }}';
        let found = this.optionsList.find(o => String(o.value) === String(this.value));
        return found ? found.label : this.value;
    },
    get selectedIcon() {
        if (this.value === null || this.value === undefined || this.value === '') return null;
        let found = this.optionsList.find(o => String(o.value) === String(this.value));
        return found ? found.icon : null;
    }
}" x-effect="optionsList = @js($jsOptions)" @click.outside="open = false" x-cloak>
    @if ($label)
        <label for="{{ $id }}" class="label-text mb-1 block">
            {{ $label }}
            @if ($required)
                <span class="text-red-500">*</span>
            @endif
        </label>
    @endif

    <div class="relative">
        {{-- Trigger --}}
        <button type="button" @click="!@js($disabled) && (open = !open)"
            class="input-field flex w-full items-center justify-between px-3 text-left transition-all"
            :class="{
                'border-primary ring-4 ring-primary/10': open,
                'border-red-500 ring-red-500/10': @js($hasError),
                'opacity-60 cursor-not-allowed bg-slate-50': @js($disabled)
            }">
            <div class="flex items-center gap-2 overflow-hidden">
                @if ($icon)
                    <span class="material-symbols-outlined text-[20px] text-slate-400"
                        x-show="!selectedIcon">{{ $icon }}</span>
                @endif
                <template x-if="selectedIcon">
                    <span class="material-symbols-outlined text-primary text-[20px]" x-text="selectedIcon"></span>
                </template>
                <span class="truncate" :class="!value ? 'text-slate-400' : 'text-slate-600 dark:text-white'"
                    x-text="selectedLabel"></span>
            </div>
            <span class="material-symbols-outlined text-slate-400 transition-transform duration-200"
                :class="open ? 'rotate-180' : ''">expand_more</span>
        </button>

        {{-- Mobile Backdrop --}}
        <div x-show="open" x-transition.opacity @click="open = false"
            class="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-sm md:hidden" aria-hidden="true"></div>

        {{-- Dropdown / Bottom Sheet --}}
        <div x-show="open" x-transition:enter="transition ease-out duration-200 md:duration-100"
            x-transition:enter-start="translate-y-full opacity-0 md:translate-y-0 md:scale-95"
            x-transition:enter-end="translate-y-0 opacity-100 md:scale-100"
            x-transition:leave="transition ease-in duration-200 md:duration-75"
            x-transition:leave-start="translate-y-0 opacity-100 md:scale-100"
            x-transition:leave-end="translate-y-full opacity-0 md:translate-y-0 md:scale-95"
            class="z-60 fixed inset-x-0 bottom-0 flex max-h-[80vh] w-full flex-col overflow-hidden rounded-t-2xl border-t border-slate-200 bg-white pb-[env(safe-area-inset-bottom)] shadow-2xl md:absolute md:inset-auto md:left-0 md:top-full md:mt-1 md:max-h-64 md:rounded-xl md:border dark:border-slate-700 dark:bg-slate-900">

            {{-- Mobile Header --}}
            <div
                class="flex items-center justify-between border-b border-slate-100 px-4 py-3 md:hidden dark:border-slate-800">
                <span
                    class="text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-white">{{ $label ?? 'Chọn tùy chọn' }}</span>
                <button type="button" @click="open = false" class="text-slate-400">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>

            <div class="flex-1 overflow-y-auto p-1">
                @if (count($normalized) > 5)
                    <div class="sticky top-0 z-10 bg-white px-2 py-2 dark:bg-slate-900">
                        <div class="relative">
                            <span
                                class="material-symbols-outlined absolute left-2 top-1/2 -translate-y-1/2 text-sm text-slate-400">search</span>
                            <input type="text" x-model="search" placeholder="Tìm kiếm..."
                                class="focus:border-primary w-full rounded-lg border-slate-200 bg-slate-50 py-1.5 pl-8 pr-3 text-xs focus:ring-0 dark:border-slate-800 dark:bg-slate-800">
                        </div>
                    </div>
                @endif

                <div class="space-y-0.5">
                    @if ($placeholder && !$required)
                        <button type="button" @click="value = ''; open = false"
                            class="flex w-full items-center rounded-lg px-3 py-2 text-sm text-slate-500 transition-colors hover:bg-slate-50 dark:hover:bg-slate-800"
                            :class="!value ? 'bg-primary/5 text-primary font-medium' : ''">
                            {{ $placeholder }}
                        </button>
                    @endif

                    @foreach ($normalized as $val => $opt)
                        @php
                            $optLabel = is_array($opt) ? $opt[$labelKey] ?? $val : $opt;
                            $optIcon = is_array($opt) ? $opt[$iconKey] ?? null : null;
                        @endphp
                        <button type="button" @click="value = @js($val); open = false"
                            x-show="!search || '{{ strtolower($optLabel) }}'.includes(search.toLowerCase())"
                            class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm transition-colors hover:bg-slate-50 dark:hover:bg-slate-800"
                            :class="value == @js($val) ? 'bg-primary/5 text-primary font-medium' :
                                'text-slate-700 dark:text-slate-300'">
                            @if ($optIcon)
                                <span class="material-symbols-outlined text-base">{{ $optIcon }}</span>
                            @endif
                            <span>{{ $optLabel }}</span>
                        </button>
                    @endforeach

                    {{-- Handle Slots if provided --}}
                    {{ $slot }}
                </div>
            </div>
        </div>
    </div>

    @if ($name)
        <x-ui.field-error field="{{ $name }}" />
    @endif
</div>
