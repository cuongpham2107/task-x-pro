<?php

use App\Models\Project;
use App\Models\Task;
use App\Services\ActivityLogs\ActivityLogService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Nhật ký hoạt động')] class extends Component {
    use WithPagination;

    protected ActivityLogService $activityLogService;

    #[Url(as: 'q', except: '')]
    public ?string $filterSearch = null;

    #[Url(as: 'action', except: '')]
    public ?string $filterAction = null;

    #[Url(as: 'user', except: '')]
    public ?string $filterUserId = null;

    #[Url(as: 'entity', except: '')]
    public ?string $filterEntityType = null;

    #[Url(as: 'sort', except: 'created_at')]
    public string $sortBy = 'created_at';

    #[Url(as: 'dir', except: 'desc')]
    public string $sortDir = 'desc';

    /** @var array<string, string> */
    public array $actionOptions = [];

    /** @var array<string, string> */
    public array $entityTypeOptions = [];

    /** @var Collection<int, \App\Models\User> */
    public Collection $userOptions;

    public function boot(ActivityLogService $activityLogService): void
    {
        $this->activityLogService = $activityLogService;
    }

    public function mount(): void
    {
        $this->loadFilterOptions();
    }

    public function updatedFilterSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterAction(): void
    {
        $this->resetPage();
    }

    public function updatedFilterUserId(): void
    {
        $this->resetPage();
    }

    public function updatedFilterEntityType(): void
    {
        $this->resetPage();
    }

    public function setSort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDir = 'desc';
        }

        $this->resetPage();
    }

    /**
     * Nap bo loc action/entity/user cho giao dien.
     */
    private function loadFilterOptions(): void
    {
        $options = $this->activityLogService->filterOptions(auth()->user());

        $this->actionOptions = $options['actions'];
        $this->entityTypeOptions = $options['entity_types'];
        $this->userOptions = $options['users'];
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function userFilterOptions(): array
    {
        return $this->userOptions
            ->mapWithKeys(
                fn($user): array => [
                    (string) $user->id => $user->name . ' (' . $user->email . ')',
                ],
            )
            ->all();
    }

    /**
     * Lay ten doi tuong duoc log.
     */
    public function resolveEntityName(mixed $entity): ?string
    {
        if ($entity instanceof Task) {
            return $entity->name;
        }

        if ($entity instanceof Project) {
            return $entity->name;
        }

        return null;
    }

    /**
     * Chuyen action code sang nhan hien thi.
     */
    public function resolveActionLabel(?string $action): string
    {
        if ($action === null || $action === '') {
            return '--';
        }

        return $this->actionOptions[$action] ?? str($action)->replace('_', ' ')->headline()->value();
    }

    /**
     * Chuyen entity type sang nhan hien thi.
     */
    public function resolveEntityTypeLabel(?string $entityType): string
    {
        if ($entityType === null || $entityType === '') {
            return 'Khác';
        }

        return match ($entityType) {
            Task::class => 'Task',
            Project::class => 'Project',
            default => class_basename($entityType),
        };
    }

    #[Computed]
    public function activityLogs()
    {
        return $this->activityLogService->paginateForIndex(
            auth()->user(),
            [
                'search' => $this->filterSearch,
                'action' => $this->filterAction,
                'user_id' => $this->filterUserId,
                'entity_type' => $this->filterEntityType,
                'sort' => $this->sortBy,
                'dir' => $this->sortDir,
            ],
            15,
        );
    }
};
?>

