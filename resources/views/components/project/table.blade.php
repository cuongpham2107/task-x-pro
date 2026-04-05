@props(['projects', 'sortBy' => 'created_at', 'sortDir' => 'desc'])

<x-ui.table :paginator="$projects" paginator-label="dự án">
    <x-ui.table.head>
        <x-ui.table.column width="min-w-62.5">
            <button wire:click="setSort('name')"
                class="hover:text-primary flex items-center gap-1 uppercase tracking-wider transition-colors">
                Tên dự án
                <span
                    class="material-symbols-outlined {{ $sortBy === 'name' ? 'text-primary' : 'text-slate-300 dark:text-slate-600' }} text-sm!">
                    {{ $sortBy === 'name' ? ($sortDir === 'asc' ? 'arrow_upward' : 'arrow_downward') : 'unfold_more' }}
                </span>
            </button>
        </x-ui.table.column>
        <x-ui.table.column width="min-w-35" align="center">Người tạo</x-ui.table.column>
        <x-ui.table.column width="min-w-35" align="center">Quản lý</x-ui.table.column>
        <x-ui.table.column width="min-w-40">
            Ngân sách dự kiến
        </x-ui.table.column>
        <x-ui.table.column width="min-w-30" align="start">
            <button wire:click="setSort('start_date')"
                class="hover:text-primary flex items-center gap-1 uppercase tracking-wider transition-colors">
                Ngày bắt đầu
                <span
                    class="material-symbols-outlined {{ $sortBy === 'start_date' ? 'text-primary' : 'text-slate-300 dark:text-slate-600' }} text-sm!">
                    {{ $sortBy === 'start_date' ? ($sortDir === 'asc' ? 'arrow_upward' : 'arrow_downward') : 'unfold_more' }}
                </span>
            </button>
        </x-ui.table.column>

        <x-ui.table.column width="min-w-30" align="start">
            <button wire:click="setSort('end_date')"
                class="hover:text-primary flex items-center gap-1 uppercase tracking-wider transition-colors">
                Hạn chót
                <span
                    class="material-symbols-outlined {{ $sortBy === 'end_date' ? 'text-primary' : 'text-slate-300 dark:text-slate-600' }} text-sm!">
                    {{ $sortBy === 'end_date' ? ($sortDir === 'asc' ? 'arrow_upward' : 'arrow_downward') : 'unfold_more' }}
                </span>
            </button>
        </x-ui.table.column>
        <x-ui.table.column width="min-w-40">Tiến độ</x-ui.table.column>
        <x-ui.table.column width="min-w-30">Trạng thái</x-ui.table.column>
        <x-ui.table.column width="w-16" align="center" :muted="true">Thao tác</x-ui.table.column>
    </x-ui.table.head>

    <x-ui.table.body>
        @forelse($projects as $project)
            @php
                $statusEnum =
                    $project->status instanceof \App\Enums\ProjectStatus
                        ? $project->status
                        : \App\Enums\ProjectStatus::tryFrom($project->status ?? '');
            @endphp

            <x-ui.table.row :href="route('projects.phases.index', $project)">
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
                            {{ strtoupper(substr($project->name, 0, 1)) }}
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-slate-600 dark:text-slate-100">
                                {{ $project->name }}</p>
                            <p class="text-xs text-slate-500">Loại: {{ $project->type->label() }}</p>
                        </div>
                    </div>
                </x-ui.table.cell>

                <x-ui.table.cell align="center">
                    <x-ui.avatar-stack :users="collect([$project->creator])" :max="1" />
                </x-ui.table.cell>

                {{-- Danh sách tham gia --}}
                <x-ui.table.cell align="center">
                    <x-ui.avatar-stack :users="$project->leaders" :max="4" />
                </x-ui.table.cell>
                <x-ui.table.cell>
                    <span
                        class="text-xs text-slate-600 dark:text-slate-400">{{ number_format($project->budget, 0, ',', '.') }}
                        VNĐ</span>
                </x-ui.table.cell>

                <x-ui.table.cell align="start" class="text-xs text-slate-600 dark:text-slate-400">
                    <div class="flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[16px] text-slate-400">calendar_today</span>
                        <span>
                            {{ $project->start_date ? \Carbon\Carbon::parse($project->start_date)->format('d/m/Y') : '—' }}
                        </span>
                    </div>
                </x-ui.table.cell>

                <x-ui.table.cell align="start" class="text-xs text-slate-600 dark:text-slate-400">
                    <div class="flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[16px] text-slate-400">event</span>
                        <span>
                            {{ $project->end_date ? \Carbon\Carbon::parse($project->end_date)->format('d/m/Y') : '—' }}
                        </span>
                    </div>
                </x-ui.table.cell>

                <x-ui.table.cell>
                    @php
                        // Use weighted progress from project (BR-009: SUM(phase.progress * phase.weight / 100))
                        $progress = (int) ($project->progress ?? 0);
                        $totalTasks = $project->tasks_count ?? 0;
                        $doneTasks = $project->done_tasks_count ?? 0;
                        $barColor = match (true) {
                            $progress >= 100 => 'bg-green-500',
                            $progress >= 60 => 'bg-primary',
                            $progress >= 30 => 'bg-yellow-400',
                            default => 'bg-slate-400',
                        };
                    @endphp
                    <div class="flex items-center gap-2">
                        <div class="h-1.5 flex-1 rounded-full bg-slate-200 dark:bg-slate-700">
                            <div class="{{ $barColor }} h-1.5 rounded-full transition-all"
                                style="width: {{ $progress }}%"></div>
                            @if ($totalTasks > 0)
                                <p class="text-2xs mt-0.5 text-slate-400">{{ $doneTasks }}/{{ $totalTasks }}
                                    công
                                    việc</p>
                            @else
                                <p class="text-2xs mt-0.5 text-slate-400">Chưa có công việc</p>
                            @endif

                        </div>
                        <p class="w-7 text-right text-xs font-bold text-slate-600 dark:text-slate-100">
                            {{ $progress }}%
                        </p>
                </x-ui.table.cell>

                <x-ui.table.cell>
                    @if ($statusEnum)
                        <x-ui.badge :class="$statusEnum->badgeClass()" size="xs">
                            <span class="material-symbols-outlined text-[14px]">{{ $statusEnum->icon() }}</span>
                            {{ $statusEnum->label() }}
                        </x-ui.badge>
                    @else
                        <span class="text-xs text-slate-400">—</span>
                    @endif
                </x-ui.table.cell>

                <x-ui.table.cell align="center" x-on:click.stop>
                    <div class="flex items-center justify-center gap-1">
                        <x-ui.icon-button icon="visibility" size="sm" tooltip="Xem chi tiết"
                            href="{{ route('projects.phases.index', $project) }}" />
                        @can('update', $project)
                            @if (($statusEnum?->value ?? '') === 'init')
                                <x-ui.icon-button icon="play_arrow" size="sm" color="blue" tooltip="Bắt đầu dự án"
                                    wire:click.stop="startProject({{ $project->id }})" />
                            @endif

                            <x-ui.icon-button icon="edit" size="sm" tooltip="Sửa"
                                wire:click.stop="openEditProjectModal({{ $project->id }})" />
                        @endcan

                        @can('delete', $project)
                            <x-ui.icon-button icon="delete" size="sm" color="red" tooltip="Xóa"
                                wire:click.stop="confirmDeleteProject({{ $project->id }})" />
                        @endcan

                    </div>
                </x-ui.table.cell>
            </x-ui.table.row>

        @empty
            <x-ui.table.empty :colspan="8" icon="folder_open" message="Chưa có dự án nào." :action-label="auth()->user()?->can('create', App\Models\Project::class) ? 'Tạo dự án mới' : null"
                :action-click="auth()->user()?->can('create', App\Models\Project::class)
                    ? '$dispatch(\'project-create-requested\')'
                    : null" />
        @endforelse
    </x-ui.table.body>

</x-ui.table>
