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
                <span
                    class="material-symbols-outlined group-focus-within:text-primary text-[20px] text-slate-400 transition-colors">{{ $icon }}</span>
            </div>
        @endif

        <input type="{{ $type }}" id="{{ $id }}"
            @if ($name) name="{{ $name }}" @endif
            @if ($value) value="{{ $value }}" @endif
            @if ($placeholder) placeholder="{{ $placeholder }}" @endif
            @if ($required) required @endif @if ($disabled) disabled @endif
            {{ $attributes->class([
                'input-field' => $type !== 'range' && $type !== 'checkbox' && $type !== 'radio' && $type !== 'file',
                'pl-10' => $icon,
                'pr-10' => $iconRight || isset($suffix),
                'border-red-500 focus:border-red-500 focus:ring-red-500/20' => $hasError,
            ]) }} />

        @if ($iconRight)
            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3">
                <span
                    class="material-symbols-outlined group-focus-within:text-primary text-[20px] text-slate-400 transition-colors">{{ $iconRight }}</span>
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
