@props(['title' => '', 'description' => '', 'desc' => ''])

@php $text = $description ?: $desc; @endphp

<div
    {{ $attributes->class(['mb-0']) }}
>
    <h1
        class="mb-2 text-xl font-black tracking-tight text-slate-900 sm:text-2xl dark:text-white"
    >{{ $title }}</h1>
    @if ($text)
        <p
            class="text-xs text-slate-500 sm:text-sm dark:text-slate-400"
        >{{ $text }}</p>
    @endif
</div>
