@props([
    'align' => 'start', // start | center | end
])

@php
    $alignClass = match ($align) {
        'center' => 'text-center [&>*]:mx-auto [&>*]:justify-center',
        'end', 'right' => 'text-right [&>*]:ml-auto [&>*]:justify-end',
        'start', 'left' => 'text-left [&>*]:mr-auto [&>*]:justify-start',
        default => '',
    };
@endphp

<td
    {{ $attributes->class(['px-3 py-4', $alignClass]) }}
>
    {{ $slot }}
</td>
