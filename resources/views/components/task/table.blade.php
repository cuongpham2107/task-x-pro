@props(['tasks', 'sortBy' => 'deadline', 'sortDir' => 'asc'])

<x-ui.table :paginator="$tasks" paginator-label="công việc">
    <x-ui.table.head>
        <x-ui.table.column width="min-w-62.5">
            <button wire:click="setSort('name')"
                class="hover:text-primary flex items-center gap-1 uppercase tracking-wider transition-colors">
                Tên công việc
                <span
                    class="material-symbols-outlined {{ $sortBy === 'name' ? 'text-primary' : 'text-slate-300 dark:text-slate-600' }} text-sm!">
                    {{ $sortBy === 'name' ? ($sortDir === 'asc' ? 'arrow_upward' : 'arrow_downward') : 'unfold_more' }}
                </span>
            </button>
        </x-ui.table.column>
        <x-ui.table.column width="min-w-60 w-60">Dự án &amp; Giai đoạn</x-ui.table.column>
        <x-ui.table.column width="min-w-35" align="center">Leader quản lý</x-ui.table.column>
        <x-ui.table.column width="min-w-35" align="center">Chủ PIC</x-ui.table.column>
        <x-ui.table.column width="min-w-35" align="center">PIC hỗ trợ</x-ui.table.column>
        <x-ui.table.column width="min-w-30">
            <button wire:click="setSort('priority')"
                class="hover:text-primary flex items-center gap-1 uppercase tracking-wider transition-colors">
                Độ ưu tiên
                <span
                    class="material-symbols-outlined {{ $sortBy === 'priority' ? 'text-primary' : 'text-slate-300 dark:text-slate-600' }} text-sm!">
                    {{ $sortBy === 'priority' ? ($sortDir === 'asc' ? 'arrow_upward' : 'arrow_downward') : 'unfold_more' }}
                </span>
            </button>
        </x-ui.table.column>
        <x-ui.table.column width="min-w-30">
            <button wire:click="setSort('deadline')"
                class="hover:text-primary flex items-center gap-1 uppercase tracking-wider transition-colors">
                Hạn chót
                <span
                    class="material-symbols-outlined {{ $sortBy === 'deadline' ? 'text-primary' : 'text-slate-300 dark:text-slate-600' }} text-sm!">
                    {{ $sortBy === 'deadline' ? ($sortDir === 'asc' ? 'arrow_upward' : 'arrow_downward') : 'unfold_more' }}
                </span>
            </button>
        </x-ui.table.column>
        <x-ui.table.column width="min-w-35">
            <button wire:click="setSort('status')"
                class="hover:text-primary flex items-center gap-1 uppercase tracking-wider transition-colors">
                Trạng thái
                <span
                    class="material-symbols-outlined {{ $sortBy === 'status' ? 'text-primary' : 'text-slate-300 dark:text-slate-600' }} text-sm!">
                    {{ $sortBy === 'status' ? ($sortDir === 'asc' ? 'arrow_upward' : 'arrow_downward') : 'unfold_more' }}
                </span>
            </button>
        </x-ui.table.column>
        <x-ui.table.column width="w-16" align="center" :muted="true">Thao tác</x-ui.table.column>
    </x-ui.table.head>

    <x-ui.table.body>
        @forelse($tasks as $task)
            @php
                $statusEnum =
                    $task->status instanceof \App\Enums\TaskStatus
                        ? $task->status
                        : \App\Enums\TaskStatus::tryFrom($task->status ?? '');
                $priorityEnum =
                    $task->priority instanceof \App\Enums\TaskPriority
                        ? $task->priority
                        : \App\Enums\TaskPriority::tryFrom($task->priority ?? '');
                $isOverdue = $task->deadline && $statusEnum !== \App\Enums\TaskStatus::Completed && $task->deadline->isPast();
                $priorityColor = match ($priorityEnum?->value ?? '') {
                    'urgent' => 'red',
                    'high' => 'orange',
                    'medium' => 'amber',
                    default => 'blue',
                };
            @endphp

            <x-ui.table.row wire:key="task-row-{{ $task->id }}" wire:click.stop="openEditTask({{ $task->id }})" class="cursor-pointer">
                <x-ui.table.cell>
                    <div class="flex items-center gap-3">
