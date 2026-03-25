@props([
    'name',
    'options',
    'label' => null,
    'icon' => null,
    'gridCols' => 'grid-cols-2',
    'hidden' => false,
    'disabled' => false,
])

@php
    // $options structure:
    // [
    //     'value' => [
    //         'label' => 'Label',
    //         'description' => 'Optional description',
    //         'icon' => 'Optional icon',
    //         'color' => 'text-primary', // Optional color class
    //         'bg' => 'bg-primary/5', // Optional bg class
    //     ]
    // ]

    $hasError = $errors->has($name);
@endphp

<div class="{{ $hidden ? 'hidden' : '' }} col-span-full space-y-2">
    @if ($label)
        <span class="flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-200">
            {{ $label }}
            @if ($icon)
                <span class="material-symbols-outlined text-base text-slate-400">{{ $icon }}</span>
            @endif
        </span>
    @endif

    <div class="{{ $gridCols }} mt-1 grid gap-3">
        @foreach ($options as $value => $opt)
            @php
                $colorClass = $opt['color'] ?? 'text-slate-700';
                $bgClass = $opt['bg'] ?? 'bg-slate-50';
                $borderClass = $opt['border'] ?? 'checked:border-primary';

                // Helper to generate dynamic background classes if passed as string format like "bg-primary/5"
                // But blade component props are cleaner if pre-calculated or consistent.
                // We'll stick to the flexible approach used in the original snippet.

            @endphp

            <label
                class="has-checked:border-current has-checked:bg-opacity-100 {{ $colorClass }} {{ $hasError ? 'border-red-300' : 'border-slate-200' }} {{ isset($opt['description']) ? 'items-start gap-4 p-4' : 'justify-center gap-2 p-3' }} {{ $disabled ? 'pointer-events-none opacity-50 grayscale select-none' : 'cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800' }} relative flex items-center rounded-xl border transition-all dark:border-slate-800">
                <div class="{{ isset($opt['description']) ? 'mt-1' : '' }}">
                    <input class="border-slate-300 focus:ring-current" name="{{ $name }}" type="radio"
                        value="{{ $value }}" {{ $disabled ? 'disabled' : '' }}
                        {{ $attributes->whereStartsWith('wire:model') }} />
                </div>

                @if (isset($opt['description']))
                    <div class="flex-1">
                        <div class="flex items-center gap-2">
                            @if (isset($opt['icon']))
                                <span class="material-symbols-outlined text-xl">{{ $opt['icon'] }}</span>
                            @endif
                            <span class="text-sm font-bold text-slate-800 dark:text-slate-100">
                                {{ $opt['label'] }}
                            </span>
                        </div>
                        <p class="mt-1 text-xs text-slate-400">{{ $opt['description'] }}</p>
                    </div>
                @else
                    <span class="text-sm font-medium">{{ $opt['label'] }}</span>
                @endif

                {{-- Background overlay for checked state styling if needed, or rely on parent class --}}
            </label>
        @endforeach
    </div>

    <x-ui.field-error field="{{ $name }}" />
</div>
