@props([
    'align' => 'left', // left | center | right
])

@php
    $alignClass = match ($align) {
        'center' => 'text-center',
        'right' => 'text-right',
        default => '',
    };
@endphp

<td
    {{ $attributes->class(['px-3 py-4', $alignClass]) }}
>
    {{ $slot }}
</td>
