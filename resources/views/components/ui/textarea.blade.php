@props([
    'label' => null,
    'name',
    'placeholder' => '',
    'rows' => 4,
    'required' => false,
    'icon' => null, // Icon for label
    'iconColor' => 'text-slate-400',
    'disabled' => false,
])

@php
    $hasError = $errors->has($name);
@endphp

<div class="col-span-full space-y-2">
    @if ($label)
        <div class="mb-1 flex items-center gap-2">
            @if ($icon)
                <span class="material-symbols-outlined {{ $iconColor }}">{{ $icon }}</span>
            @endif
            <label class="text-sm font-semibold text-slate-700 dark:text-slate-200">
                {{ $label }}
                @if ($required)
                    <span class="text-red-500">*</span>
                @endif
            </label>
        </div>
    @endif


    <textarea class="input-field {{ $attributes->get('class') }} w-full {{ $disabled ? 'bg-slate-50 cursor-not-allowed text-slate-500' : '' }}"
        placeholder="{{ $placeholder }}" rows="{{ $rows }}" name="{{ $name }}"
        {{ $attributes->whereStartsWith('wire:model') }} @if ($disabled) disabled @endif></textarea>

    <x-ui.field-error field="{{ $name }}" />
</div>
