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

new #[Title('Công việc')] class extends Component
{
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

    #[Url(as: 'sort', except: 'deadline')]
    public string $sortBy = 'deadline';

    #[Url(as: 'dir', except: 'asc')]
    public string $sortDir = 'asc';

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

    #[On('task-saved')]
    #[On('task-updated')]
    public function refreshTasks(): void
    {
        $this->resetPage();
    }

    public function openEditTask(int $taskId): void
    {
        $task = Task::query()->findOrFail($taskId);

        Gate::forUser(auth()->user())->authorize('update', $task);

        $this->dispatch('task-edit-requested', taskId: $task->id);
    }

    public function deleteTask(int $taskId): void
    {
        try {
            $task = Task::query()->findOrFail($taskId);

            app(TaskService::class)->delete(auth()->user(), $task);

            $this->resetPage();
            $this->dispatch('toast', message: 'Công việc đã được xóa!', type: 'success');
        } catch (\Exception $e) {
            $this->dispatch('toast', message: 'Lỗi khi xóa: '.$e->getMessage(), type: 'error');
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
        return $this->projectOptions
            ->mapWithKeys(fn ($project): array => [(string) $project->id => $project->name])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function phaseFilterOptions(): array
    {
        return $this->phaseOptions
            ->mapWithKeys(fn ($phase): array => [(string) $phase->id => $phase->name])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function picFilterOptions(): array
    {
        return $this->picOptions
            ->mapWithKeys(fn ($user): array => [(string) $user->id => $user->name])
            ->all();
    }

    private function loadFilterOptions(): void
    {
        $options = app(TaskQueryService::class)->formOptions(
            auth()->user(),
            $this->filterProjectId ? (int) $this->filterProjectId : null,
        );

        $this->projectOptions = $options['projects'];
        $this->phaseOptions = $options['phases'];
        $this->picOptions = $options['pics'];
    }
};
?>

<div class="flex flex-col gap-4">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <x-ui.heading title="Danh sách công việc"
            description="Theo dõi và quản lý tiến độ công việc trên toàn hệ thống." class="mb-0" />
        @can('create', App\Models\Task::class)
            <x-ui.button icon="add" size="md" @click="$dispatch('task-create-requested')">
                Thêm công việc
            </x-ui.button>
        @endcan
    </div>

    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div class="w-full md:w-auto">
            <x-ui.filter-search model="filterSearch" placeholder="Tìm công việc..." width="w-full md:w-60" />
        </div>

        <div class="flex w-full items-center gap-2 overflow-x-auto pb-2 md:w-auto md:overflow-visible md:pb-0">
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

    <x-task.table :tasks="$this->tasks" :sort-by="$sortBy" :sort-dir="$sortDir" />

    <livewire:task.form />
</div>