@php
                        $__avatarColorOptions = [
                            'bg-primary/10 text-primary',
                            'bg-emerald-100 text-emerald-700',
                            'bg-blue-50 text-blue-600',
                            'bg-amber-100 text-amber-700',
                            'bg-purple-100 text-purple-700',
                            'bg-pink-100 text-pink-700',
                            'bg-slate-100 text-slate-700',
                            'bg-indigo-50 text-indigo-700',
                        ];
                        $avatarColorClass = $__avatarColorOptions[array_rand($__avatarColorOptions)];
                    @endphp

                    <div
                        class="{{ $avatarColorClass }} flex size-10 items-center justify-center rounded-full text-lg font-bold">
                        {{ strtoupper(substr($task->name, 0, 1)) }}
                    </div>
                    <div class="flex flex-col gap-1">
                        <span class="text-sm font-semibold text-slate-900 dark:text-slate-100">
                            {{ $task->name }}
                        </span>
                        @if ($task->description)
                            <span class="text-xs text-slate-500">
                                {{ str($task->description)->limit(70) }}
                            </span>
                        @else
                            <span class="text-xs text-slate-400">#{{ str_pad((string) $task->id, 4, '0', STR_PAD_LEFT) }}</span>
                        @endif
                    </div>
                    </div>
                    
                </x-ui.table.cell>

                <x-ui.table.cell>
                    <div class="flex flex-wrap gap-1">
                        <span class="text-sm font-medium text-slate-900 dark:text-slate-100">
                            {{ $task->phase?->project?->name ?? '—' }}
                        </span>
                        <x-ui.badge color="slate" size="xs">
                            {{ $task->phase?->name ?? '—' }}
                        </x-ui.badge>
                    </div>
                </x-ui.table.cell>

                <x-ui.table.cell align="center">
                    @php
                        $leaders = $task->phase?->project?->leaders ?? collect();
                    @endphp
                    <x-ui.avatar-stack :users="collect($leaders)" :max="3" />
                </x-ui.table.cell>

                <x-ui.table.cell align="center">
                    <x-ui.avatar-stack :users="collect([$task->pic])" :max="1" />
                </x-ui.table.cell>

                <x-ui.table.cell align="center">
                    <x-ui.avatar-stack :users="$task->coPics" :max="4" />
                </x-ui.table.cell>

                <x-ui.table.cell>
                    <x-ui.badge :color="$priorityColor" size="xs">
                        {{ $priorityEnum?->label() ?? '—' }}
                    </x-ui.badge>
                </x-ui.table.cell>

                <x-ui.table.cell>
                    @if ($task->deadline)
                        <span
                            class="{{ $isOverdue ? 'text-red-600 font-semibold' : 'text-slate-600 dark:text-slate-400' }} text-sm">
                            {{ $task->deadline->translatedFormat('d/m/Y') }}
                        </span>
                    @else
                        <span class="text-sm text-slate-400">—</span>
                    @endif
                </x-ui.table.cell>

                <x-ui.table.cell>
                    <x-ui.badge :color="$statusEnum?->color() ?? 'slate'" size="xs">
                        {{ $statusEnum?->label() ?? '—' }}
                    </x-ui.badge>
                </x-ui.table.cell>

                <x-ui.table.cell align="center" x-on:click.stop>
                    <div class="flex items-center justify-center gap-1">
                        @can('update', $task)
                            <x-ui.icon-button icon="edit" size="sm" tooltip="Sửa"
                                wire:click.stop="openEditTask({{ $task->id }})" />
                        @endcan

                        @can('delete', $task)
                            <x-ui.icon-button icon="delete" size="sm" color="red" tooltip="Xóa"
                                wire:click.stop="deleteTask({{ $task->id }})"
                                wire:confirm="Bạn có chắc muốn xóa công việc này không?" />
                        @endcan
                    </div>
                </x-ui.table.cell>
            </x-ui.table.row>
        @empty
            <x-ui.table.empty :colspan="9" icon="task_alt" message="Chưa có công việc nào." />
        @endforelse
    </x-ui.table.body>
</x-ui.table>
