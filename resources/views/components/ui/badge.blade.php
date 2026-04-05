@props([
    'color' => 'slate',  // slate|primary|green|red|orange|blue|purple|amber
    'size' => 'sm',     // xs|sm|md
    'icon' => null,
])
@php
    $colorClasses = match ($color) {
        'primary' => 'bg-primary/10 text-primary',
        'green' => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
        'red' => 'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400',
        'orange' => 'bg-orange-100 text-orange-600 dark:bg-orange-900/30 dark:text-orange-400',
        'blue' => 'bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400',
        'purple' => 'bg-purple-100 text-purple-600 dark:bg-purple-900/30 dark:text-purple-400',
        'amber' => 'bg-amber-100 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400',
        'yellow' => 'bg-yellow-100 text-yellow-600 dark:bg-yellow-900/30 dark:text-yellow-400',
        default => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400',
    };

    $sizeClasses = match ($size) {
        'xs' => 'text-[10px] px-3 py-1',
        'md' => 'text-sm px-3 py-1',
        default => 'text-xs px-2.5 py-0.5',
    };

    // If a caller provides its own `class` (e.g. from an Enum like ProjectStatus::badgeClass()),
    // don't force the default `$colorClasses` because Tailwind class order matters.
    $hasCustomClass = trim((string) ($attributes->get('class') ?? '')) !== '';
@endphp
     <span {{ $attributes->class([
    'inline-flex items-center gap-1 rounded-lg font-bold uppercase tracking-wider',
    $hasCustomClass ? null : $colorClasses,
    $sizeClasses,
]) }}>
    @if ($icon)
        <span class="material-symbols-outlined text-xs">{{ $icon }}</span>
    @endif
    {{ $slot }}
</span>
