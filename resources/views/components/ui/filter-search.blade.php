@props([
    'model', // wire:model property name, e.g. "filterSearch"
    'placeholder' => 'Tìm kiếm...',
    'width' => 'w-56',
])

<x-ui.input wire:model.live.debounce.300ms="{{ $model }}" icon="search" placeholder="{{ $placeholder }}"
    class="{{ $width }} bg-white py-1.5 text-sm placeholder:text-slate-400/80 dark:bg-slate-900 dark:placeholder:text-slate-500/80"
    {{ $attributes->except(['model', 'placeholder', 'width']) }} />
