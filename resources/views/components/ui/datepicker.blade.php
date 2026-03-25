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
    displayValue: '',
    value: @entangle($attributes->wire('model')),
    instance: null,

    // Convert dd/mm/yyyy → yyyy-mm-dd
    toISO(str) {
        if (!str) return '';
        const [d, m, y] = str.split('/');
        return y && m && d ? `${y}-${m}-${d}` : '';
    },

    // Convert yyyy-mm-dd → dd/mm/yyyy để hiển thị
    toDisplay(str) {
        if (!str) return '';
        const [y, m, d] = str.split('-');
        return y && m && d ? `${d}/${m}/${y}` : '';
    },

    init() {
        this.$nextTick(() => {
            if (!this.$refs.input) return;

            this.displayValue = this.toDisplay(this.value);

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
                if (!this.$refs.input) return;
                this.displayValue = this.$refs.input.value;
                this.value = this.toISO(this.$refs.input.value);
            });

            this.$watch('value', (newVal) => {
                if (!this.$refs.input || !this.instance) return;
                const display = this.toDisplay(newVal);
                if (display !== this.$refs.input.value) {
                    this.instance.setDate(display);
                }
            });
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
                'cursor-not-allowed opacity-60 bg-slate-50' => $disabled,
                'hover:text-red-500' => !$disabled,
            ]) }} />
    </div>

    @if ($name)
        <x-ui.field-error field="{{ $name }}" />
    @endif
</div>
