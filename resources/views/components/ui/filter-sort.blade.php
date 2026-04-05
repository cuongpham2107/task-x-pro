@props([
    'sortBy', // current sortBy value
    'sortDir', // 'asc' | 'desc'
    'options' => [], // [ 'column' => 'Label', ... ]
    'width' => 'w-36',
    'dropWidth' => 'w-48',
])

<div x-data="{ open: false }" @click.outside="open = false" class="relative" :class="open ? 'z-60' : 'z-10'" x-cloak>
    <button @click="open = !open"
        class="hover:border-primary/50 {{ $width }} flex items-center gap-1 rounded-lg border px-3 py-1.5 text-xs font-semibold transition-colors"
        :class="open ? 'border-primary text-primary bg-primary/5' :
            'border-slate-200 bg-slate-100 text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400'">
        <span class="material-symbols-outlined text-sm">sort</span>
        <span class="flex-1 text-left">Sắp xếp</span>
        <span class="material-symbols-outlined text-sm transition-transform duration-200"
            :class="open ? 'rotate-180' : ''">
            {{ $sortDir === 'asc' ? 'arrow_upward' : 'arrow_downward' }}
        </span>
    </button>

    {{-- Mobile Backdrop --}}
    <div x-cloak x-show="open" x-transition.opacity @click="open = false"
        class="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-sm md:hidden" aria-hidden="true"></div>

    <div x-cloak x-show="open" x-transition:enter="transition ease-out duration-200 md:duration-150"
        x-transition:enter-start="translate-y-full opacity-0 md:-translate-y-1 md:translate-y-0 md:scale-95"
        x-transition:enter-end="translate-y-0 opacity-100 md:translate-y-0 md:scale-100"
        x-transition:leave="transition ease-in duration-200 md:duration-100"
        x-transition:leave-start="translate-y-0 opacity-100 md:translate-y-0 md:scale-100"
        x-transition:leave-end="translate-y-full opacity-0 md:-translate-y-1 md:translate-y-0 md:scale-95"
        class="md:w-{{ $dropWidth }} z-60 fixed inset-x-0 bottom-0 flex max-h-[80vh] w-full flex-col overflow-hidden rounded-t-2xl border-t border-slate-200 bg-white pb-[env(safe-area-inset-bottom)] shadow-2xl md:absolute md:inset-auto md:right-0 md:top-full md:z-30 md:max-h-72 md:min-w-max md:rounded-xl md:border md:pb-0 dark:border-slate-700 dark:bg-slate-900">

        {{-- Mobile Header --}}
        <div
            class="flex items-center justify-between border-b border-slate-100 px-4 py-3 md:hidden dark:border-slate-800">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-lg text-slate-400">sort</span>
                <span class="text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-white">Sắp xếp
                    theo</span>
            </div>
            <button type="button" @click="open = false"
                class="flex h-8 w-8 items-center justify-center rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-400">
                <span class="material-symbols-outlined text-lg">close</span>
            </button>
        </div>

        <div class="flex-1 overflow-y-auto">
            <p
                class="hidden border-b border-slate-100 px-4 py-2.5 text-xs font-bold uppercase tracking-wider text-slate-500 md:block dark:border-slate-800">
                Sắp xếp theo
            </p>

            @foreach ($options as $col => $label)
                <button wire:click="setSort('{{ $col }}')" @click="open = false"
                    class="{{ $sortBy === $col
                        ? 'bg-primary/5 text-primary font-semibold'
                        : 'text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800' }} flex w-full items-center justify-between gap-4 whitespace-nowrap px-4 py-3 text-left text-xs transition-colors md:py-2.5">
                    {{ $label }}
                    @if ($sortBy === $col)
                        <span class="material-symbols-outlined text-sm">
                            {{ $sortDir === 'asc' ? 'arrow_upward' : 'arrow_downward' }}
                        </span>
                    @endif
                </button>
            @endforeach
        </div>
    </div>
</div>
