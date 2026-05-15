<?php

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Services\Projects\ProjectService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Dự án')] class extends Component
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
};
?>

<div>
    <div class="mb-4 flex items-center justify-between gap-4">
        <x-ui.heading title="Danh sách dự án" description="Quản lý và theo dõi tiến độ các dự án đang triển khai"
            class="mb-0" />
        <div class="flex items-center gap-3">
            @if (auth()->user()?->can('create', App\Models\Project::class))
                <x-ui.button icon="add" size="sm" wire:click="$dispatch('project-create-requested')">
                    Tạo dự án
                </x-ui.button>
            @endif

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
    <!-- Project Table -->
    <x-project.table :projects="$this->projects" :sort-by="$sortBy" :sort-dir="$sortDir" />
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
</div>
