@props([
    'maxWidth' => '2xl', // sm | md | lg | xl | 2xl | 3xl | 4xl | 5xl
    'closeable' => true,
    'position' => 'right', // right | left
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
        ][$maxWidth] ?? 'max-w-2xl';

    $isRight = $position === 'right';
    $panelPosition = $isRight ? 'right-0' : 'left-0';
    $translateStart = $isRight ? 'translate-x-full' : '-translate-x-full';
@endphp

<div
    x-data="{ isOpen: @entangle($wireModel) }"
    x-effect="document.body.classList.toggle('overflow-hidden', isOpen)"
    {{ $attributes->except(['maxWidth', 'closeable', 'position'])->whereDoesntStartWith('wire:model') }}
>
    <template
        x-teleport="body"
    >
        <div
            x-show="isOpen"
            class="fixed inset-0 z-50 flex"
        >
            {{-- Backdrop --}}
            <div
                x-show="isOpen"
                x-transition:enter="ease-out duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="ease-in duration-200"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="fixed inset-0 bg-black/40 backdrop-blur-sm"
                @if ($closeable) @click="isOpen = false" @endif
            ></div>

            {{-- Panel --}}
            <div
                x-show="isOpen"
                x-transition:enter="transform transition ease-out duration-300"
                x-transition:enter-start="{{ $translateStart }}"
                x-transition:enter-end="translate-x-0"
                x-transition:leave="transform transition ease-in duration-200"
                x-transition:leave-start="translate-x-0"
                x-transition:leave-end="{{ $translateStart }}"
                class="{{ $maxWidthClasses }} {{ $panelPosition }} fixed inset-y-0 z-10 flex w-full flex-col bg-white shadow-2xl dark:bg-slate-800"
                @if ($closeable) @keydown.escape.window="isOpen = false" @endif
            >
                {{-- Header --}}
                <div
                    class="flex shrink-0 items-start justify-between gap-4 border-b border-slate-200 px-6 py-5 dark:border-slate-700"
                >
                    <div
                        class="flex-1"
                    >
                        @if (isset($header))
                            {{ $header }}
                        @endif
                    </div>
                    @if ($closeable)
                        <button
                            @click="isOpen = false"
                            class="shrink-0 rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-700"
                        >
                            <span
                                class="material-symbols-outlined text-xl"
                            >close</span>
                        </button>
                    @endif
                </div>

                {{-- Body --}}
                <div
                    class="custom-scrollbar flex-1 overflow-y-auto px-6 py-5"
                >
                    {{ $slot }}
                </div>

                {{-- Footer --}}
                @if (isset($footer))
                    <div
                        class="flex shrink-0 items-center justify-end gap-3 border-t border-slate-200 bg-slate-50 px-6 py-4 dark:border-slate-700 dark:bg-slate-700/30"
                    >
                        {{ $footer }}
                    </div>
                @endif
            </div>
        </div>
    </template>
</div>
