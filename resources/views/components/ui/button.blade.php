@props([
    'tag' => 'button', // button | a
    'variant' => 'primary', // primary | secondary | ghost | danger | outline | white | ghost-light
    'size' => 'sm', // xs | sm | md | lg | xl
    'icon' => null, // material-symbols-outlined icon name (left)
    'iconRight' => null, // icon on the right
    'href' => null, // auto-switch tag to <a>
    'navigate' => false, // wire:navigate
    'full' => false, // w-full
    'loading' => null, // wire target for loading state, e.g. "save"
])

@php
    $isLink = $href || $tag === 'a';

    // ─── Size classes ─────────────────────────────────────────
    $sizeClasses = match ($size) {
        'xs' => 'px-2 py-0.5 md:px-2.5 md:py-1 text-[10px] md:text-xs rounded-lg gap-1',
        'sm' => 'px-2.5 py-1 md:px-3 md:py-1.5 text-xs rounded-lg gap-1.5',
        'md' => 'px-4 py-2 md:px-5 md:py-2.5 text-xs md:text-sm rounded-lg gap-2',
        'lg' => 'px-5 py-2.5 md:px-6 md:py-3 text-sm rounded-xl gap-2',
        'xl' => 'px-6 py-3 md:px-8 md:py-3.5 text-sm md:text-base rounded-xl gap-2.5',
    };

    $iconSize = match ($size) {
        'xs' => 'text-xs md:text-sm',
        'sm' => 'text-sm md:text-base',
        'md', 'lg' => 'text-base md:text-lg',
        'xl' => 'text-lg md:text-xl',
    };

    // ─── Variant classes ──────────────────────────────────────
    $variantClasses = match ($variant) {
        'primary'
            => 'bg-primary text-white font-bold shadow-lg shadow-primary/20 hover:bg-primary/90 active:scale-[0.98]',
        'secondary'
            => 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold hover:bg-slate-200 dark:hover:bg-slate-700',
        'ghost'
            => 'text-slate-500 dark:text-slate-400 font-bold hover:text-slate-800 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800',
        'danger' => 'bg-red-600 text-white font-bold shadow-lg shadow-red-600/20 hover:bg-red-700 active:scale-[0.98]',
        'success'
            => 'bg-green-600 text-white font-bold shadow-lg shadow-green-600/20 hover:bg-green-700 active:scale-[0.98]',
        'warning'
            => 'bg-amber-500 text-white font-bold shadow-lg shadow-amber-500/20 hover:bg-amber-600 active:scale-[0.98]',
        'outline'
            => 'border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 font-bold hover:bg-slate-50 dark:hover:bg-slate-800',
        'white' => 'bg-white text-primary font-bold shadow-lg hover:bg-blue-50 active:scale-[0.98]',
        'ghost-light' => 'border border-white/30 text-white font-bold hover:bg-white/10',
    };

    $baseClasses =
        'inline-flex items-center justify-center transition-all disabled:opacity-60 disabled:cursor-not-allowed cursor-pointer ';
@endphp

@if ($isLink)
    <a href="{{ $href }}" @if ($navigate) wire:navigate @endif
        {{ $attributes->class([$baseClasses, $sizeClasses, $variantClasses, 'w-full' => $full]) }}>
        @if ($icon)
            <span class="material-symbols-outlined {{ $iconSize }}">{{ $icon }}</span>
        @endif
        {{ $slot }}
        @if ($iconRight)
            <span class="material-symbols-outlined {{ $iconSize }}">{{ $iconRight }}</span>
        @endif
    </a>
@else
    <button {{ $attributes->class([$baseClasses, $sizeClasses, $variantClasses, 'w-full' => $full]) }}
        @if (!$attributes->has('type')) type="button" @endif
        @if ($loading) wire:loading.attr="disabled" wire:loading.class="opacity-70 cursor-not-allowed" @endif>
        @if ($loading)
            <span class="material-symbols-outlined {{ $iconSize }} animate-spin" wire:loading
                wire:target="{{ $loading }}">progress_activity</span>
        @endif
        @if ($icon)
            <span class="material-symbols-outlined {{ $iconSize }}"
                @if ($loading) wire:loading.remove wire:target="{{ $loading }}" @endif>{{ $icon }}</span>
        @endif
        @if ($loading)
            <span wire:loading.remove wire:target="{{ $loading }}">{{ $slot }}</span>
            <span wire:loading wire:target="{{ $loading }}">Đang xử lý...</span>
        @else
            {{ $slot }}
        @endif
        @if ($iconRight)
            <span class="material-symbols-outlined {{ $iconSize }}"
                @if ($loading) wire:loading.remove wire:target="{{ $loading }}" @endif>{{ $iconRight }}</span>
        @endif
    </button>
@endif
