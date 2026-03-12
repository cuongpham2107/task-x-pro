@props([
    'icon'     => 'more_vert',
    'size'     => 'md',    // sm | md | lg
    'tooltip'  => null,
    'variant'  => 'ghost', // ghost | outline | solid
    'color'    => 'slate', // slate | primary | red | green
    'href'     => null,
    'navigate' => false,
])

@php
    $sizeClasses = [
        'sm' => 'p-1 rounded-md',
        'md' => 'p-1.5 rounded-lg',
        'lg' => 'p-2 rounded-xl',
    ][$size] ?? 'p-1.5 rounded-lg';

    $iconSizes = [
        'sm' => 'text-base',
        'md' => 'text-lg',
        'lg' => 'text-xl',
    ][$size] ?? 'text-lg';

    $variantClasses = match($variant) {
        'ghost' => match($color) {
            'primary' => 'hover:bg-primary-50 dark:hover:bg-primary-900/20 text-primary-600 dark:text-primary-400',
            'red'     => 'hover:bg-red-50 dark:hover:bg-red-900/20 text-red-600 dark:text-red-400',
            'green'   => 'hover:bg-green-50 dark:hover:bg-green-900/20 text-green-600 dark:text-green-400',
            'blue'    => 'hover:bg-blue-50 dark:hover:bg-blue-900/20 text-blue-600 dark:text-blue-400',
            default   => 'hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-400 hover:text-slate-600 dark:text-slate-500 dark:hover:text-slate-300',
        },
        'outline' => match($color) {
            'primary' => 'border border-primary-200 dark:border-primary-700 hover:bg-primary-50 dark:hover:bg-primary-900/20 text-primary-600',
            'red'     => 'border border-red-200 dark:border-red-700 hover:bg-red-50 dark:hover:bg-red-900/20 text-red-600',
            default   => 'border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700/50 text-slate-500',
        },
        'solid' => match($color) {
            'primary' => 'bg-primary-600 hover:bg-primary-700 text-white',
            'red'     => 'bg-red-600 hover:bg-red-700 text-white',
            default   => 'bg-slate-600 hover:bg-slate-700 text-white',
        },
        default => '',
    };
@endphp

@if ($href)
<a
    href="{{ $href }}"
    @if ($navigate) wire:navigate @endif
    {{ $attributes->except(['href','navigate'])->class([
        'inline-flex items-center justify-center transition cursor-pointer',
        $sizeClasses,
        $variantClasses,
    ]) }}
    @if ($tooltip) title="{{ $tooltip }}" @endif
>
    <span class="material-symbols-outlined {{ $iconSizes }}">{{ $icon }}</span>
</a>
@else
<button
    type="button"
    {{ $attributes->class([
        'inline-flex items-center justify-center transition cursor-pointer',
        $sizeClasses,
        $variantClasses,
    ]) }}
    @if ($tooltip) title="{{ $tooltip }}" @endif
>
    <span class="material-symbols-outlined {{ $iconSizes }}">{{ $icon }}</span>
</button>
@endif
