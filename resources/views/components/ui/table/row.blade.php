@props([
    'href' => null,  // nếu có → cả row là link
])

<tr {{ $attributes->class([
        'animate-enter hover:bg-slate-50/80 dark:hover:bg-slate-800/30 transition-colors',
        $href ? 'cursor-pointer select-none' : '',
    ]) }}
    @if($href)
        x-on:click="Livewire.navigate('{{ $href }}')"
    @endif
>
    {{ $slot }}
</tr>
