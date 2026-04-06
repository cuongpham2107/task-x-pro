<?php

use App\Models\Phase;
use App\Models\Project;
use App\Models\Task;
use App\Services\Tasks\TaskQueryService;
use App\Services\Tasks\TaskService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public Project $project;

    public Phase $phase;

    // ─── Filter & sort (URL-persisted) ───────────────────────────
    #[Url(as: 'status', except: '')]
    public string $filterStatus = '';

    #[Url(as: 'priority', except: '')]
    public string $filterPriority = '';

    #[Url(as: 'sort', except: 'status')]
    public string $sortBy = 'status';

    #[Url(as: 'dir', except: 'asc')]
    public string $sortDir = 'asc';

    #[Url(as: 'task', except: '')]
    public ?int $urlTaskId = null;

    public function mount(Project $project, Phase $phase)
    {
        $this->project = $project;
        $this->phase = $phase;
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatedFilterPriority(): void
    {
        $this->resetPage();
    }

    public function sort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDir = 'asc';
        }
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->filterStatus = '';
        $this->filterPriority = '';
        $this->sortBy = 'status';
        $this->sortDir = 'asc';
        $this->resetPage();
    }

    public function openEditTask(int $taskId): void
    {
        $this->dispatch('task-edit-requested', taskId: $taskId);
    }

    #[On('task-saved')]
    #[On('task-updated')]
    public function onTaskSaved(): void
    {
        unset($this->tasks);
        unset($this->taskStats);
    }

    public function startTask(int $taskId): void
    {
        try {
            $taskService = app(TaskService::class);
            $task = Task::findOrFail($taskId);
            $taskService->start(auth()->user(), $task);

            $this->dispatch('toast', message: 'Công việc đã bắt đầu!', type: 'success');
            unset($this->tasks);
            unset($this->taskStats);
        } catch (\Exception $e) {
            $this->dispatch('toast', message: 'Lỗi: ' . $e->getMessage(), type: 'error');
        }
    }

    public function deleteTask(int $taskId)
    {
        try {
            $task = Task::findOrFail($taskId);
            app(TaskService::class)->delete(auth()->user(), $task);

            $this->dispatch('task-deleted', taskTitle: $task->name);
        } catch (\Exception $e) {
            $this->dispatch('toast', message: 'Lỗi khi xóa: ' . $e->getMessage(), type: 'error');
        }
    }

    #[Computed]
    public function tasks()
    {
        return app(TaskQueryService::class)->paginateForIndex(
            auth()->user(),
            [
                'project_id' => $this->project->id,
                'phase_id' => $this->phase->id,
                'status' => $this->filterStatus,
                'priority' => $this->filterPriority,
            ],
            10,
            $this->sortBy,
            $this->sortDir,
        );
    }

    #[Computed]
    public function members()
    {
        $leaderIds = $this->project->leaders->pluck('id');
        $picIds = Task::where('tasks.phase_id', $this->phase->id)->pluck('tasks.pic_id');
        $coPicIds = \DB::table('task_co_pics')
            ->whereIn('task_id', Task::where('phase_id', $this->phase->id)->pluck('id'))
            ->pluck('user_id');

        $userIds = $leaderIds->concat($picIds)->concat($coPicIds)->unique()->filter();

        return \App\Models\User::whereIn('id', $userIds)->get();
    }

    #[Computed]
    public function taskStats(): array
    {
        $tasks = app(TaskQueryService::class)
            ->taskScopeForActor(auth()->user(), $this->project->id)
            ->where('phase_id', $this->phase->id)
            ->get();

        return [
            'total' => $tasks->count(),
            'done' => $tasks->where('status', \App\Enums\TaskStatus::Completed)->count(),
            'in_progress' => $tasks->where('status', \App\Enums\TaskStatus::InProgress)->count(),
        ];
    }
};
?>

<div class="flex flex-col gap-2" x-data="{ activeTab: 'kanban' }"
    @task-deleted.window="$dispatch('toast', { message: 'Đã xóa \'' + $event.detail.taskTitle + '\'', type: 'error' })">
    {{-- Trigger task edit modal if taskId is present in URL --}}
    <div x-data="{ taskId: @js($urlTaskId) }" x-init="if (taskId) $dispatch('task-edit-requested', { taskId: taskId })" class="hidden"></div>

    <!-- Breadcrumbs and Action Header -->
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="flex flex-col gap-1.5">
            <x-ui.breadcrumbs :items="[
                ['label' => 'Dự án', 'url' => route('projects.index'), 'icon' => 'folder'],
                ['label' => $project->name, 'url' => route('projects.phases.index', $project)],
                ['label' => $phase->name, 'url' => route('projects.phases.tasks.index', [$project, $phase])],
                ['label' => 'Công việc'],
            ]" />

            <x-ui.heading title="{{ $phase->name }}"
                description="Giai đoạn: {{ $phase->name }} | Trình trạng: {{ $phase->progress }}% | {{ $project->name }}"
                class="mb-0" />
        </div>
        <div class="flex items-center gap-3">
            <div class="mr-4">
                <x-ui.avatar-stack :users="$this->members" :max="5" :size="10" placement="bottom" />
            </div>
            @can('create', [App\Models\Task::class, $phase])
                <x-ui.button icon="add" size="sm" @click="$dispatch('task-create-requested')">
                    Thêm công việc
                </x-ui.button>
            @endcan
        </div>
    </div>

    <!-- Tabs Navigation -->
    <div class="flex gap-8 border-b border-slate-200 dark:border-slate-800">
        <button @click="activeTab = 'list'"
            :class="activeTab === 'list' ? 'border-primary text-primary' :
                'border-transparent text-slate-500 dark:text-slate-400 hover:text-primary'"
            class="flex items-center gap-2 border-b-2 py-3 text-sm font-semibold transition-colors">
            <span class="material-symbols-outlined text-xl">list_alt</span>
            <span>Danh sách</span>
        </button>
        <button @click="activeTab = 'kanban'"
            :class="activeTab === 'kanban' ? 'border-primary text-primary' :
                'border-transparent text-slate-500 dark:text-slate-400 hover:text-primary'"
            class="flex items-center gap-2 border-b-2 py-3 text-sm font-semibold transition-colors">
            <span class="material-symbols-outlined text-xl">view_kanban</span>
            <span>Bảng (Kanban)</span>
        </button>
    </div>

    {{-- LIST VIEW --}}
    <div x-show="activeTab === 'list'">
        <x-task.table-view :tasks="$this->tasks" :task-stats="$this->taskStats" :project="$project" :filter-status="$filterStatus" :filter-priority="$filterPriority"
            :sort-by="$sortBy" :sort-dir="$sortDir" />
    </div>

    {{-- KANBAN VIEW --}}
    <div x-show="activeTab === 'kanban'">
        <livewire:task.kanban-view :project-id="$project->id" :phase-id="$phase->id" :key="'kanban-' . $phase->id" />
    </div>

    {{-- Form Modal --}}
    <livewire:task.form :project="$project" :phase="$phase" wire:key="global-task-form-{{ $phase->id }}" />
</div>
