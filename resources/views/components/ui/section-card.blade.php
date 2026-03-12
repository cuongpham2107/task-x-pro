@props([
    'title' => null,
    'icon' => null,
    'iconBg' => 'bg-slate-100 dark:bg-slate-700',
    'iconColor' => 'text-slate-600 dark:text-slate-400',
    'compact' => false,
    'separator' => false,
])

<div
    {{ $attributes->class([
        'bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700',
        'p-5' => !$compact,
        'p-4' => $compact,
    ]) }}
>
    @if ($title || $icon || isset($actions))
        <div
            @class([
                'flex items-center justify-between',
                'mb-4 pb-2 border-b border-slate-100 dark:border-slate-800' => $separator,
                'mb-2' => !$separator,
            ])
        >
            <div
                class="flex items-center gap-2"
            >
                @if ($icon)
                    <div
                        class="{{ $iconBg }} rounded-lg p-1.5"
                    >
                        <span
                            class="material-symbols-outlined {{ $iconColor }} text-lg"
                        >{{ $icon }}</span>
                    </div>
                @endif
                @if ($title)
                    <h3
                        class="font-bold text-slate-800 dark:text-white"
                    >{{ $title }}</h3>
                @endif
            </div>

            @if (isset($actions))
                <div
                    class="flex items-center gap-2"
                >
                    {{ $actions }}
                </div>
            @endif
        </div>
    @endif

    {{ $slot }}
</div>
