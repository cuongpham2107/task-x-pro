@props(['title' => '', 'description' => '', 'icon' => ''])

<div {{ $attributes->class(['mb-2 flex flex-col space-x-2']) }}>
    <div class="flex items-center gap-3">
        <span class="material-symbols-outlined text-primary text-xl">{{ $icon ?? 'add' }}</span>
        <h2 class="text-lg font-bold text-slate-900 dark:text-white">{{ $title ?? '' }}</h2>
    </div>
    <p class="text-sm text-slate-500 dark:text-slate-400">{{ $description ?? '' }}</p>
</div>
