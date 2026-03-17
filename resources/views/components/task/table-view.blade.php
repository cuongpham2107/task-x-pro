@props([
    'tasks',
    'taskStats' => ['total' => 0, 'done' => 0, 'in_progress' => 0],
    'project',
    'filterStatus' => '',
    'filterPriority' => '',
    'sortBy' => 'status',
    'sortDir' => 'asc',
])

@php
    $hasActiveFilter = $filterStatus !== '' || $filterPriority !== '';
@endphp

<div class="flex flex-col gap-4">
    {{-- Filters Bar --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="text-xs font-medium text-slate-500">
            Tổng {{ $taskStats['total'] }} công việc
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <x-ui.filter-select model="filterStatus" :value="$filterStatus" label="Trạng thái" icon="circle" all-label="Tất cả"
                :options="App\Enums\TaskStatus::optionsWithColors()" />

            <x-ui.filter-select model="filterPriority" :value="$filterPriority" label="Ưu tiên" icon="flag" all-label="Tất cả"
                :options="App\Enums\TaskPriority::options()" />

            {{-- Clear filters --}}
            @if ($hasActiveFilter)
                <button wire:click="clearFilters"
                    class="flex items-center gap-1 rounded-lg border border-red-200 px-3 py-1.5 text-xs font-medium text-red-500 transition-colors hover:bg-red-50 dark:border-red-800 dark:hover:bg-red-900/20">
                    <span class="material-symbols-outlined text-sm">filter_alt_off</span>
                    Xóa bộ lọc
                </button>
            @endif
        </div>
    </div>

    {{-- Table --}}
    <x-ui.table :paginator="$tasks" paginator-label="công việc">
        <x-ui.table.head>
            <x-ui.table.column width="w-2/5">
                <button wire:click="sort('name')" class="hover:text-primary flex items-center gap-1 transition-colors">
                    Tên công việc
                    <span class="material-symbols-outlined text-base">
                        {{ $sortBy === 'name' ? ($sortDir === 'asc' ? 'arrow_drop_up' : 'arrow_drop_down') : 'unfold_more' }}
                    </span>
                </button>
            </x-ui.table.column>
            <x-ui.table.column>Người thực hiện</x-ui.table.column>
            <x-ui.table.column>
                <button wire:click="sort('deadline')"
                    class="hover:text-primary flex items-center gap-1 transition-colors">
                    Hạn chót
                    <span class="material-symbols-outlined text-base">
                        {{ $sortBy === 'deadline' ? ($sortDir === 'asc' ? 'arrow_drop_up' : 'arrow_drop_down') : 'unfold_more' }}
                    </span>
                </button>
            </x-ui.table.column>
            <x-ui.table.column>Mức độ ưu tiên</x-ui.table.column>
            <x-ui.table.column>Trạng thái</x-ui.table.column>
            <x-ui.table.column :muted="true" width="w-10"></x-ui.table.column>
        </x-ui.table.head>

        <x-ui.table.body>
            @forelse ($tasks as $task)
                @php
                    $isDone = $task->status === App\Enums\TaskStatus::Completed;
                    $isOverdue = $task->deadline && !$isDone && $task->deadline->isPast();
                    $priority =
                        $task->priority instanceof App\Enums\TaskPriority
                            ? $task->priority
                            : App\Enums\TaskPriority::from($task->priority);
                    $status =
                        $task->status instanceof App\Enums\TaskStatus
                            ? $task->status
                            : App\Enums\TaskStatus::from($task->status);
                    $isNearDeadline = $task->deadline && !$isDone && $task->deadline->lte(now()->addDays(3));

                    // Dependency block
                    $depTask = $task->dependencyTask;
                    $depStatus = $depTask?->status instanceof \BackedEnum ? $depTask->status->value : $depTask?->status;
                    $hasDependencyBlock = $depTask !== null && $depStatus !== 'completed';
                @endphp

                <x-ui.table.row wire:click="openEditTask({{ $task->id }})"
                    class="{{ $isDone ? 'opacity-70' : '' }} {{ $isNearDeadline ? 'bg-amber-50/60 dark:bg-amber-900/10' : '' }} cursor-pointer">
                    {{-- Tên công việc --}}
                    <x-ui.table.cell>
                        <div class="flex items-center gap-3">
                            <div class="min-w-0">
                                <div
                                    class="{{ $isDone ? 'line-through text-slate-400' : 'text-slate-900 dark:text-white' }} truncate font-semibold">
                                    {{ $task->name }}
                                </div>
                                @if ($task->description)
                                    <div class="mt-0.5 max-w-xs truncate text-xs text-slate-500">
                                        {{ $task->description }}
                                    </div>
                                @endif
                            </div>
                        </div>
                    </x-ui.table.cell>

                    {{-- Người thực hiện --}}
                    <x-ui.table.cell>
                        <x-ui.avatar-stack :users="collect([$task->pic])
                            ->concat($task->coPics)
                            ->filter()" :max="3" :size="8" />
                    </x-ui.table.cell>

                    {{-- Hạn chót --}}
                    <x-ui.table.cell>
                        @if ($task->deadline)
                            <div
                                class="{{ $isOverdue ? 'text-red-500 font-bold' : ($isDone ? 'text-slate-400' : 'text-slate-600 dark:text-slate-400') }} flex items-center space-x-2 text-sm font-medium">
                                <span>{{ $task->deadline->translatedFormat('d/m/Y') }}</span>
                                @if ($isOverdue)
                                    <span class="material-symbols-outlined align-middle text-xs">warning</span>
                                @endif
                            </div>
                        @else
                            <span class="text-xs text-slate-400">—</span>
                        @endif
                    </x-ui.table.cell>

                    {{-- Ưu tiên --}}
                    <x-ui.table.cell>
                        <x-ui.badge :color="match ($priority) {
                            App\Enums\TaskPriority::Urgent => 'red',
                            App\Enums\TaskPriority::High => 'orange',
                            App\Enums\TaskPriority::Medium => 'amber',
                            default => 'blue',
                        }" size="xs">
                            {{ $priority->label() }}
                        </x-ui.badge>
                    </x-ui.table.cell>

                    {{-- Trạng thái --}}
                    <x-ui.table.cell>
                        <div class="flex items-center gap-2">
                            <span class="{{ $status->dotClass() }} h-2 w-2 shrink-0 rounded-full"></span>
                            <span class="{{ $status->badgeClass() }} rounded-full px-2 py-0.5 text-xs font-semibold">
                                {{ $status->label() }}
                            </span>
                        </div>
                    </x-ui.table.cell>

                    {{-- Thao tác --}}
                    <x-ui.table.cell align="right">
                        <div class="flex items-center justify-end gap-1">
                            @if ($status === App\Enums\TaskStatus::Pending && !$hasDependencyBlock)
                                <x-ui.icon-button icon="play_circle" size="sm" color="primary"
                                    tooltip="Bắt đầu ngay" wire:click="startTask({{ $task->id }})" />
                            @endif

                            @can('view', $task)
                                <x-ui.icon-button :icon="auth()->user()->can('update', $task) ? 'edit' : 'visibility'" size="sm" color="slate" :tooltip="auth()->user()->can('update', $task) ? 'Chỉnh sửa' : 'Xem chi tiết'"
                                    wire:click="openEditTask({{ $task->id }})" />
                            @endcan
                            @can('delete', $task)
                                <x-ui.icon-button icon="delete" size="sm" color="red" tooltip="Xóa"
                                    wire:click="deleteTask({{ $task->id }})"
                                    wire:confirm="Bạn có chắc muốn xóa công việc này không?" />
                            @endcan
                        </div>
                    </x-ui.table.cell>
                </x-ui.table.row>
            @empty
                <x-ui.table.empty :colspan="6" icon="task_alt" message="Chưa có công việc nào." />
            @endforelse
        </x-ui.table.body>
    </x-ui.table>

    {{-- Footer --}}
    <div class="flex items-center justify-between px-1">
        @can('create', App\Models\Task::class)
            <button @click="$dispatch('task-create-requested')"
                class="text-primary flex items-center gap-1.5 text-sm font-bold hover:underline">
                <span class="material-symbols-outlined text-sm">add</span>
                Thêm công việc mới
            </button>
        @else
            <div></div>
        @endcan
        <div class="flex items-center gap-4 text-xs font-medium text-slate-400">
            <span>{{ $taskStats['done'] }}/{{ $taskStats['total'] }} hoàn thành</span>
            @if ($taskStats['in_progress'] > 0)
                <span class="text-primary">{{ $taskStats['in_progress'] }} đang làm</span>
            @endif
        </div>
    </div>
</div>
