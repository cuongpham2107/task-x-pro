@props(['field', 'sortBy', 'sortDir', 'align' => 'left', 'width' => null])

<x-ui.table.column :align="$align" :width="$width" {{ $attributes->merge(['class' => 'group']) }}>
    <button type="button" wire:click="setSort('{{ $field }}')"
        class="hover:text-primary inline-flex items-center gap-1 uppercase tracking-wider transition-colors">
        {{ $slot }}
        <span
            class="material-symbols-outlined text-sm! {{ $sortBy === $field ? 'text-primary' : 'text-slate-300 group-hover:text-slate-400 dark:text-slate-600' }}">
            {{ $sortBy === $field ? ($sortDir === 'asc' ? 'arrow_upward' : 'arrow_downward') : 'unfold_more' }}
        </span>
    </button>
</x-ui.table.column>
