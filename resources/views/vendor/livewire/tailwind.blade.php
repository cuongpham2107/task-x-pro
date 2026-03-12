@php
    if (! isset($scrollTo)) {
        $scrollTo = 'body';
    }

    $scrollIntoViewJsSnippet = $scrollTo !== false
        ? "(\$el.closest('{$scrollTo}') || document.querySelector('{$scrollTo}')).scrollIntoView()"
        : '';
@endphp

@if ($paginator->hasPages())
    <div class="flex items-center gap-1">
        {{-- Previous --}}
        @if ($paginator->onFirstPage())
            <span class="flex size-9 cursor-not-allowed items-center justify-center rounded-lg border border-slate-200 text-slate-300 dark:border-slate-800 dark:text-slate-600">
                <span class="material-symbols-outlined text-xl">chevron_left</span>
            </span>
        @else
            <button
                type="button"
                wire:click="previousPage('{{ $paginator->getPageName() }}')"
                x-on:click="{{ $scrollIntoViewJsSnippet }}"
                wire:loading.attr="disabled"
                class="flex size-9 items-center justify-center rounded-lg border border-slate-200 text-slate-600 transition-colors hover:bg-slate-100 dark:border-slate-800 dark:text-slate-400 dark:hover:bg-slate-800"
                aria-label="{{ __('pagination.previous') }}"
            >
                <span class="material-symbols-outlined text-xl">chevron_left</span>
            </button>
        @endif

        {{-- Page Numbers --}}
        @foreach ($elements as $element)
            @if (is_string($element))
                {{-- Dots --}}
                <span class="flex size-9 items-center justify-center text-sm font-medium text-slate-400 dark:text-slate-500">
                    &hellip;
                </span>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="flex size-9 cursor-default items-center justify-center rounded-lg bg-primary text-sm font-bold text-white">
                            {{ $page }}
                        </span>
                    @else
                        <button
                            type="button"
                            wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')"
                            x-on:click="{{ $scrollIntoViewJsSnippet }}"
                            wire:loading.attr="disabled"
                            class="flex size-9 items-center justify-center rounded-lg text-sm font-medium text-slate-600 transition-colors hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800"
                        >
                            {{ $page }}
                        </button>
                    @endif
                @endforeach
            @endif
        @endforeach

        {{-- Next --}}
        @if ($paginator->hasMorePages())
            <button
                type="button"
                wire:click="nextPage('{{ $paginator->getPageName() }}')"
                x-on:click="{{ $scrollIntoViewJsSnippet }}"
                wire:loading.attr="disabled"
                class="flex size-9 items-center justify-center rounded-lg border border-slate-200 text-slate-600 transition-colors hover:bg-slate-100 dark:border-slate-800 dark:text-slate-400 dark:hover:bg-slate-800"
                aria-label="{{ __('pagination.next') }}"
            >
                <span class="material-symbols-outlined text-xl">chevron_right</span>
            </button>
        @else
            <span class="flex size-9 cursor-not-allowed items-center justify-center rounded-lg border border-slate-200 text-slate-300 dark:border-slate-800 dark:text-slate-600">
                <span class="material-symbols-outlined text-xl">chevron_right</span>
            </span>
        @endif
    </div>
@endif
