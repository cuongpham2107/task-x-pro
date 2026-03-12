<?php

use App\Enums\ProjectType;
use App\Models\PhaseTemplate;
use App\Services\PhaseTemplates\PhaseTemplateService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Mẫu phase')] class extends Component {
    use WithPagination;

    protected PhaseTemplateService $phaseTemplateService;

    #[Url(as: 'q', except: '')]
    public ?string $filterSearch = null;

    #[Url(as: 'type', except: '')]
    public ?string $filterProjectType = null;

    #[Url(as: 'sort', except: 'order_index')]
    public string $sortBy = 'order_index';

    #[Url(as: 'dir', except: 'asc')]
    public string $sortDir = 'asc';

    public bool $showFormModal = false;

    public string $mode = 'create';

    public ?int $editingTemplateId = null;

    public string $projectType = ProjectType::Warehouse->value;

    public string $phaseName = '';

    public string $phaseDescription = '';

    public int $orderIndex = 1;

    public string $defaultWeight = '10.00';

    public ?string $defaultDurationDays = null;

    public bool $isActive = true;

    public bool $showDeleteModal = false;

    public ?int $pendingDeleteTemplateId = null;

    public string $pendingDeleteTemplateName = '';

    /** @var array<string, string> */
    public array $projectTypeLabels = [];

    public function boot(PhaseTemplateService $phaseTemplateService): void
    {
        $this->phaseTemplateService = $phaseTemplateService;
    }

    public function mount(): void
    {
        $options = $this->phaseTemplateService->formOptions(auth()->user());
        $this->projectTypeLabels = $options['project_type_labels'];
    }

    public function updatedFilterSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterProjectType(): void
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

    /**
     * @return array<string, array<int|string, \Illuminate\Contracts\Validation\ValidationRule|string>>
     */
    protected function rules(): array
    {
        return [
            'projectType' => ['required', Rule::in(ProjectType::values())],
            'phaseName' => ['required', 'string', 'max:255'],
            'phaseDescription' => ['nullable', 'string'],
            'orderIndex' => ['required', 'integer', 'min:1', 'max:999', Rule::unique('phase_templates', 'order_index')->where(fn($query) => $query->where('project_type', $this->projectType))->ignore($this->editingTemplateId)],
            'defaultWeight' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'defaultDurationDays' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'isActive' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'projectType.required' => 'Loai du an la bat buoc.',
            'projectType.in' => 'Loai du an khong hop le.',
            'phaseName.required' => 'Ten phase la bat buoc.',
            'phaseName.max' => 'Ten phase khong duoc vuot qua 255 ky tu.',
            'orderIndex.required' => 'Thu tu phase la bat buoc.',
            'orderIndex.integer' => 'Thu tu phase phai la so nguyen.',
            'orderIndex.unique' => 'Thu tu phase da ton tai trong loai du an nay.',
            'defaultWeight.required' => 'Trong so mac dinh la bat buoc.',
            'defaultWeight.numeric' => 'Trong so mac dinh phai la so.',
            'defaultWeight.min' => 'Trong so mac dinh phai lon hon 0.',
            'defaultWeight.max' => 'Trong so mac dinh khong duoc vuot qua 100.',
            'defaultDurationDays.integer' => 'Thoi gian mac dinh phai la so nguyen.',
            'defaultDurationDays.min' => 'Thoi gian mac dinh toi thieu 1 ngay.',
            'defaultDurationDays.max' => 'Thoi gian mac dinh khong hop le.',
        ];
    }

    public function openCreateFormModal(): void
    {
        Gate::forUser(auth()->user())->authorize('create', PhaseTemplate::class);

        $this->resetFormModal();
        $this->mode = 'create';
        $this->showFormModal = true;
    }

    public function openEditFormModal(int $templateId): void
    {
        $this->resetFormModal();

        $actor = auth()->user();
        if ($actor === null) {
            return;
        }

        $template = $this->phaseTemplateService->findForEdit($actor, $templateId);
        Gate::forUser($actor)->authorize('update', $template);

        $this->mode = 'edit';
        $this->editingTemplateId = $template->id;
        $this->projectType = (string) $template->project_type;
        $this->phaseName = (string) $template->phase_name;
        $this->phaseDescription = (string) ($template->phase_description ?? '');
        $this->orderIndex = (int) $template->order_index;
        $this->defaultWeight = (string) $template->default_weight;
        $this->defaultDurationDays = $template->default_duration_days !== null ? (string) $template->default_duration_days : null;
        $this->isActive = (bool) $template->is_active;
        $this->showFormModal = true;
    }

    public function closeFormModal(): void
    {
        $this->showFormModal = false;
        $this->resetFormModal();
    }

    public function resetFormModal(): void
    {
        $this->reset(['phaseName', 'phaseDescription', 'editingTemplateId', 'defaultDurationDays']);

        $this->mode = 'create';
        $this->projectType = ProjectType::Warehouse->value;
        $this->orderIndex = 1;
        $this->defaultWeight = '10.00';
        $this->isActive = true;
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
            'project_type' => $this->projectType,
            'phase_name' => $this->phaseName,
            'phase_description' => $this->phaseDescription,
            'order_index' => $this->orderIndex,
            'default_weight' => $this->defaultWeight,
            'default_duration_days' => $this->defaultDurationDays,
            'is_active' => $this->isActive,
        ];

        try {
            if ($this->mode === 'edit' && $this->editingTemplateId !== null) {
                $template = $this->phaseTemplateService->findForEdit($actor, $this->editingTemplateId);
                $this->phaseTemplateService->update($actor, $template, $payload);
                $message = 'Cap nhat mau phase thanh cong!';
            } else {
                $this->phaseTemplateService->create($actor, $payload);
                $message = 'Tao mau phase thanh cong!';
            }

            $this->closeFormModal();
            unset($this->templates, $this->summaryStats);

            session()->flash('success', $message);
            $this->dispatch('toast', message: $message, type: 'success');
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());
        } catch (\Exception $e) {
            $message = 'Khong the luu mau phase: ' . $e->getMessage();
            session()->flash('error', $message);
            $this->dispatch('toast', message: $message, type: 'error');
        }
    }

    public function confirmDeleteTemplate(int $templateId): void
    {
        $template = PhaseTemplate::query()->findOrFail($templateId);

        Gate::forUser(auth()->user())->authorize('delete', $template);

        $this->pendingDeleteTemplateId = $template->id;
        $this->pendingDeleteTemplateName = $template->phase_name;
        $this->showDeleteModal = true;
    }

    public function closeDeleteModal(): void
    {
        $this->showDeleteModal = false;
        $this->pendingDeleteTemplateId = null;
        $this->pendingDeleteTemplateName = '';
    }

    public function deleteTemplate(): void
    {
        if ($this->pendingDeleteTemplateId === null) {
            return;
        }

        $actor = auth()->user();
        if ($actor === null) {
            return;
        }

        try {
            $template = $this->phaseTemplateService->findForEdit($actor, $this->pendingDeleteTemplateId);
            $this->phaseTemplateService->delete($actor, $template);

            $this->closeDeleteModal();
            $this->resetPage();
            unset($this->templates, $this->summaryStats);

            $message = 'Xoa mau phase thanh cong!';
            session()->flash('success', $message);
            $this->dispatch('toast', message: $message, type: 'success');
        } catch (\Exception $e) {
            $message = 'Khong the xoa mau phase: ' . $e->getMessage();
            session()->flash('error', $message);
            $this->dispatch('toast', message: $message, type: 'error');
        }
    }

    #[Computed]
    public function templates()
    {
        return $this->phaseTemplateService->paginateForIndex(
            auth()->user(),
            [
                'search' => $this->filterSearch,
                'project_type' => $this->filterProjectType,
                'sort' => $this->sortBy,
                'dir' => $this->sortDir,
            ],
            12,
        );
    }

    /**
     * @return array{
     *     total_templates: int,
     *     active_templates: int,
     *     project_types: int
     * }
     */
    #[Computed]
    public function summaryStats(): array
    {
        return $this->phaseTemplateService->summaryStats(auth()->user());
    }
};
?>

