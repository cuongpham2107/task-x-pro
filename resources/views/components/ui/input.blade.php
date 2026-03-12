@props([
    'label' => null,
    'name' => null,
    'type' => 'text',
    'placeholder' => null,
    'id' => null,
    'required' => false,
    'disabled' => false,
    'value' => null,
    'icon' => null,
    'iconRight' => null,
])
@php
    $id = $id ?? ($name ?? md5($attributes->wire('model')));
    $hasError = $name && $errors->has($name);
    $isNumber = $type === 'number';
    $inputType = $isNumber ? 'text' : $type;

    // Lấy tên property wire:model (vd: "budget" hoặc "form.budget")
    $wireModel = $isNumber
        ? ($attributes->get('wire:model')
            ?? $attributes->get('wire:model.live')
            ?? $attributes->get('wire:model.blur')
            ?? $attributes->get('wire:model.lazy'))
        : null;

    // Với number input: tách Alpine khỏi wire:model gốc để tự sync giá trị sạch
    $inputAttributes = $isNumber
        ? $attributes->except(['wire:model', 'wire:model.live', 'wire:model.blur', 'wire:model.lazy'])
        : $attributes;
@endphp

<div class="w-full">
    @if ($label)
        <label for="{{ $id }}" class="label-text mb-1 block">
            {{ $label }}
            @if ($required)
                <span class="text-red-500">*</span>
            @endif
        </label>
    @endif

    <div class="group relative">
        @if ($icon)
            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                <span class="material-symbols-outlined group-focus-within:text-primary text-[20px] text-slate-400 transition-colors">{{ $icon }}</span>
            </div>
        @endif

        <input
            type="{{ $inputType }}"
            id="{{ $id }}"
            @if ($name) name="{{ $name }}" @endif
            @if ($value) value="{{ $value }}" @endif
            @if ($placeholder) placeholder="{{ $placeholder }}" @endif
            @if ($required) required @endif
            @if ($disabled) disabled @endif

            @if ($isNumber)
                inputmode="numeric"
                {{-- Alpine: display value có format, sync giá trị thuần số về Livewire --}}
                @if ($wireModel)
                    x-data="{
                        display: $wire.{{ $wireModel }}
                            ? Number(String($wire.{{ $wireModel }}).replace(/,/g, '')).toLocaleString('en-US')
                            : ''
                    }"
                    x-model="display"
                    x-mask:dynamic="$money($input, '.', ',', 0)"
                    @input.debounce.300ms="$wire.set('{{ $wireModel }}', $el.value.replace(/,/g, '') || null)"
                @else
                    x-data
                    x-mask:dynamic="$money($input, '.', ',', 0)"
                @endif
            @endif

            {{ $inputAttributes->class([
                'input-field' => !in_array($type, ['range', 'checkbox', 'radio', 'file']),
                'pl-10' => $icon,
                'pr-10' => $iconRight || isset($suffix),
                'border-red-500 focus:border-red-500 focus:ring-red-500/20' => $hasError,
            ]) }}
        />

        @if ($iconRight)
            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3">
                <span class="material-symbols-outlined group-focus-within:text-primary text-[20px] text-slate-400 transition-colors">{{ $iconRight }}</span>
            </div>
        @endif

        @isset($suffix)
            <div class="absolute inset-y-0 right-0 flex items-center pr-3">
                {{ $suffix }}
            </div>
        @endisset
    </div>

    @if ($name)
        <x-ui.field-error field="{{ $name }}" />
    @endif
</div>