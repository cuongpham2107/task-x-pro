<div>
    <x-ui.table>
        <x-ui.table.head>
            <x-ui.table.column width="w-12"></x-ui.table.column>
            <x-ui.table.column width="w-16">STT</x-ui.table.column>
            <x-ui.table.column width="min-w-[200px]">Tên giai đoạn</x-ui.table.column>
            <x-ui.table.column>Trọng số (%)</x-ui.table.column>
            <x-ui.table.column width="min-w-[180px]">Thời gian</x-ui.table.column>
            <x-ui.table.column width="min-w-[180px]">Tiến độ</x-ui.table.column>
            <x-ui.table.column>Trạng thái</x-ui.table.column>
            <x-ui.table.column align="right" :muted="true">Thao tác</x-ui.table.column>
        </x-ui.table.head>

        <x-ui.table.body x-init="new Sortable($el, {
            handle: '.cursor-grab',
            animation: 150,
            ghostClass: 'bg-primary/5',
            onEnd: function(evt) {
                let ids = Array.from($el.querySelectorAll('[data-id]')).map(el => el.dataset.id);
                $wire.updateOrder(ids);
            }
        });">
            @forelse ($phases as $index => $phase)
                @php
                    $statusEnum = \App\Enums\PhaseStatus::tryFrom($phase->status);
                    $statusColor = match ($phase->status) {
                        'completed' => 'green',
                        'active' => 'primary',
                        default => 'slate',
                    };
                @endphp
                <x-ui.table.row wire:key="phase-{{ $phase->id }}" data-id="{{ $phase->id }}" :href="route('projects.phases.tasks.index', [$project, $phase])">
                    <x-ui.table.cell class="cursor-grab text-slate-300 active:cursor-grabbing dark:text-slate-700">
                        <span class="material-symbols-outlined">drag_indicator</span>
                    </x-ui.table.cell>
                    <x-ui.table.cell
                        class="text-sm font-medium text-slate-400">{{ $phase->order_index }}</x-ui.table.cell>
                    <x-ui.table.cell>
                        <div class="flex flex-col">
                            <span
                                class="text-sm font-semibold text-slate-900 dark:text-white">{{ $phase->name }}</span>
                            @if ($phase->description)
                                <span class="max-w-xs truncate text-xs text-slate-500">{{ $phase->description }}</span>
                            @endif
                        </div>
                    </x-ui.table.cell>
                    <x-ui.table.cell>
                        <span
                            class="rounded-md bg-slate-100 px-3 py-1 text-sm font-bold dark:bg-slate-800">{{ number_format($phase->weight, 0) }}%</span>
                    </x-ui.table.cell>
                    <x-ui.table.cell>
                        <div class="flex flex-row items-center space-x-1 text-xs text-slate-600 dark:text-slate-400">
                            <span>{{ $phase->start_date?->format('d/m/Y') ?? '—' }}</span>
                            <span class="text-2xs uppercase text-slate-400">-></span>
                            <span>{{ $phase->end_date?->format('d/m/Y') ?? '—' }}</span>
                        </div>
                    </x-ui.table.cell>
                    <x-ui.table.cell>
                        <div class="flex items-center gap-3">
                            <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                <div @class([
                                    'h-full transition-all duration-500',
                                    'bg-green-500' => $phase->progress >= 100,
                                    'bg-primary' => $phase->progress > 0 && $phase->progress < 100,
                                    'bg-slate-300' => $phase->progress == 0,
                                ]) style="width: {{ $phase->progress }}%"></div>
                            </div>
                            <span @class([
                                'text-xs font-bold',
                                'text-green-600 dark:text-green-400' => $phase->progress >= 100,
                                'text-primary' => $phase->progress > 0 && $phase->progress < 100,
                                'text-slate-400' => $phase->progress == 0,
                            ])>{{ $phase->progress }}%</span>
                        </div>
                    </x-ui.table.cell>
                    <x-ui.table.cell>
                        <x-ui.badge :color="$statusColor"
                            size="xs">{{ $statusEnum?->label() ?? $phase->status }}</x-ui.badge>
                    </x-ui.table.cell>
                    <x-ui.table.cell align="right" x-on:click.stop>
                        <div class="flex items-center justify-end gap-1">
                            <x-ui.icon-button icon="visibility" size="sm" tooltip="Sửa"
                                href="{{ route('projects.phases.tasks.index', [$project, $phase]) }}" />
                            @can('update', $phase)
                                @if ($phase->status === 'pending')
                                    <x-ui.icon-button icon="play_circle" size="sm" color="blue" tooltip="Bắt đầu"
                                        wire:click="confirmStartStatus({{ $phase->id }})" />
                                @elseif($phase->status === 'active')
                                    <x-ui.icon-button icon="stop" size="sm" tooltip="Hoàn thành" :hidden="$phase->progress !== 100"
                                        wire:click="confirmCompleteStatus({{ $phase->id }})" />
                                @endif
                            @endcan
                            @can('update', $phase)
                                <x-ui.icon-button icon="edit" size="sm" tooltip="Sửa"
                                    wire:click="openEditPhaseModal({{ $phase->id }})" />
                            @endcan

                            @can('delete', $phase)
                                <x-ui.icon-button icon="delete" size="sm" color="red" tooltip="Xóa"
                                    wire:click="confirmDeletePhase({{ $phase->id }})" />
                            @endcan
                        </div>
                    </x-ui.table.cell>
                </x-ui.table.row>
            @empty
                <x-ui.table.empty colspan="8" />
            @endforelse
        </x-ui.table.body>
    </x-ui.table>
    {{-- ─────────────────── Footer help text ──────────────────────────────── --}}
    <div class="mt-6 flex items-center justify-between">
        <p class="items-center text-sm text-yellow-700">
            * Kéo thả biểu tượng
            <span class="material-symbols-outlined align-middle text-sm">drag_indicator</span>
            để thay đổi thứ tự giai đoạn.
        </p>
    </div>
</div>
