<?php

use App\Models\Task;
use App\Services\Tasks\TaskQueryService;
use App\Services\Tasks\TaskService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Công việc')] class extends Component {
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public ?string $filterSearch = null;

    #[Url(as: 'status', except: '')]
    public ?string $filterStatus = null;

    #[Url(as: 'priority', except: '')]
    public ?string $filterPriority = null;

    #[Url(as: 'project', except: '')]
    public ?string $filterProjectId = null;

    #[Url(as: 'phase', except: '')]
    public ?string $filterPhaseId = null;

    #[Url(as: 'pic', except: '')]
    public ?string $filterPicId = null;

    #[Url(as: 'my', except: false)]
    public bool $filterMyTasks = false;

    #[Url(as: 'sort', except: 'deadline')]
    public string $sortBy = 'deadline';

    #[Url(as: 'dir', except: 'asc')]
    public string $sortDir = 'asc';

    public string $viewMode = 'table';

    public Collection $projectOptions;

    public Collection $phaseOptions;

    public Collection $picOptions;

    public function mount(): void
    {
        $this->loadFilterOptions();
    }

    public function updatedFilterSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatedFilterPriority(): void
    {
        $this->resetPage();
    }

    public function updatedFilterProjectId(): void
    {
        $this->filterPhaseId = null;
        $this->resetPage();
        $this->loadFilterOptions();
    }

    public function updatedFilterPhaseId(): void
    {
        $this->resetPage();
    }

    public function updatedFilterPicId(): void
    {
        $this->resetPage();
    }

    public function updatedFilterMyTasks(): void
    {
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

    #[On('task-saved')]
    #[On('task-updated')]
    public function refreshTasks(): void
    {
        $this->resetPage();
    }

    public function openEditTask(int $taskId): void
    {
        $task = Task::query()->findOrFail($taskId);

        Gate::forUser(auth()->user())->authorize('view', $task);

        $this->dispatch('task-edit-requested', taskId: $task->id);
    }

    public function deleteTask(int $taskId)
    {
        try {
            $task = Task::query()->findOrFail($taskId);

            app(TaskService::class)->delete(auth()->user(), $task);

            $this->dispatch('toast', message: 'Công việc đã được xóa!', type: 'success');
        } catch (\Exception $e) {
            $this->dispatch('toast', message: 'Lỗi khi xóa: ' . $e->getMessage(), type: 'error');
        }
    }

    #[Computed]
    public function tasks(): LengthAwarePaginator
    {
        return app(TaskQueryService::class)->paginateForIndex(
            auth()->user(),
            [
                'search' => $this->filterSearch,
                'status' => $this->filterStatus,
                'priority' => $this->filterPriority,
                'project_id' => $this->filterProjectId,
                'phase_id' => $this->filterPhaseId,
                'pic_id' => $this->filterPicId,
                'my_tasks' => $this->filterMyTasks,
            ],
            12,
            $this->sortBy,
            $this->sortDir,
        );
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function projectFilterOptions(): array
    {
        return $this->projectOptions->mapWithKeys(fn($project): array => [(string) $project->id => $project->name])->all();
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function phaseFilterOptions(): array
    {
        return $this->phaseOptions->mapWithKeys(fn($phase): array => [(string) $phase->id => $phase->name])->all();
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function picFilterOptions(): array
    {
        return $this->picOptions->mapWithKeys(fn($user): array => [(string) $user->id => $user->name])->all();
    }

    private function loadFilterOptions(): void
    {
        $options = app(TaskQueryService::class)->formOptions(auth()->user(), $this->filterProjectId ? (int) $this->filterProjectId : null);

        $this->projectOptions = $options['projects'];
        $this->phaseOptions = $options['phases'];
        $this->picOptions = $options['pics'];
    }
};
?>

<div class="flex flex-col gap-4">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <x-ui.heading title="Danh sách công việc" description="Theo dõi và quản lý tiến độ công việc trên toàn hệ thống."
            class="mb-0" />
        <div class="flex items-center gap-3">
            <div
                class="flex items-center gap-1 rounded-lg border border-slate-200 bg-slate-100 p-1 dark:border-slate-700 dark:bg-slate-800">
                <x-ui.button size="sm" :variant="$viewMode === 'table' ? 'primary' : 'ghost'" wire:click="switchView('table')">Bảng</x-ui.button>
                <x-ui.button size="sm" :variant="$viewMode === 'gantt' ? 'primary' : 'ghost'" wire:click="switchView('gantt')">Gantt</x-ui.button>
            </div>
            @can('create', App\Models\Task::class)
                <x-ui.button icon="add" size="sm" @click="$dispatch('task-create-requested')">
                    Thêm công việc
                </x-ui.button>
            @endcan
        </div>
    </div>

    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div class="w-full md:w-auto">
            <x-ui.filter-search model="filterSearch" placeholder="Tìm công việc..." width="w-full md:w-60" />
        </div>

        <div class="flex w-full items-center gap-2 overflow-x-auto pb-2 md:w-auto md:overflow-visible md:pb-0">
            <div class="flex shrink-0 items-center gap-2 pl-2">
                <label class="relative inline-flex cursor-pointer items-center pr-2">
                    <input type="checkbox" wire:model.live="filterMyTasks" class="peer sr-only">
                    <div
                        class="peer-checked:bg-primary peer-focus:ring-primary/20 peer h-5 w-9 rounded-full bg-slate-200 after:absolute after:left-[2px] after:top-[2px] after:h-4 after:w-4 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:after:translate-x-full peer-checked:after:border-white peer-focus:outline-none peer-focus:ring-2 dark:bg-slate-700">
                    </div>
                    <span class="ml-2 text-xs font-semibold text-slate-700 dark:text-slate-300">Việc của tôi</span>
                </label>
            </div>
            <div class="shrink-0">
                <x-ui.filter-select model="filterProjectId" :value="$filterProjectId" label="Dự án" icon="folder"
                    all-label="Tất cả dự án" width="w-44" drop-width="w-64" :options="$this->projectFilterOptions" />
            </div>

            <div class="shrink-0">
                <x-ui.filter-select model="filterPhaseId" :value="$filterPhaseId" label="Giai đoạn" icon="timeline"
                    all-label="Tất cả giai đoạn" width="w-44" drop-width="w-64" :options="$this->phaseFilterOptions" />
            </div>

            <div class="shrink-0">
                <x-ui.filter-select model="filterPicId" :value="$filterPicId" label="PIC" icon="person"
                    all-label="Tất cả PIC" width="w-44" drop-width="w-64" :options="$this->picFilterOptions" />
            </div>

            <div class="shrink-0">
                <x-ui.filter-select model="filterStatus" :value="$filterStatus" label="Trạng thái" icon="circle"
                    all-label="Tất cả trạng thái" width="w-44" drop-width="w-56" :options="App\Enums\TaskStatus::optionsWithColors()" />
            </div>

            <div class="shrink-0">
                <x-ui.filter-select model="filterPriority" :value="$filterPriority" label="Ưu tiên" icon="flag"
                    all-label="Tất cả ưu tiên" width="w-40" drop-width="w-48" :options="App\Enums\TaskPriority::options()" />
            </div>

            <div class="shrink-0">
                <x-ui.filter-sort :sort-by="$sortBy" :sort-dir="$sortDir" :options="[
                    'deadline' => 'Hạn chót',
                    'priority' => 'Độ ưu tiên',
                    'status' => 'Trạng thái',
                    'name' => 'Tên công việc',
                ]" />
            </div>


        </div>
    </div>

    @if ($viewMode === 'table')
        <x-task.table :tasks="$this->tasks" :sort-by="$sortBy" :sort-dir="$sortDir" />
    @else
        <x-task.gantt :tasks="$this->tasks" />
    @endif

    <livewire:task.form />
</div>
