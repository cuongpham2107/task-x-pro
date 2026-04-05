@props([
    'user' => null,
    'name' => null,
    'avatarUrl' => null,
    'size' => 6,
    'label' => null,
])

@php
    $userName = $user?->name ?? $name;
    $userAvatar = $user?->avatar_url ?? $avatarUrl;
    $initials = strtoupper(substr($userName ?? '?', 0, 1));
    $sizeClass = "size-{$size}";
    $ringClass = "{$sizeClass} rounded-full object-cover ring-1 ring-white dark:ring-slate-900";
@endphp

<div {{ $attributes->merge(['class' => 'group/av relative shrink-0']) }}>
    @if ($userAvatar)
        <img src="{{ $userAvatar }}" alt="{{ $userName }}" class="{{ $ringClass }}" />
    @else
        <div
            class="bg-primary/20 text-primary flex {{ $sizeClass }} items-center justify-center rounded-full text-2xs font-bold ring-1 ring-white dark:ring-slate-900">
            {{ $initials }}
        </div>
    @endif

    {{-- Premium Tooltip --}}
    <div
        class="pointer-events-none absolute bottom-full left-1/2 z-20 mb-1.5 -translate-x-1/2 whitespace-nowrap rounded bg-slate-800 px-2 py-0.5 text-2xs text-white opacity-0 transition-opacity group-hover/av:opacity-100">
        {{ $userName }} @if ($label)
            ({{ $label }})
        @endif
        <div class="absolute left-1/2 top-full -translate-x-1/2 border-4 border-transparent border-t-slate-800">
        </div>
    </div>
</div>