<div class="flex flex-col gap-4">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <x-ui.heading title="Nhật ký hoạt động"
            description="Theo dõi lịch sử thao tác trong hệ thống để kiểm tra thay đổi và truy vết sự cố."
            class="mb-0" />
    </div>


    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <x-ui.filter-search model="filterSearch" placeholder="Tìm theo hành động, user, đối tượng..." width="w-full md:w-80" />
        <div class="flex items-center gap-2 overflow-x-auto pb-2 md:overflow-visible md:pb-0">
            <div class="shrink-0">
                <x-ui.filter-select model="filterAction" :value="$filterAction" label="Hành động" icon="bolt"
                    all-label="Tất cả hành động" width="w-44" drop-width="w-60" :options="$actionOptions" />
            </div>

            <div class="shrink-0">
                <x-ui.filter-select model="filterEntityType" :value="$filterEntityType" label="Đối tượng" icon="category"
                    all-label="Tất cả đối tượng" width="w-44" drop-width="w-56" :options="$entityTypeOptions" />
            </div>

            <div class="shrink-0">
                <x-ui.filter-select model="filterUserId" :value="$filterUserId" label="Người thao tác" icon="person"
                    all-label="Tất cả người dùng" width="w-48" drop-width="w-72" :options="$this->userFilterOptions" />
            </div>

            <div class="shrink-0">
                <x-ui.filter-sort :sort-by="$sortBy" :sort-dir="$sortDir" :options="[
                    'created_at' => 'Thời gian',
                    'action' => 'Hành động',
                    'entity_type' => 'Đối tượng',
                ]" />
            </div>
        </div>
    </div>

    <x-ui.table :paginator="$this->activityLogs" paginator-label="log">
        <x-ui.table.head>
            <x-ui.table.column width="min-w-36">Thời gian</x-ui.table.column>
            <x-ui.table.column width="min-w-56">Người thao tác</x-ui.table.column>
            <x-ui.table.column width="min-w-44">Hành động</x-ui.table.column>
            <x-ui.table.column width="min-w-48">Đối tượng</x-ui.table.column>
            <x-ui.table.column width="min-w-80">Chi tiết thay đổi</x-ui.table.column>
            <x-ui.table.column width="min-w-32">IP</x-ui.table.column>
        </x-ui.table.head>

        <x-ui.table.body>
            @forelse ($this->activityLogs as $log)
                @php
                    $actionLabel = $this->resolveActionLabel($log->action);
                    $entityTypeLabel = $this->resolveEntityTypeLabel($log->entity_type);
                    $entityName = $this->resolveEntityName($log->entity);

                    $oldValues = is_array($log->old_values) ? $log->old_values : [];
                    $newValues = is_array($log->new_values) ? $log->new_values : [];
                    $oldValuesText =
                        $oldValues !== []
                            ? json_encode($oldValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                            : null;
                    $newValuesText =
                        $newValues !== []
                            ? json_encode($newValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                            : null;

                    $badgeColor = match ($log->action) {
                        'status_updated', 'status_changed' => 'amber',
                        'progress_updated' => 'blue',
                        'approved' => 'green',
                        default => 'slate',
                    };
                @endphp
                <x-ui.table.row wire:key="activity-log-{{ $log->id }}">
                    <x-ui.table.cell>
                        <div class="text-sm font-semibold text-slate-900 dark:text-white">
                            {{ $log->created_at?->format('d/m/Y H:i') }}</div>
                        <div class="text-[11px] text-slate-500">{{ $log->created_at?->diffForHumans() }}</div>
                    </x-ui.table.cell>

                    <x-ui.table.cell>
                        @if ($log->user)
                            <div class="flex items-center gap-2">
                                <img src="{{ $log->user->avatar_url }}" alt="{{ $log->user->name }}"
                                    class="h-8 w-8 rounded-full object-cover" />
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-slate-900 dark:text-white">
                                        {{ $log->user->name }}</p>
                                    <p class="truncate text-xs text-slate-500">{{ $log->user->email }}</p>
                                </div>
                            </div>
                        @else
                            <span class="text-sm text-slate-500">Hệ thống</span>
                        @endif
                    </x-ui.table.cell>

                    <x-ui.table.cell>
                        <x-ui.badge :color="$badgeColor" size="xs">
                            {{ $actionLabel }}
                        </x-ui.badge>
                    </x-ui.table.cell>

                    <x-ui.table.cell>
                        <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $entityTypeLabel }}
                            #{{ $log->entity_id }}</p>
                        @if ($entityName)
                            <p class="mt-1 truncate text-xs text-slate-500">{{ $entityName }}</p>
                        @endif
                    </x-ui.table.cell>

                    <x-ui.table.cell>
                        <div class="space-y-1 text-xs">
                            @if ($oldValuesText)
                                <p class="text-slate-500">
                                    <span class="font-semibold">Cũ:</span>
                                    <span class="break-all">{{ $oldValuesText }}</span>
                                </p>
                            @endif
                            @if ($newValuesText)
                                <p class="text-slate-700 dark:text-slate-300">
                                    <span class="font-semibold">Mới:</span>
                                    <span class="break-all">{{ $newValuesText }}</span>
                                </p>
                            @endif
                            @if (!$oldValuesText && !$newValuesText)
                                <span class="text-slate-400">Không có dữ liệu thay đổi.</span>
                            @endif
                        </div>
                    </x-ui.table.cell>

                    <x-ui.table.cell>
                        <span class="text-xs text-slate-600 dark:text-slate-300">{{ $log->ip_address ?: '--' }}</span>
                    </x-ui.table.cell>
                </x-ui.table.row>
            @empty
                <x-ui.table.empty colspan="6" icon="history"
                    message="Chưa có activity log phù hợp với bộ lọc hiện tại." />
            @endforelse
        </x-ui.table.body>
    </x-ui.table>
</div>
