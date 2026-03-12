@props([
    'label' => null,
    'name' => null,
    'id' => null,
    'placeholder' => 'Chọn ngày',
    'required' => false,
    'disabled' => false,
    'format' => 'dd/mm/yyyy',
    'orientation' => 'bottom',
])

@php
    $id = $id ?? ($name ?? md5($attributes->wire('model')));
    $hasError = $name && $errors->has($name);
@endphp

<div class="w-full" x-data="{
    value: @entangle($attributes->wire('model')),
    instance: null,
    init() {
        this.instance = new window.Datepicker(this.$refs.input, {
            language: 'vi',
            format: '{{ $format }}',
            orientation: '{{ $orientation }}',
            autohide: true,
            clearBtn: true,

            todayBtn: true,
            todayBtnMode: 1,
        });

        this.$refs.input.addEventListener('changeDate', (e) => {
            // Update Livewire value
            this.value = this.$refs.input.value;
        });

        // Watch for external changes
        this.$watch('value', (newVal) => {
            if (newVal !== this.$refs.input.value) {
                this.instance.setDate(newVal);
            }
        });
    }
}" wire:ignore>
    @if ($label)
        <label for="{{ $id }}" class="label-text mb-1 block">
            {{ $label }}
            @if ($required)
                <span class="text-red-500">*</span>
            @endif
        </label>
    @endif

    <div class="relative">
        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
            <span class="material-symbols-outlined text-[20px] text-slate-400">calendar_today</span>
        </div>

        <input x-ref="input" type="text" id="{{ $id }}"
            @if ($name) name="{{ $name }}" @endif
            @if ($placeholder) placeholder="{{ $placeholder }}" @endif
            @if ($required) required @endif @if ($disabled) disabled @endif
            datepicker-orientation="{{ $orientation }}"
            {{ $attributes->class([
                'input-field pl-10',
                'border-red-500 focus:border-red-500 focus:ring-red-500/20' => $hasError,
            ]) }} />
    </div>

    @if ($name)
        <x-ui.field-error field="{{ $name }}" />
    @endif
</div>
