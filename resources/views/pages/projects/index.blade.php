<?php

use App\Enums\ProjectStatus;
use App\Exports\ProjectDetailExport;
use App\Exports\ProjectReportExport;
use App\Models\Project;
use App\Services\Projects\ProjectService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Quản lý dự án')] class extends Component
{
    use WithPagination;

    protected ProjectService $projectService;

    public function boot(ProjectService $projectService): void
    {
        $this->projectService = $projectService;
    }

    #[Url(as: 'tab', except: 'all')]
    public string $tab = 'all';

    #[Url(as: 'sort', except: 'created_at')]
    public string $sortBy = 'created_at';

    #[Url(as: 'dir', except: 'desc')]
    public string $sortDir = 'desc';

    public string $viewMode = 'table';

    #[Url(as: 'type', except: '')]
    public ?string $filterType = null;

    #[Url(as: 'status', except: '')]
    public ?string $filterStatus = null;

    #[Url(as: 'manager', except: '')]
    public ?string $filterManagerId = null;

    #[Url(as: 'start', except: '')]
    public ?string $filterStartDate = null;

    #[Url(as: 'end', except: '')]
    public ?string $filterEndDate = null;

    #[Url(as: 'q', except: '')]
    public ?string $filterSearch = null;

    public bool $showDeleteModal = false;

    public ?int $pendingDeleteProjectId = null;

    public string $pendingDeleteProjectName = '';

    public bool $showDateRangeModal = false;

    public bool $showProjectSelectModal = false;

    public ?string $filterExportStartDate = null;

    public ?string $filterExportEndDate = null;

    public string $exportProjectSearch = '';

    public ?int $selectedExportProjectId = null;

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
        $this->resetPage();
    }

    public function setSort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDir = 'asc';
        }
        $this->resetPage();
    }

    public function switchView(string $mode): void
    {
        if (in_array($mode, ['table', 'gantt'], true)) {
            $this->viewMode = $mode;
        }
    }

    public function updatedFilterSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterType(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatedFilterManagerId(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStartDate(): void
    {
        $this->resetPage();
    }

    public function updatedFilterEndDate(): void
    {
        $this->resetPage();
    }

    #[On('project-saved')]
    public function refreshAfterProjectSaved(): void
    {
        unset($this->projects, $this->tabs);
    }

    public function openEditProjectModal(int $projectId): void
    {
        $project = Project::query()->findOrFail($projectId);

        Gate::forUser(auth()->user())->authorize('update', $project);

        $this->dispatch('project-edit-requested', projectId: $project->id);
    }

    public function startProject(int $projectId, \App\Services\Projects\ProjectPhaseService $phaseService): void
    {
        $project = Project::query()->findOrFail($projectId);

        Gate::forUser(auth()->user())->authorize('update', $project);

        $project->update(['status' => ProjectStatus::Running->value]);
        $phaseService->syncPhaseStatusesWithProjectStatus($project);

        $this->refreshAfterProjectSaved();
        $this->dispatch('toast', message: 'Dự án đã được bắt đầu thành công!', type: 'success');
    }

    public function confirmDeleteProject(int $projectId): void
    {
        $project = Project::query()->findOrFail($projectId);

        Gate::forUser(auth()->user())->authorize('delete', $project);

        $this->pendingDeleteProjectId = $project->id;
        $this->pendingDeleteProjectName = $project->name;
        $this->showDeleteModal = true;
    }

    public function closeDeleteModal(): void
    {
        $this->showDeleteModal = false;
        $this->pendingDeleteProjectId = null;
        $this->pendingDeleteProjectName = '';
    }

    public function deleteProject(): void
    {
        if ($this->pendingDeleteProjectId === null) {
            return;
        }

        try {
            $project = Project::query()->findOrFail($this->pendingDeleteProjectId);

            $this->projectService->delete(auth()->user(), $project);

            $this->closeDeleteModal();
            $this->resetPage();
            unset($this->projects, $this->tabs);

            session()->flash('success', 'Dự án đã được xóa thành công!');
            $this->dispatch('toast', message: 'Dự án đã được xóa thành công!', type: 'success');
        } catch (\Exception $e) {
            session()->flash('error', 'Không thể xóa dự án: '.$e->getMessage());
            $this->dispatch('toast', message: 'Không thể xóa dự án: '.$e->getMessage(), type: 'error');
        }
    }

    public function cloneProject(int $projectId): void
    {
        try {
            $sourceProject = Project::query()->findOrFail($projectId);

            Gate::forUser(auth()->user())->authorize('view', $sourceProject);

            $clonedProject = $this->projectService->clone(auth()->user(), $sourceProject);

            unset($this->projects, $this->tabs);

            $this->dispatch('toast', message: 'Dự án "'.$sourceProject->name.'" đã được sao chép thành công!', type: 'success');
        } catch (\Exception $e) {
            $this->dispatch('toast', message: 'Không thể sao chép dự án: '.$e->getMessage(), type: 'error');
        }
    }

    #[Computed]
    public function types(): array
    {
        return $this->projectService->getTypeOptions();
    }

    #[Computed]
    public function statuses(): array
    {
        return $this->projectService->getStatusOptions();
    }

    #[Computed]
    public function managers(): array
    {
        return $this->projectService->getManagerOptions();
    }

    #[Computed]
    public function tabs(): array
    {
        return $this->projectService->getTabCounts(auth()->user());
    }

    #[Computed]
    public function projects()
    {
        return $this->projectService->paginateForIndex(auth()->user(), [
            'tab' => $this->tab,
            'sort' => $this->sortBy,
            'dir' => $this->sortDir,
            'type' => $this->filterType,
            'status' => $this->filterStatus,
            'manager_id' => $this->filterManagerId,
            'start_date' => $this->filterStartDate,
            'end_date' => $this->filterEndDate,
            'search' => $this->filterSearch,
        ]);
    }

    #[Computed]
    public function projectsForExport()
    {
        return Project::query()
            ->with('projectType:id,label')
            ->where(function ($q) {
                if ($this->exportProjectSearch) {
                    $q->where('name', 'like', '%'.$this->exportProjectSearch.'%');
                }
            })
            ->orderBy('name')
            ->get(['id', 'name', 'project_type_id']);
    }

    public function exportOverallReport()
    {
        $this->validate([
            'filterExportStartDate' => 'required|date',
            'filterExportEndDate' => 'required|date|after_or_equal:filterExportStartDate',
        ], [
            'filterExportStartDate.required' => 'Vui lòng chọn ngày bắt đầu.',
            'filterExportStartDate.date' => 'Ngày bắt đầu không hợp lệ.',
            'filterExportEndDate.required' => 'Vui lòng chọn ngày kết thúc.',
            'filterExportEndDate.date' => 'Ngày kết thúc không hợp lệ.',
            'filterExportEndDate.after_or_equal' => 'Ngày kết thúc phải lớn hơn hoặc bằng ngày bắt đầu.',
        ]);

        $from = $this->filterExportStartDate;
        $to = $this->filterExportEndDate;

        $projects = Project::query()
            ->whereDate('start_date', '>=', $from)
            ->whereDate('end_date', '<=', $to)
            ->with(['leaders', 'activityLogs' => function ($q) {
                $q->where('action', 'progress_updated')
                    ->where('created_at', '<', now()->subDays(7))
                    ->latest();
            }])
            ->get();

        $total = $projects->count();
        $running = $projects->filter(fn ($p) => $p->status === ProjectStatus::Running)->count();
        $overdue = $projects->filter(fn ($p) => $p->status === ProjectStatus::Overdue)->count();
        $completed = $projects->filter(fn ($p) => $p->status === ProjectStatus::Completed)->count();
        $slow = $projects->filter(fn ($p) => $p->progress < 60
            && $p->end_date
            && $p->end_date <= now()->addDays(30)
            && ! in_array($p->status, [ProjectStatus::Completed, ProjectStatus::Overdue, ProjectStatus::Cancelled])
        )->count();

        $projectData = $projects->map(function ($project) {
            $progress7dAgo = $project->activityLogs->first()?->new_values['progress'] ?? null;
            $status = $project->status instanceof ProjectStatus ? $project->status : ProjectStatus::tryFrom($project->status);

            return [
                'name' => $project->name,
                'leader_names' => $project->leaders->pluck('name')->implode(', '),
                'start_date' => $project->start_date?->format('d/m/Y') ?? '—',
                'end_date' => $project->end_date?->format('d/m/Y') ?? '—',
                'progress' => $project->progress,
                'progress_7d_ago' => $progress7dAgo,
                'status_label' => $status?->label() ?? $project->status,
                'status_key' => $status?->value ?? $project->status,
            ];
        })->toArray();

        $filename = 'bao-cao-tong-du-an-'.now()->format('Y-m-d-His').'.xlsx';

        $this->showDateRangeModal = false;
        $this->dispatch('toast', message: 'Bắt đầu xuất báo cáo tổng dự án', type: 'info');

        return (new ProjectReportExport(
            $projectData,
            [
                'total' => $total,
                'running' => $running,
                'overdue' => $overdue,
                'slow' => $slow,
                'completed' => $completed,
            ],
            \Carbon\Carbon::parse($from)->format('d/m/Y'),
            \Carbon\Carbon::parse($to)->format('d/m/Y'),
            auth()->user()?->name ?? 'Hệ thống',
        ))->download($filename);
    }

    public function exportProjectDetail()
    {
        $this->validate([
            'selectedExportProjectId' => 'required|exists:projects,id',
        ], [
            'selectedExportProjectId.required' => 'Vui lòng chọn một dự án.',
            'selectedExportProjectId.exists' => 'Dự án không tồn tại.',
        ]);

        $project = Project::query()
            ->with([
                'leaders:id,name',
                'phases' => function ($q) {
                    $q->orderBy('order_index');
                },
                'phases.tasks' => function ($q) {
                    $q->with(['pic:id,name', 'creator:id,name']);
                },
                'phases.tasks.activityLogs' => function ($q) {
                    $q->where('action', 'progress_updated')
                        ->where('created_at', '<', now()->subDays(7))
                        ->latest();
                },
            ])
            ->findOrFail($this->selectedExportProjectId);

        $project->phases->each->refreshProgressFromTasks();
        $project->refresh();

        $tasks = $project->phases->flatMap->tasks;
        $totalTasks = $tasks->count();
        $completedTasks = $tasks->filter(fn ($t) => $t->status === \App\Enums\TaskStatus::Completed)->count();
        $inProgressTasks = $tasks->filter(fn ($t) => $t->status === \App\Enums\TaskStatus::InProgress)->count();
        $lateTasks = $tasks->filter(fn ($t) => $t->status === \App\Enums\TaskStatus::Late)->count();
        $pendingTasks = $tasks->filter(fn ($t) => $t->status === \App\Enums\TaskStatus::Pending)->count();

        $phaseData = $project->phases->map(function ($phase) {
            return [
                'name' => $phase->name,
                'tasks' => $phase->tasks->map(function ($task) {
                    $progress7dAgo = $task->activityLogs->first()?->new_values['progress'] ?? null;

                    return [
                        'name' => $task->name,
                        'leader_name' => $task->creator?->name ?? '—',
                        'pic_name' => $task->pic?->name ?? '—',
                        'priority_label' => $task->priority instanceof \App\Enums\TaskPriority ? $task->priority->label() : $task->priority,
                        'progress' => $task->progress,
                        'progress_7d_ago' => $progress7dAgo,
                        'status_label' => $task->status instanceof \App\Enums\TaskStatus ? $task->status->label() : $task->status,
                        'deadline' => $task->deadline?->format('d/m/Y') ?? '—',
                    ];
                })->toArray(),
            ];
        })->toArray();

        $filename = 'bao-cao-du-an-'.now()->format('Y-m-d-His').'.xlsx';

        $this->showProjectSelectModal = false;
        $this->selectedExportProjectId = null;
        $this->exportProjectSearch = '';
        $this->dispatch('toast', message: 'Bắt đầu xuất báo cáo dự án', type: 'info');

        return (new ProjectDetailExport(
            $project,
            $phaseData,
            [
                'total_tasks' => $totalTasks,
                'completed_tasks' => $completedTasks,
                'in_progress_tasks' => $inProgressTasks,
                'late_tasks' => $lateTasks,
                'pending_tasks' => $pendingTasks,
                'progress' => $project->progress,
            ],
            auth()->user()?->name ?? 'Hệ thống',
        ))->download($filename);
    }

    public function updatedExportProjectSearch(): void
    {
        unset($this->projectsForExport);
    }
};
?>

<div>
    <div class="mb-4 flex items-center justify-between gap-4">
        <x-ui.heading title="Danh sách dự án" description="Quản lý và theo dõi tiến độ các dự án đang triển khai"
            class="mb-0" />
        <div class="flex items-center gap-3">
            <div
                class="flex items-center gap-1 rounded-lg border border-slate-200 bg-slate-100 p-1 dark:border-slate-700 dark:bg-slate-800">
                <x-ui.button size="sm" :variant="$viewMode === 'table' ? 'primary' : 'ghost'" wire:click="switchView('table')">Bảng</x-ui.button>
                <x-ui.button size="sm" :variant="$viewMode === 'gantt' ? 'primary' : 'ghost'" wire:click="switchView('gantt')">Gantt</x-ui.button>
            </div>
            @if (auth()->user()?->can('create', App\Models\Project::class))
                <x-ui.button icon="add" size="sm" wire:click="$dispatch('project-create-requested')">
                    Tạo dự án
                </x-ui.button>
            @endif
            @if(auth()->user()?->can('exportProjects'))
                <div x-data="{ open: false }" class="relative">
                    <x-ui.button icon="file_download" size="sm" @click="open = !open">
                        Xuất báo cáo
                    </x-ui.button>
                    <div x-show="open" @click.outside="open = false" x-cloak
                        class="absolute right-0 z-50 mt-2 w-64 origin-top-right rounded-xl border border-slate-200 bg-white py-1 shadow-lg dark:border-slate-700 dark:bg-slate-800">
                        <button wire:click="$set('showDateRangeModal', true)" @click="open = false"
                            class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-sm text-slate-700 transition-colors hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-700">
                            <span class="material-symbols-outlined text-[20px] text-slate-400">calendar_month</span>
                            <span>Xuất báo cáo tổng dự án</span>
                        </button>
                        <button wire:click="$set('showProjectSelectModal', true)" @click="open = false"
                            class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-sm text-slate-700 transition-colors hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-700">
                            <span class="material-symbols-outlined text-[20px] text-slate-400">folder_open</span>
                            <span>Xuất báo cáo dự án cụ thể</span>
                        </button>
                    </div>
                </div>
            @endif


        </div>
    </div>
    <!-- Project Table -->
    <div class="mb-4">
        <div class="mb-8 grid grid-cols-1 gap-4 md:grid-cols-4">
            {{-- Total projects --}}
            <div class="flex flex-col gap-1 rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Tổng số dự án</p>
                <div class="flex items-baseline gap-2">
                    <span class="text-2xl font-bold dark:text-white text-slate-600">{{ $this->tabs['all']['count'] ?? 0 }}</span>
                </div>
            </div>

            {{-- Running projects --}}
            <div class="flex flex-col gap-1 rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Đang chạy</p>
                <div>
                    <span class="text-2xl font-bold text-slate-600 dark:text-white">{{ $this->tabs['running']['count'] ?? 0 }}</span>
                </div>
            </div>

            {{-- Paused projects --}}
            <div class="flex flex-col gap-1 rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Tạm dừng</p>
                <div>
                    <span class="text-2xl font-bold text-slate-600 dark:text-white">{{ $this->tabs['paused']['count'] ?? 0 }}</span>
                </div>
            </div>

            {{-- Completed projects --}}
            <div class="flex flex-col gap-1 rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Hoàn thành</p>
                <div>
                    <span class="text-2xl font-bold text-slate-600 dark:text-white">{{ $this->tabs['completed']['count'] ?? 0 }}</span>
                </div>
            </div>
        </div>
    </div>
    <!-- Controls Section -->
    <div class="mb-2 flex items-center justify-between gap-2 md:gap-2 md:pb-0 dark:border-slate-800">
        {{-- Search (left) + Filters (right) --}}
        <div class="flex items-end w-full gap-2">
            <div class="shrink-0 w-full xl:w-64">
                <x-ui.filter-search model="filterSearch" placeholder="Tìm dự án..." width="w-full" />
            </div>

            <div class="items-end gap-3 ml-auto flex-wrap hidden xl:flex">
                <div class="shrink-0">
                    <x-ui.filter-select model="filterType" :value="$filterType" label="Loại hình" icon="style"
                        all-label="Tất cả loại hình" width="w-44" drop-width="w-52" :options="[]">
                        @foreach ($this->types as $value => $label)
                            <button wire:click="$set('filterType', '{{ $value }}')"
                                class="{{ (string) $filterType === (string) $value
                                    ? 'bg-primary/5 text-primary font-semibold'
                                    : 'text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800' }} flex w-full items-center justify-between px-3 py-2 text-left text-xs transition-colors">
                                {{ $label }}
                                @if ((string) $filterType === (string) $value)
                                    <span class="material-symbols-outlined text-sm">check</span>
                                @endif
                            </button>
                        @endforeach
                    </x-ui.filter-select>
                </div>

                <div class="shrink-0">
                    <x-ui.filter-select model="filterStatus" :value="$filterStatus" label="Trạng thái" icon="filter_alt"
                        all-label="Tất cả trạng thái" width="w-44" drop-width="w-56" :options="$this->statuses" />
                </div>

                <div class="shrink-0">
                    <x-ui.filter-select model="filterManagerId" :value="$filterManagerId" label="Quản lý" icon="supervisor_account"
                        all-label="Tất cả quản lý" width="w-44" drop-width="w-72" :options="$this->managers" />
                </div>

                <div class="shrink-0 lg:w-44">
                    <x-ui.datepicker class="py-1 rounded-lg bg-white dark:bg-slate-800" label="Thời gian bắt đầu" name="filterStartDate" wire:model.live="filterStartDate" />
                </div>

                <div class="shrink-0 lg:w-44">
                    <x-ui.datepicker class="py-1 rounded-lg bg-white dark:bg-slate-800" label="Thời gian kết thúc" name="filterEndDate" wire:model.live="filterEndDate" />
                </div>

                <div class="shrink-0">
                    <x-ui.filter-sort :sort-by="$sortBy" :sort-dir="$sortDir" :options="[
                        'created_at' => 'Ngày tạo',
                        'name' => 'Tên dự án',
                        'start_date' => 'Ngày bắt đầu',
                        'end_date' => 'Hạn chót',
                        'priority' => 'Độ ưu tiên',
                    ]" />
                </div>
            </div>
        </div>
    </div>
   
    @if ($viewMode === 'table')
        <x-project.table :projects="$this->projects" :sort-by="$sortBy" :sort-dir="$sortDir" />
    @else
        <x-project.gantt :projects="$this->projects" />
    @endif
    <livewire-project.form />
    <x-ui.modal wire:model="showDeleteModal" maxWidth="md">
        <x-slot name="header">
            <x-ui.form.heading icon="warning" title="Xác nhận xóa dự án"
                description="Hành động này sẽ đưa dự án vào trạng thái đã xóa mềm." />
        </x-slot>

        <div class="space-y-3">
            <p class="text-sm text-slate-600 dark:text-slate-300">
                Bạn có chắc chắn muốn xóa dự án này không? Dữ liệu sẽ không còn hiển thị trong danh sách đang hoạt động.
            </p>
            @if ($pendingDeleteProjectName !== '')
                <p class="text-sm font-semibold text-slate-600 dark:text-slate-100">
                    Dự án: {{ $pendingDeleteProjectName }}
                </p>
            @endif
        </div>

        <x-slot name="footer">
            <x-ui.button variant="secondary" wire:click="closeDeleteModal">
                Hủy
            </x-ui.button>
            <x-ui.button variant="danger" icon="delete" wire:click="deleteProject" loading="deleteProject">
                Xóa dự án
            </x-ui.button>
        </x-slot>
    </x-ui.modal>

    <x-ui.modal wire:model="showDateRangeModal" maxWidth="lg">
        <x-slot name="header">
            <h3 class="text-lg font-semibold text-slate-800 dark:text-white">Xuất báo cáo</h3>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                Chọn khoảng thời gian để xuất báo cáo tổng quan dự án.
            </p>
        </x-slot>

        <div class="flex flex-row space-x-3">
            <x-ui.datepicker wire:model.live="filterExportStartDate" label="Từ ngày" />
            <span class="self-center text-slate-400 dark:text-slate-500 mt-4">→</span>
            <x-ui.datepicker wire:model.live="filterExportEndDate" label="Đến ngày" />
        </div>
        @error('filterExportStartDate') <p class="mt-2 text-xs text-red-500">{{ $message }}</p> @enderror
        @error('filterExportEndDate') <p class="mt-2 text-xs text-red-500">{{ $message }}</p> @enderror

        <x-slot name="footer">
            <x-ui.button variant="ghost" wire:click="$set('showDateRangeModal', false)">
                Hủy
            </x-ui.button>
            <x-ui.button icon="file_download" wire:click="exportOverallReport" loading="exportOverallReport">
                Xuất Excel
            </x-ui.button>
        </x-slot>
    </x-ui.modal>

    <x-ui.modal wire:model="showProjectSelectModal" maxWidth="lg">
        <x-slot name="header">
            <h3 class="text-lg font-semibold text-slate-800 dark:text-white">Chọn dự án</h3>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                Chọn dự án để xuất báo cáo chi tiết.
            </p>
        </x-slot>

        <div class="space-y-3">
            <x-ui.input wire:model.live="exportProjectSearch" icon="search" placeholder="Tìm dự án..." />
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mt-2">Danh sách dự án</label>
            <div class="max-h-82 space-y-1 overflow-y-auto rounded-lg border border-slate-200 p-1 dark:border-slate-700">
                @forelse ($this->projectsForExport as $project)
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
                    <label class="flex cursor-pointer items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition-colors hover:bg-slate-50 dark:hover:bg-slate-700 {{ $selectedExportProjectId === $project->id ? 'bg-primary/5 text-primary' : 'text-slate-700 dark:text-slate-300' }}">
                        <input type="radio" wire:model="selectedExportProjectId" value="{{ $project->id }}"
                            class="h-4 w-4 border-slate-300 text-primary focus:ring-primary/20">
                        <div class="flex items-center gap-3">
                            <div class="{{ $avatarColorClass }} flex size-10 items-center justify-center rounded-full text-lg font-bold">
                                {{ strtoupper(substr($project->name, 0, 1)) }}
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-slate-600 dark:text-slate-100">{{ $project->name }}</p>
                                <p class="text-xs text-slate-500">Loại: {{ $project->projectType ? $project->projectType->label : ($project->type instanceof \BackedEnum ? $project->type->label() : ($project->type ?? '—')) }}</p>
                            </div>
                        </div>
                    </label>
                @empty
                    <p class="px-3 py-6 text-center text-sm text-slate-400">Không tìm thấy dự án.</p>
                @endforelse
            </div>
            @error('selectedExportProjectId') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
        </div>

        <x-slot name="footer">
            <x-ui.button variant="ghost" wire:click="$set('showProjectSelectModal', false)">
                Hủy
            </x-ui.button>
            <x-ui.button icon="file_download" wire:click="exportProjectDetail" loading="exportProjectDetail">
                Xuất Excel
            </x-ui.button>
        </x-slot>
    </x-ui.modal>
</div>