<div class="flex flex-col gap-6">
    <div class="mb-2 flex flex-wrap items-center justify-between gap-4">
        <x-ui.heading title="Mẫu phase" description="Quản lý phase template và group theo project type." class="mb-0" />

        @can('create', App\Models\PhaseTemplate::class)
            <x-ui.button icon="add" wire:click="openCreateFormModal">
                Thêm mẫu phase
            </x-ui.button>
        @endcan
    </div>

    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <x-ui.filter-search model="filterSearch" placeholder="Tìm tên phase..." width="w-full md:w-72" />
        <div class="flex items-center gap-2 overflow-x-auto pb-2 md:overflow-visible md:pb-0">
            <div class="shrink-0">
                <x-ui.filter-select model="filterProjectType" :value="$filterProjectType" label="Loại dự án" icon="style"
                    all-label="Tất cả loại" width="w-44" drop-width="w-52" :options="$projectTypeLabels" />
            </div>
            <div class="shrink-0">
                <x-ui.filter-sort :sort-by="$sortBy" :sort-dir="$sortDir" :options="[
                    'order_index' => 'Thứ tự',
                    'phase_name' => 'Tên phase',
                    'default_weight' => 'Trọng số',
                    'default_duration_days' => 'Thời gian',
                ]" />
            </div>
        </div>
    </div>

    <x-ui.table :paginator="$this->templates" paginator-label="mau phase">
        <x-ui.table.head>
            <x-ui.table.sort-column field="order_index" :sort-by="$sortBy" :sort-dir="$sortDir" width="min-w-18">Thứ
                tự</x-ui.table.sort-column>
            <x-ui.table.sort-column field="phase_name" :sort-by="$sortBy" :sort-dir="$sortDir" width="min-w-48">Tên
                phase</x-ui.table.sort-column>
            <x-ui.table.column width="min-w-60">Mô tả</x-ui.table.column>
            <x-ui.table.sort-column field="default_weight" :sort-by="$sortBy" :sort-dir="$sortDir" width="min-w-24">Trọng
                số</x-ui.table.sort-column>
            <x-ui.table.sort-column field="default_duration_days" :sort-by="$sortBy" :sort-dir="$sortDir"
                width="min-w-24">Ngày mặc định</x-ui.table.sort-column>
            <x-ui.table.column width="min-w-22">Trạng thái</x-ui.table.column>
            <x-ui.table.column width="min-w-20" align="right" :muted="true">Thao tac</x-ui.table.column>
        </x-ui.table.head>

        <x-ui.table.body>
            @php
                $currentGroup = null;
            @endphp
            @forelse ($this->templates as $template)
                @if ($currentGroup !== $template->project_type)
                    @php
                        $currentGroup = $template->project_type;
                        $groupLabel = $projectTypeLabels[$template->project_type] ?? $template->project_type;
                    @endphp
                    <tr class="border-y border-slate-200 bg-slate-50/80 dark:border-slate-800 dark:bg-slate-800/40">
                        <td colspan="7"
                            class="px-3 py-2 text-xs font-bold uppercase tracking-wide text-slate-600 dark:text-slate-300">
                            Loại dự án: {{ $groupLabel }}
                        </td>
                    </tr>
                @endif

                <x-ui.table.row wire:key="phase-template-{{ $template->id }}">
                    <x-ui.table.cell>
                        <span
                            class="rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-bold tracking-wide text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                            {{ $template->order_index }}
                        </span>
                    </x-ui.table.cell>
                    <x-ui.table.cell>
                        <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $template->phase_name }}</p>
                    </x-ui.table.cell>
                    <x-ui.table.cell>
                        <p class="line-clamp-2 text-sm text-slate-600 dark:text-slate-300">
                            {{ $template->phase_description ?: '--' }}
                        </p>
                    </x-ui.table.cell>
                    <x-ui.table.cell>
                        <span
                            class="text-primary text-sm font-semibold">{{ number_format((float) $template->default_weight, 2) }}%</span>
                    </x-ui.table.cell>
                    <x-ui.table.cell>
                        <span class="text-sm text-slate-700 dark:text-slate-300">
                            {{ $template->default_duration_days !== null ? $template->default_duration_days . ' ngày' : '--' }}
                        </span>
                    </x-ui.table.cell>
                    <x-ui.table.cell>
                        <x-ui.badge :color="$template->is_active ? 'green' : 'slate'" size="xs">
                            {{ $template->is_active ? 'Active' : 'Inactive' }}
                        </x-ui.badge>
                    </x-ui.table.cell>
                    <x-ui.table.cell align="right" x-on:click.stop>
                        <div class="flex items-center justify-end gap-1">
                            @can('update', $template)
                                <x-ui.icon-button icon="edit" size="sm" tooltip="Sua"
                                    wire:click="openEditFormModal({{ $template->id }})" />
                            @endcan
                            @can('delete', $template)
                                <x-ui.icon-button icon="delete" size="sm" color="red" tooltip="Xoa"
                                    wire:click="confirmDeleteTemplate({{ $template->id }})" />
                            @endcan
                        </div>
                    </x-ui.table.cell>
                </x-ui.table.row>
            @empty
                <x-ui.table.empty colspan="7" icon="view_timeline"
                    message="Chưa có phase template phù hợp với bộ lọc hiện tại." />
            @endforelse
        </x-ui.table.body>
    </x-ui.table>

    <x-ui.slide-panel wire:model="showFormModal" maxWidth="3xl">
        <x-slot name="header">
            <x-ui.form.heading :icon="$mode === 'edit' ? 'edit' : 'add'" :title="$mode === 'edit' ? 'Cập nhật mẫu phase' : 'Tạo mẫu phase mới'" :description="$mode === 'edit' ? 'Cập nhật thông tin phase template.' : 'Nhập thông tin mẫu phase cho loại dự án.'" />
        </x-slot>

        <form id="phase-template-form" wire:submit="save" class="space-y-5">
            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                <div>
                    <label class="label-text">Loại dự án <span class="text-red-500">*</span></label>
                    <select wire:model="projectType" class="input-field">
                        @foreach ($projectTypeLabels as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <x-ui.field-error field="projectType" />
                </div>

                <x-ui.input type="number" label="Thứ tự" required min="1" wire:model="orderIndex"
                    name="orderIndex" />
            </div>

            <div class="md:col-span-2">
                <x-ui.input type="text" label="Tên phase" required wire:model="phaseName" name="phaseName"
                    placeholder="Ví dụ: Khởi tạo, Triển khai, Nghiệm thu" />
            </div>

            <div class="md:col-span-2">
                <x-ui.textarea label="Mô tả" rows="3" wire:model="phaseDescription" name="phaseDescription"
                    placeholder="Mô tả ngắn cho phase template..." />
            </div>

            <div class="grid grid-cols-1 gap-5 md:grid-cols-2 lg:md:col-span-2">
                <x-ui.input type="number" label="Trọng số mặc định (%)" required step="0.01" min="0.01"
                    max="100" wire:model="defaultWeight" name="defaultWeight" />

                <x-ui.input type="number" label="Số ngày mặc định" min="1" wire:model="defaultDurationDays"
                    name="defaultDurationDays" placeholder="Để trống nếu không áp dụng" />
            </div>

            <div class="md:col-span-2">
                <label class="inline-flex cursor-pointer items-center">
                    <input type="checkbox" wire:model="isActive" class="peer sr-only">
                    <div
                        class="bg-neutral-quaternary peer-focus:ring-brand-soft dark:peer-focus:ring-brand-soft peer-checked:after:border-buffer peer-checked:bg-brand after:inset-s-[2px] peer relative h-5 w-9 rounded-full after:absolute after:top-[2px] after:h-4 after:w-4 after:rounded-full after:bg-white after:transition-all after:content-[''] peer-checked:after:translate-x-full peer-focus:outline-none peer-focus:ring-4 rtl:peer-checked:after:-translate-x-full">
                    </div>
                    <span class="text-heading ms-3 select-none text-sm font-medium">Kích hoạt mẫu phase</span>
                </label>
                <x-ui.field-error field="isActive" />
            </div>
        </form>

        <x-slot name="footer">
            <x-ui.button variant="secondary" wire:click="closeFormModal">
                Hủy
            </x-ui.button>
            <x-ui.button type="submit" form="phase-template-form" :icon="$mode === 'edit' ? 'save' : 'add'" loading="save">
                {{ $mode === 'edit' ? 'Cap nhat mau phase' : 'Tao mau phase' }}
            </x-ui.button>
        </x-slot>
    </x-ui.slide-panel>

    <x-ui.modal wire:model="showDeleteModal" maxWidth="md">
        <x-slot name="header">
            <x-ui.form.heading icon="warning" title="Xác nhận xóa mẫu phase"
                description="Mẫu phase sẽ bị xóa khỏi hệ thống." />
        </x-slot>

        <div class="space-y-3">
            <p class="text-sm text-slate-600 dark:text-slate-300">
                Bạn có chắc chắn muốn xóa mẫu phase này không?
            </p>
            @if ($pendingDeleteTemplateName !== '')
                <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">
                    Phase: {{ $pendingDeleteTemplateName }}
                </p>
            @endif
        </div>

        <x-slot name="footer">
            <x-ui.button variant="secondary" wire:click="closeDeleteModal">
                Hủy
            </x-ui.button>
            <x-ui.button variant="danger" icon="delete" wire:click="deleteTemplate" loading="deleteTemplate">
                Xóa mẫu phase
            </x-ui.button>
        </x-slot>
    </x-ui.modal>
</div>
