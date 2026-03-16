@props([
    'maxWidth' => '2xl', // sm | md | lg | xl | 2xl | 3xl | 4xl | 5xl | 6xl | 7xl | full
    'closeable' => true,
])

@php
    $wireModel = $attributes->wire('model');

    $maxWidthClasses =
        [
            'sm' => 'max-w-sm',
            'md' => 'max-w-md',
            'lg' => 'max-w-lg',
            'xl' => 'max-w-xl',
            '2xl' => 'max-w-2xl',
            '3xl' => 'max-w-3xl',
            '4xl' => 'max-w-4xl',
            '5xl' => 'max-w-5xl',
            '6xl' => 'max-w-6xl',
            '7xl' => 'max-w-7xl',
            'full' => 'max-w-full',
        ][$maxWidth] ?? 'max-w-2xl';
@endphp

<div
    x-data="{ isOpen: @entangle($wireModel).live }"
    x-effect="document.body.classList.toggle('overflow-hidden', isOpen)"
    {{ $attributes->except(['maxWidth', 'closeable'])->whereDoesntStartWith('wire:model') }}
>
    <template x-if="isOpen">
        <div
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 z-50 flex items-center justify-center overflow-hidden p-2"
            @if ($closeable) @keydown.escape.window="isOpen = false" @endif
        >
            {{-- Backdrop --}}
            <div
                class="fixed inset-0 bg-black/50 backdrop-blur-sm"
                @if ($closeable) @click="isOpen = false" @endif
            ></div>

            {{-- Panel --}}
            <div
                x-transition:enter="ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="ease-in duration-150"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                class="{{ $maxWidthClasses }} relative z-10 w-full overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-slate-800"
            >
                {{-- Close button --}}
                @if ($closeable)
                    <button
                        @click="isOpen = false"
                        class="absolute right-4 top-4 z-10 rounded-lg p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-700"
                    >
                        <span
                            class="material-symbols-outlined text-xl"
                        >close</span>
                    </button>
                @endif

                {{-- Header --}}
                @if (isset($header))
                    <div
                        class="px-6 pb-0 pt-6"
                    >
                        {{ $header }}
                    </div>
                @endif

                {{-- Body --}}
                <div
                    class="max-h-[75vh] overflow-y-auto px-5 py-4"
                >
                    {{ $slot }}
                </div>

                {{-- Footer --}}
                @if (isset($footer))
                    <div
                        class="flex items-center justify-end gap-3 border-t border-slate-200 bg-slate-50 px-6 py-4 dark:border-slate-700 dark:bg-slate-700/30"
                    >
                        {{ $footer }}
                    </div>
                @endif
            </div>
        </div>
    </template>
</div>
