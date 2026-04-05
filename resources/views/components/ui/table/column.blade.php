@props([
    'align' => 'left', // left | center | right
    'width' => null, // e.g. 'min-w-62.5' hoặc 'w-16'
    'muted' => false, // true → dùng màu slate-500 (cột "Thao tác")
])

@php
    $alignClass = match ($align) {
        'center' => 'text-center',
        'right' => 'text-right',
        default => '',
    };
    $colorClass = $muted ? 'text-slate-500' : 'text-slate-500 dark:text-slate-100';
@endphp

<th
    {{ $attributes->class([
        'px-3 py-4 text-xs font-bold uppercase tracking-wider border-b border-slate-200 dark:border-slate-800',
        $colorClass,
        $alignClass,
        $width,
    ]) }}
>
    {{ $slot }}
</th>
