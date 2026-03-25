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

    // Chuyển đổi linh hoạt các định dạng sang yyyy-mm-dd
    toISO(str) {
        if (!str) return '';

        // Nếu đã là định dạng yyyy-mm-dd
        if (str.includes('-')) {
            let parts = str.split(' ')[0].split('-');
            if (parts.length === 3) {
                let [y, m, d] = parts;
                if (y.length === 4 && y.startsWith('00')) y = '20' + y.substring(2);
                return `${y}-${m.padStart(2, '0')}-${d.padStart(2, '0')}`;
            }
        }

        // Nếu là định dạng dd/mm/yyyy
        if (str.includes('/')) {
            let parts = str.split('/');
            if (parts.length === 3) {
                let [d, m, y] = parts;
                if (y.length === 2) {
                    y = parseInt(y) > 50 ? '19' + y : '20' + y;
                }
                return `${y}-${m.padStart(2, '0')}-${d.padStart(2, '0')}`;
            }
        }

        return str;
    },

    // Chuyển đổi yyyy-mm-dd → dd/mm/yyyy để hiển thị
    toDisplay(str) {
        if (!str) return '';

        // Nếu đã là định dạng dd/mm/yyyy thì trả về luôn
        if (str.includes('/') && str.split('/').length === 3) {
            return str;
        }

        let iso = this.toISO(str);
        if (!iso || !iso.includes('-')) return str;

        let [y, m, d] = iso.split('-');
        return `${d}/${m}/${y}`;
    },

    init() {
        this.$nextTick(() => {
            if (!this.$refs.input) return;

            // Tự động chuẩn hóa giá trị nếu lỡ mang định dạng lạ hoặc năm 0030
            this.value = this.toISO(this.value);
            this.displayValue = this.toDisplay(this.value);

            // Phải set value cho input trước khi init Datepicker
            this.$refs.input.value = this.displayValue;

            this.instance = new window.Datepicker(this.$refs.input, {
                language: 'vi',
                format: '{{ $format }}',
                orientation: '{{ $orientation }}',
                autohide: true,
                clearBtn: true,
                todayBtn: true,
                todayBtnMode: 1,
            });

            // Đồng bộ lại datepicker nếu đã có giá trị
            if (this.displayValue) {
                this.instance.setDate(this.displayValue);
            }

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
