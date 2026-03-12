<?php

use App\Enums\SlaProjectType;
use App\Enums\SlaTaskType;
use App\Models\SlaConfig;
use App\Services\SlaConfigs\SlaConfigService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Carbon\Carbon;

new #[Title('Cấu hình SLA')] class extends Component {
    use WithPagination;

    protected SlaConfigService $slaConfigService;

    #[Url(as: 'q', except: '')]
    public ?string $filterSearch = null;

    #[Url(as: 'dept', except: '')]
    public ?string $filterDepartmentId = null;

    #[Url(as: 'task', except: '')]
    public ?string $filterTaskType = null;

    #[Url(as: 'project', except: '')]
    public ?string $filterProjectType = null;

    #[Url(as: 'state', except: '')]
    public ?string $filterState = null;

    #[Url(as: 'sort', except: 'effective_date')]
    public string $sortBy = 'effective_date';

    #[Url(as: 'dir', except: 'desc')]
    public string $sortDir = 'desc';

    public bool $showFormModal = false;

    public bool $showDeleteModal = false;

    public string $mode = 'create';

    public ?int $editingSlaConfigId = null;

    public ?int $pendingDeleteSlaConfigId = null;

    public string $pendingDeleteSlaConfigLabel = '';

    public ?string $departmentId = null;

    public string $taskType = SlaTaskType::All->value;

    public string $projectType = SlaProjectType::All->value;

    public string $standardHours = '24.00';

    public string $effectiveDate = '';

    public ?string $expiredDate = null;

    public string $note = '';

    /** @var array<string, string> */
    public array $taskTypeLabels = [];

    /** @var array<string, string> */
    public array $projectTypeLabels = [];

    /** @var Collection<int, \App\Models\Department> */
    public Collection $departmentOptions;

    public function boot(SlaConfigService $slaConfigService): void
    {
        $this->slaConfigService = $slaConfigService;
    }

    public function mount(): void
    {
        $options = $this->slaConfigService->formOptions(auth()->user());
        $this->departmentOptions = $options['departments'];
        $this->taskTypeLabels = $options['task_type_labels'];
        $this->projectTypeLabels = $options['project_type_labels'];
        $this->effectiveDate = now()->toDateString();
    }

    public function updatedFilterSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterDepartmentId(): void
    {
        $this->resetPage();
    }

    public function updatedFilterTaskType(): void
    {
        $this->resetPage();
    }

    public function updatedFilterProjectType(): void
    {
        $this->resetPage();
    }

    public function updatedFilterState(): void
    {
        $this->resetPage();
    }

    public function updatedDepartmentId(mixed $value): void
    {
        if ($value === '') {
            $this->departmentId = null;
        }
    }

    public function updatedExpiredDate(mixed $value): void
    {
        if ($value === '') {
            $this->expiredDate = null;
        }
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
     * @return array<string, array<int|string, \Illuminate\Contracts\Validation\ValidationRule|string>>
     */
    protected function rules(): array
    {
        return [
            'departmentId' => ['nullable', 'exists:departments,id'],
            'taskType' => ['required', Rule::in(SlaTaskType::values())],
            'projectType' => ['required', Rule::in(SlaProjectType::values())],
            'standardHours' => ['required', 'numeric', 'min:0.25', 'max:999.99'],
            'effectiveDate' => ['required', 'date'],
            'expiredDate' => ['nullable', 'date', 'after_or_equal:effectiveDate'],
            'note' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'departmentId.exists' => 'Phòng ban đã chọn không tồn tại.',
            'taskType.required' => 'Loại công việc là bắt buộc.',
            'taskType.in' => 'Loại công việc không hợp lệ.',
            'projectType.required' => 'Loại dự án là bắt buộc.',
            'projectType.in' => 'Loại dự án không hợp lệ.',
            'standardHours.required' => 'Giờ SLA là bắt buộc.',
            'standardHours.numeric' => 'Giờ SLA phải là số.',
            'standardHours.min' => 'Giờ SLA tối thiểu là 0.25.',
            'standardHours.max' => 'Giờ SLA không được vượt quá 999.99.',
            'effectiveDate.required' => 'Ngày bắt đầu hiệu lực là bắt buộc.',
            'effectiveDate.date' => 'Ngày bắt đầu hiệu lực không hợp lệ.',
            'expiredDate.date' => 'Ngày kết thúc hiệu lực không hợp lệ.',
            'expiredDate.after_or_equal' => 'Ngày kết thúc hiệu lực phải lớn hơn hoặc bằng ngày bắt đầu.',
            'note.max' => 'Ghi chú không được vượt quá 5000 ký tự.',
        ];
    }

    public function openCreateFormModal(): void
    {
        Gate::forUser(auth()->user())->authorize('create', SlaConfig::class);

        $this->resetFormModal();
        $this->mode = 'create';
        $this->showFormModal = true;
    }

    public function openEditFormModal(int $slaConfigId): void
    {
        $this->resetFormModal();

        $actor = auth()->user();
        if ($actor === null) {
            return;
        }

        $slaConfig = $this->slaConfigService->findForEdit($actor, $slaConfigId);
        Gate::forUser($actor)->authorize('update', $slaConfig);

        $this->mode = 'edit';
        $this->editingSlaConfigId = $slaConfig->id;
        $this->departmentId = $slaConfig->department_id !== null ? (string) $slaConfig->department_id : null;
        $this->taskType = $slaConfig->task_type instanceof \BackedEnum ? (string) $slaConfig->task_type->value : (string) $slaConfig->task_type;
        $this->projectType = $slaConfig->project_type instanceof \BackedEnum ? (string) $slaConfig->project_type->value : (string) $slaConfig->project_type;
        $this->standardHours = number_format((float) $slaConfig->standard_hours, 2, '.', '');
        $this->effectiveDate = $slaConfig->effective_date instanceof Carbon ? $slaConfig->effective_date->toDateString() : $slaConfig->effective_date ?? now()->toDateString();
        $this->expiredDate = $slaConfig->expired_date instanceof Carbon ? $slaConfig->expired_date->toDateString() : $slaConfig->expired_date ?? null;
        $this->note = (string) ($slaConfig->note ?? '');
        $this->showFormModal = true;
    }

    public function closeFormModal(): void
    {
        $this->showFormModal = false;
        $this->resetFormModal();
    }

    public function resetFormModal(): void
    {
        $this->reset(['editingSlaConfigId', 'departmentId', 'expiredDate', 'note']);

        $this->mode = 'create';
        $this->taskType = SlaTaskType::All->value;
        $this->projectType = SlaProjectType::All->value;
        $this->standardHours = '24.00';
        $this->effectiveDate = now()->toDateString();
        $this->resetValidation();
    }

    public function save(): void
    {
        $this->validate();

        $actor = auth()->user();
        if ($actor === null) {
            return;
        }

        $payload = [
            'department_id' => $this->departmentId,
            'task_type' => $this->taskType,
            'project_type' => $this->projectType,
            'standard_hours' => $this->standardHours,
            'effective_date' => $this->effectiveDate,
            'expired_date' => $this->expiredDate,
            'note' => $this->note,
        ];

        try {
            if ($this->mode === 'edit' && $this->editingSlaConfigId !== null) {
                $slaConfig = $this->slaConfigService->findForEdit($actor, $this->editingSlaConfigId);
                $this->slaConfigService->update($actor, $slaConfig, $payload);
                $message = 'Cập nhật cấu hình SLA thành công!';
            } else {
                $this->slaConfigService->create($actor, $payload);
                $message = 'Tạo cấu hình SLA thành công!';
            }

            $this->closeFormModal();
            unset($this->slaConfigs, $this->summaryStats);

            session()->flash('success', $message);
            $this->dispatch('toast', message: $message, type: 'success');
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());
        } catch (\Exception $e) {
            $message = 'Không thể lưu cấu hình SLA: ' . $e->getMessage();
            session()->flash('error', $message);
            $this->dispatch('toast', message: $message, type: 'error');
        }
    }

    public function confirmDeleteSlaConfig(int $slaConfigId): void
    {
        $slaConfig = SlaConfig::query()->with('department:id,name')->findOrFail($slaConfigId);

        Gate::forUser(auth()->user())->authorize('delete', $slaConfig);

        $departmentName = $slaConfig->department?->name ?? 'Toàn công ty';
        $this->pendingDeleteSlaConfigId = $slaConfig->id;
        $this->pendingDeleteSlaConfigLabel = $departmentName . ' / ' . $this->taskTypeLabelFromValue($slaConfig->task_type) . ' / ' . $this->projectTypeLabelFromValue($slaConfig->project_type);
        $this->showDeleteModal = true;
    }

    public function closeDeleteModal(): void
    {
        $this->showDeleteModal = false;
        $this->pendingDeleteSlaConfigId = null;
        $this->pendingDeleteSlaConfigLabel = '';
    }

    public function deleteSlaConfig(): void
    {
        if ($this->pendingDeleteSlaConfigId === null) {
            return;
        }

        $actor = auth()->user();
        if ($actor === null) {
            return;
        }

        try {
            $slaConfig = $this->slaConfigService->findForEdit($actor, $this->pendingDeleteSlaConfigId);
            $this->slaConfigService->delete($actor, $slaConfig);

            $this->closeDeleteModal();
            $this->resetPage();
            unset($this->slaConfigs, $this->summaryStats);

            $message = 'Xóa cấu hình SLA thành công!';
            session()->flash('success', $message);
            $this->dispatch('toast', message: $message, type: 'success');
        } catch (\Exception $e) {
            $message = 'Không thể xóa cấu hình SLA: ' . $e->getMessage();
            session()->flash('error', $message);
            $this->dispatch('toast', message: $message, type: 'error');
        }
    }

    public function resolveState(SlaConfig $slaConfig): string
    {
        $today = now()->startOfDay();
        $effectiveDate = $slaConfig->effective_date !== null ? Carbon::parse($slaConfig->effective_date)->startOfDay() : null;

        if ($effectiveDate !== null && $effectiveDate->gt($today)) {
            return 'upcoming';
        }

        if ($slaConfig->isEffectiveAt($today)) {
            return 'active';
        }

        return 'expired';
    }

    /**
     * @return array{label: string, color: string}
     */
    public function stateMeta(string $state): array
    {
        return match ($state) {
            'active' => ['label' => 'Đang hiệu lực', 'color' => 'green'],
            'upcoming' => ['label' => 'Sắp hiệu lực', 'color' => 'blue'],
            default => ['label' => 'Hết hiệu lực', 'color' => 'red'],
        };
    }

    public function taskTypeLabelFromValue(mixed $value): string
    {
        $normalized = $value instanceof \BackedEnum ? (string) $value->value : (string) $value;

        return $this->taskTypeLabels[$normalized] ?? $normalized;
    }

    public function projectTypeLabelFromValue(mixed $value): string
    {
        $normalized = $value instanceof \BackedEnum ? (string) $value->value : (string) $value;

        return $this->projectTypeLabels[$normalized] ?? $normalized;
    }

    /**
     * @return string
     */
    public function formatDate(?Carbon $date): string
    {
        return $date?->format('d/m/Y') ?? '--';
    }

    #[Computed]
    public function slaConfigs()
    {
        return $this->slaConfigService->paginateForIndex(
            auth()->user(),
            [
                'search' => $this->filterSearch,
                'department_id' => $this->filterDepartmentId,
                'task_type' => $this->filterTaskType,
                'project_type' => $this->filterProjectType,
                'state' => $this->filterState,
                'sort' => $this->sortBy,
                'dir' => $this->sortDir,
            ],
            12,
        );
    }

    /**
     * @return array<string, array{label: string, dot: string}>
     */
    #[Computed]
    public function departmentFilterOptions(): array
    {
        $departmentOptions = $this->departmentOptions
            ->mapWithKeys(function ($department): array {
                $label = trim((string) $department->code) !== '' ? $department->name . ' (' . $department->code . ')' : $department->name;

                return [
                    (string) $department->id => [
                        'label' => $label,
                        'dot' => 'bg-slate-500',
                    ],
                ];
            })
            ->all();

        return ['global' => ['label' => 'Toàn công ty', 'dot' => 'bg-primary']] + $departmentOptions;
    }
};
?>

