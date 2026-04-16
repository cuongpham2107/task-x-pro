@props([
    'label' => null,
    'name',
    'min' => 0,
    'max' => 100,
    'step' => 1,
    'icon' => null,
    'unit' => '%',
    'startLabel' => 'Bắt đầu (0%)',
    'endLabel' => 'Hoàn thành (100%)',
    'disabled' => false,
])

<div class="{{ $disabled ? 'pointer-events-none opacity-50' : '' }} col-span-full space-y-3" x-data="{ value: @entangle($attributes->wire('model')) }">
    @if ($label)
        <div class="flex items-center justify-between">
            <label class="label-text flex items-center gap-2">
                @if ($icon)
                    <span class="material-symbols-outlined text-base text-slate-400">{{ $icon }}</span>
                @endif
                {{ $label }}
            </label>
            <span class="bg-primary/10 text-primary dark:bg-primary/20 rounded-full px-3 py-1 text-sm font-bold"
                x-text="value + '{{ $unit }}'"></span>
        </div>
    @endif

    <div class="relative flex items-center py-2">
        <x-ui.input type="range" name="{{ $name }}" min="{{ $min }}" max="{{ $max }}"
            step="{{ $step }}" x-model="value" :disabled="$disabled"
            class="accent-primary text-primary h-2 w-full cursor-pointer appearance-none rounded-lg bg-slate-200 bg-[image:linear-gradient(currentColor,currentColor)] bg-no-repeat focus:outline-none dark:bg-slate-700"
            x-bind:style="`background-size: ${ ((value - {{ $min }}) / ({{ $max }} - {{ $min }})) * 100 }% 100%;`" />
    </div>

    <div class="flex justify-between text-[10px] font-medium uppercase tracking-wider text-slate-400">
        <span>{{ $startLabel }}</span>
        <span>{{ $endLabel }}</span>
    </div>
</div>