<div class="flex flex-col gap-6">
    <div class="mb-2 flex flex-wrap items-center justify-between gap-4">
        <x-ui.heading title="Cấu hình SLA"
            description="Quản lý quy tắc SLA theo phòng ban, loại công việc, loại dự án và khoảng thời gian hiệu lực."
            class="mb-0" />

        @can('create', App\Models\SlaConfig::class)
            <x-ui.button icon="add" wire:click="openCreateFormModal">
                Thêm cấu hình SLA
            </x-ui.button>
        @endcan
    </div>


    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <x-ui.filter-search model="filterSearch" placeholder="Tìm theo ghi chú, phòng ban..." width="w-full md:w-72" />
        <div class="flex items-center gap-2 overflow-x-auto pb-2 md:overflow-visible md:pb-0">
            <div class="shrink-0">
                <x-ui.filter-select model="filterDepartmentId" :value="$filterDepartmentId" label="Phòng ban" icon="apartment"
                    all-label="Tất cả phòng ban" width="w-48" drop-width="w-64" :options="$this->departmentFilterOptions" />
            </div>

            <div class="shrink-0">
                <x-ui.filter-select model="filterTaskType" :value="$filterTaskType" label="Loại công việc" icon="task_alt"
                    all-label="Tất cả loại task" width="w-48" drop-width="w-64" :options="$taskTypeLabels" />
            </div>

            <div class="shrink-0">
                <x-ui.filter-select model="filterProjectType" :value="$filterProjectType" label="Loại dự án" icon="style"
                    all-label="Tất cả loại dự án" width="w-48" drop-width="w-64" :options="$projectTypeLabels" />
            </div>

            <div class="shrink-0">
                <x-ui.filter-select model="filterState" :value="$filterState" label="Hiệu lực" icon="schedule"
                    all-label="Tất cả trạng thái" width="w-44" drop-width="w-56" :options="[
                        'active' => ['label' => 'Đang hiệu lực', 'dot' => 'bg-green-500'],
                        'upcoming' => ['label' => 'Sắp hiệu lực', 'dot' => 'bg-blue-500'],
                        'expired' => ['label' => 'Hết hiệu lực', 'dot' => 'bg-red-500'],
                    ]" />
            </div>

            <div class="shrink-0">
                <x-ui.filter-sort :sort-by="$sortBy" :sort-dir="$sortDir" :options="[
                    'effective_date' => 'Ngày hiệu lực',
                    'expired_date' => 'Ngày hết hạn',
                    'standard_hours' => 'Số giờ SLA',
                    'task_type' => 'Loại công việc',
                    'project_type' => 'Loại dự án',
                    'created_at' => 'Ngày tạo',
                ]" />
            </div>
        </div>
    </div>

    <x-ui.table :paginator="$this->slaConfigs" paginator-label="cấu hình sla">
        <x-ui.table.head>
            <x-ui.table.column width="min-w-44">Phòng ban</x-ui.table.column>
            <x-ui.table.sort-column field="task_type" :sort-by="$sortBy" :sort-dir="$sortDir" width="min-w-44">
                Phạm vi SLA
            </x-ui.table.sort-column>
            <x-ui.table.sort-column field="standard_hours" :sort-by="$sortBy" :sort-dir="$sortDir" width="min-w-24">
                Giờ SLA
            </x-ui.table.sort-column>
            <x-ui.table.sort-column field="effective_date" :sort-by="$sortBy" :sort-dir="$sortDir" width="min-w-40">
                Hiệu lực
            </x-ui.table.sort-column>
            <x-ui.table.column width="min-w-44">Người tạo</x-ui.table.column>
            <x-ui.table.column width="min-w-56 text-wrap">Ghi chú</x-ui.table.column>
            <x-ui.table.column width="min-w-20" align="right" :muted="true">Thao tác</x-ui.table.column>
        </x-ui.table.head>

        <x-ui.table.body>
            @forelse ($this->slaConfigs as $slaConfig)
                @php
                    $state = $this->resolveState($slaConfig);
                    $stateMeta = $this->stateMeta($state);
                    $departmentLabel = $slaConfig->department?->name ?? 'Toàn công ty';
                    $departmentSubLabel = $slaConfig->department?->code ?? 'GLOBAL';
                @endphp
                <x-ui.table.row wire:key="sla-config-{{ $slaConfig->id }}">
                    <x-ui.table.cell>
                        <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $departmentLabel }}</p>
                        <p class="text-xs text-slate-500">{{ $departmentSubLabel }}</p>
                    </x-ui.table.cell>

                    <x-ui.table.cell>
                        <div class="flex flex-wrap gap-1">
                            <x-ui.badge color="slate" size="xs">
                                Task: {{ $this->taskTypeLabelFromValue($slaConfig->task_type) }}
                            </x-ui.badge>
                            <x-ui.badge color="slate" size="xs">
                                Project: {{ $this->projectTypeLabelFromValue($slaConfig->project_type) }}
                            </x-ui.badge>
                        </div>
                    </x-ui.table.cell>

                    <x-ui.table.cell>
                        <p class="text-primary text-sm font-semibold">
                            {{ number_format((float) $slaConfig->standard_hours, 2) }}h</p>
                    </x-ui.table.cell>

                    <x-ui.table.cell>
                        <div class="space-y-1">
                            <p class="text-xs text-slate-700 dark:text-slate-300">
                                {{ $this->formatDate($slaConfig->effective_date) }} -
                                {{ $this->formatDate($slaConfig->expired_date) }}
                            </p>
                            <x-ui.badge :color="$stateMeta['color']" size="xs">
                                {{ $stateMeta['label'] }}
                            </x-ui.badge>
                        </div>
                    </x-ui.table.cell>

                    <x-ui.table.cell>
                        <div class="flex items-center gap-2">
                            <img src="{{ $slaConfig->creator?->avatar_url }}" alt="{{ $slaConfig->creator?->name }}"
                                class="h-8 w-8 rounded-full object-cover" />
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-slate-900 dark:text-white">
                                    {{ $slaConfig->creator?->name ?? '--' }}</p>
                                <p class="truncate text-xs text-slate-500">
                                    {{ $slaConfig->created_at?->format('d/m/Y') ?? '--' }}</p>
                            </div>
                        </div>
                    </x-ui.table.cell>

                    <x-ui.table.cell>
                        <p class="line-clamp-2 text-sm text-slate-600 dark:text-slate-300">
                            {{ $slaConfig->note ?: '--' }}</p>
                    </x-ui.table.cell>

                    <x-ui.table.cell align="right" x-on:click.stop>
                        <div class="flex items-center justify-end gap-1">
                            @can('update', $slaConfig)
                                <x-ui.icon-button icon="edit" size="sm" tooltip="Sửa cấu hình SLA"
                                    wire:click="openEditFormModal({{ $slaConfig->id }})" />
                            @endcan
                            @can('delete', $slaConfig)
                                <x-ui.icon-button icon="delete" size="sm" color="red" tooltip="Xóa cấu hình SLA"
                                    wire:click="confirmDeleteSlaConfig({{ $slaConfig->id }})" />
                            @endcan
                        </div>
                    </x-ui.table.cell>
                </x-ui.table.row>
            @empty
                <x-ui.table.empty colspan="7" icon="schedule"
                    message="Chưa có cấu hình SLA phù hợp với bộ lọc hiện tại." />
            @endforelse
        </x-ui.table.body>
    </x-ui.table>

    <x-ui.slide-panel wire:model="showFormModal" maxWidth="3xl">
        <x-slot name="header">
            <x-ui.form.heading :icon="$mode === 'edit' ? 'edit' : 'add_chart'" :title="$mode === 'edit' ? 'Cập nhật cấu hình SLA' : 'Thêm cấu hình SLA'"
                description="Cấu hình SLA theo phòng ban, loại công việc và loại dự án." />
        </x-slot>

        <form id="sla-config-form" wire:submit="save" class="space-y-5">
            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                <x-ui.select label="Phòng ban" name="departmentId" wire:model="departmentId" icon="apartment"
                    placeholder="Toàn công ty (GLOBAL)" :options="$departmentOptions->mapWithKeys(
                        fn($d) => [$d->id => $d->name . ($d->code ? ' (' . $d->code . ')' : '')],
                    )" />

                <x-ui.input label="Giờ SLA (hours)" name="standardHours" type="number" min="0.25"
                    step="0.25" wire:model="standardHours" icon="schedule" required />

                <x-ui.select label="Loại công việc" name="taskType" wire:model="taskType" icon="task_alt" required
                    :options="$taskTypeLabels" />

                <x-ui.select label="Loại dự án" name="projectType" wire:model="projectType" icon="style" required
                    :options="$projectTypeLabels" />

                <x-ui.datepicker label="Ngày bắt đầu hiệu lực" name="effectiveDate" wire:model="effectiveDate"
                    required />

                <x-ui.datepicker label="Ngày kết thúc hiệu lực" name="expiredDate" wire:model="expiredDate" />

                <x-ui.textarea label="Ghi chú" name="note" wire:model="note" icon="description"
                    rows="4" />
            </div>
        </form>

        <x-slot name="footer">
            <x-ui.button variant="secondary" wire:click="closeFormModal">
                Hủy
            </x-ui.button>
            <x-ui.button type="submit" form="sla-config-form" icon="save" loading="save">
                {{ $mode === 'edit' ? 'Cập nhật' : 'Tạo mới' }}
            </x-ui.button>
        </x-slot>
    </x-ui.slide-panel>

    <x-ui.modal wire:model="showDeleteModal" maxWidth="md">
        <x-slot name="header">
            <x-ui.form.heading icon="warning" title="Xác nhận xóa cấu hình SLA"
                description="Bản ghi sau khi xóa sẽ không được sử dụng để snapshot SLA." />
        </x-slot>

        <div class="space-y-3">
            <p class="text-sm text-slate-600 dark:text-slate-300">
                Bạn có chắc chắn muốn xóa cấu hình SLA này không?
            </p>
            @if ($pendingDeleteSlaConfigLabel !== '')
                <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">
                    Cấu hình: {{ $pendingDeleteSlaConfigLabel }}
                </p>
            @endif
        </div>

        <x-slot name="footer">
            <x-ui.button variant="secondary" wire:click="closeDeleteModal">
                Hủy
            </x-ui.button>
            <x-ui.button variant="danger" icon="delete" wire:click="deleteSlaConfig" loading="deleteSlaConfig">
                Xóa cấu hình
            </x-ui.button>
        </x-slot>
    </x-ui.modal>
</div>
