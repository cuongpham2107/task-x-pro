<?php

use App\Enums\DepartmentStatus;
use App\Models\Department;
use App\Models\User;
use App\Services\Departments\DepartmentService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Phòng ban')] class extends Component {
    use WithPagination;

    protected DepartmentService $departmentService;

    #[Url(as: 'q', except: '')]
    public ?string $filterSearch = null;

    #[Url(as: 'status', except: '')]
    public ?string $filterStatus = null;

    #[Url(as: 'sort', except: 'name')]
    public string $sortBy = 'name';

    #[Url(as: 'dir', except: 'asc')]
    public string $sortDir = 'asc';

    public bool $showFormModal = false;

    public bool $showDeleteModal = false;

    public string $mode = 'create';

    public ?int $editingDepartmentId = null;

    public ?int $pendingDeleteDepartmentId = null;

    public string $pendingDeleteDepartmentName = '';

    public string $code = '';

    public string $name = '';

    public ?string $headUserId = null;

    public string $status = DepartmentStatus::Active->value;

    /** @var Collection<int, User>|null */
    public ?Collection $users = null;

    /** @var array<string, string> */
    public array $statusLabels = [];

    /** @var Collection<int, \App\Models\User> */
    public Collection $headOptions;

    public function boot(DepartmentService $departmentService): void
    {
        $this->departmentService = $departmentService;
    }

    public function mount(): void
    {
        $this->statusLabels = DepartmentStatus::options();
        $this->loadHeadOptions();
    }

    public function updatedFilterSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
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
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique(Department::class, 'code')->ignore($this->editingDepartmentId)],
            'name' => ['required', 'string', 'max:255'],
            'headUserId' => ['nullable', 'exists:users,id'],
            'status' => ['required', Rule::in(DepartmentStatus::values())],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'code.required' => 'Mã phòng ban là bắt buộc.',
            'code.max' => 'Mã phòng ban không được vượt quá 20 ký tự.',
            'code.regex' => 'Mã phòng ban chỉ chấp nhận chữ, số, dấu gạch ngang hoặc gạch dưới.',
            'code.unique' => 'Mã phòng ban đã tồn tại.',
            'name.required' => 'Tên phòng ban là bắt buộc.',
            'name.max' => 'Tên phòng ban không được vượt quá 255 ký tự.',
            'headUserId.exists' => 'Trưởng phòng đã chọn không tồn tại.',
            'status.required' => 'Trạng thái là bắt buộc.',
            'status.in' => 'Trạng thái không hợp lệ.',
        ];
    }

    public function openCreateFormModal(): void
    {
        Gate::forUser(auth()->user())->authorize('create', Department::class);

        $this->resetFormModal();
        $this->loadHeadOptions();
        $this->mode = 'create';
        $this->editingDepartmentId = null;
        $this->showFormModal = true;
    }

    public function openEditFormModal(int $departmentId): void
    {
        $this->resetFormModal();

        $actor = auth()->user();
        if ($actor === null) {
            return;
        }

        $department = $this->departmentService->findForEdit($actor, $departmentId);

        Gate::forUser($actor)->authorize('update', $department);

        $this->loadHeadOptions();
        $this->mode = 'edit';
        $this->editingDepartmentId = $department->id;
        $this->code = (string) $department->code;
        $this->name = (string) $department->name;
        $this->users = $department->users;
        $this->headUserId = $department->head_user_id !== null ? (string) $department->head_user_id : null;
        $this->status = $department->status instanceof \BackedEnum ? (string) $department->status->value : (string) $department->status;

        $this->showFormModal = true;
    }

    public function closeFormModal(): void
    {
        $this->showFormModal = false;
        $this->resetFormModal();
    }

    public function resetFormModal(): void
    {
        $this->reset(['code', 'name', 'headUserId', 'editingDepartmentId', 'users']);
        $this->status = DepartmentStatus::Active->value;
        $this->mode = 'create';
        $this->resetValidation();
    }

    private function loadHeadOptions(): void
    {
        $this->headOptions = $this->departmentService->formOptions()['heads'];
    }

    public function confirmDeleteDepartment(int $departmentId): void
    {
        $department = Department::query()->findOrFail($departmentId);

        Gate::forUser(auth()->user())->authorize('delete', $department);

        $this->pendingDeleteDepartmentId = $department->id;
        $this->pendingDeleteDepartmentName = $department->name;
        $this->showDeleteModal = true;
    }

    public function closeDeleteModal(): void
    {
        $this->showDeleteModal = false;
        $this->pendingDeleteDepartmentId = null;
        $this->pendingDeleteDepartmentName = '';
    }

    public function deleteDepartment(): void
    {
        if ($this->pendingDeleteDepartmentId === null) {
            return;
        }

        $actor = auth()->user();
        if ($actor === null) {
            return;
        }

        try {
            $department = Department::query()->findOrFail($this->pendingDeleteDepartmentId);

            $this->departmentService->delete($actor, $department);

            $this->closeDeleteModal();
            $this->resetPage();
            unset($this->departments, $this->summaryStats);

            $message = 'Xóa phòng ban thành công!';
            session()->flash('success', $message);
            $this->dispatch('toast', message: $message, type: 'success');
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());
            $message = $e->validator->errors()->first() ?: 'Không thể xóa phòng ban.';
            $this->dispatch('toast', message: $message, type: 'error');
        } catch (\Exception $e) {
            $message = 'Không thể xóa phòng ban: ' . $e->getMessage();
            session()->flash('error', $message);
            $this->dispatch('toast', message: $message, type: 'error');
        }
    }

    public function save(): void
    {
        $this->validate();

        $actor = auth()->user();
        if ($actor === null) {
            return;
        }

        $payload = [
            'code' => $this->code,
            'name' => $this->name,
            'head_user_id' => $this->headUserId,
            'status' => $this->status,
        ];

        try {
            if ($this->mode === 'edit' && $this->editingDepartmentId !== null) {
                $department = $this->departmentService->findForEdit($actor, $this->editingDepartmentId);
                $this->departmentService->update($actor, $department, $payload);

                $message = 'Cập nhật phòng ban thành công!';
            } else {
                $this->departmentService->create($actor, $payload);

                $message = 'Tạo phòng ban thành công!';
            }

            $this->closeFormModal();
            unset($this->departments, $this->summaryStats);

            session()->flash('success', $message);
            $this->dispatch('toast', message: $message, type: 'success');
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());
        } catch (\Exception $e) {
            $message = 'Không thể lưu phòng ban: ' . $e->getMessage();
            session()->flash('error', $message);
            $this->dispatch('toast', message: $message, type: 'error');
        }
    }

    #[Computed]
    public function departments()
    {
        return $this->departmentService->paginateForIndex(
            auth()->user(),
            [
                'search' => $this->filterSearch,
                'status' => $this->filterStatus,
                'sort' => $this->sortBy,
                'dir' => $this->sortDir,
            ],
            10,
        );
    }

    /**
     * @return array<string, int|float>
     */
    #[Computed]
    public function summaryStats(): array
    {
        return $this->departmentService->summaryStats(auth()->user());
    }

    /**
     * @return array<string, array{label: string, dot: string}>
     */
    #[Computed]
    public function statusFilterOptions(): array
    {
        return collect($this->statusLabels)
            ->mapWithKeys(function (string $label, string $value): array {
                return [
                    $value => [
                        'label' => $label,
                        'dot' => $value === DepartmentStatus::Active->value ? 'bg-green-500' : 'bg-slate-400',
                    ],
                ];
            })
            ->all();
    }
};
?>

<div class="flex flex-col gap-4">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <x-ui.heading title="Danh sách phòng ban"
            description="Quản lý cơ cấu phòng ban, trưởng phòng và theo dõi chỉ số KPI trung bình theo đơn vị."
            class="mb-0" />

        @can('create', App\Models\Department::class)
            <x-ui.button icon="add" size="md" wire:click="openCreateFormModal">
                Thêm phòng ban
            </x-ui.button>
        @endcan
    </div>

    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div class="w-full md:w-auto">
            <x-ui.filter-search model="filterSearch" placeholder="Tìm theo mã hoặc tên phòng ban..."
                width="w-full md:w-72" />
        </div>
        <div class="flex w-full items-center gap-2 overflow-x-auto pb-2 md:w-auto md:overflow-visible md:pb-0">
            <div class="shrink-0">
                <x-ui.filter-select model="filterStatus" :value="$filterStatus" label="Trạng thái" icon="tune"
                    all-label="Tất cả trạng thái" width="w-44" drop-width="w-52" :options="$this->statusFilterOptions" />
            </div>
            <div class="shrink-0">
                <x-ui.filter-sort :sort-by="$sortBy" :sort-dir="$sortDir" :options="[
                    'name' => 'Tên phòng ban',
                    'code' => 'Mã phòng ban',
                    'status' => 'Trạng thái',
                    'created_at' => 'Ngày tạo',
                ]" />
            </div>
        </div>
    </div>

    <x-ui.table :paginator="$this->departments" paginator-label="phòng ban">
        <x-ui.table.head>
            <x-ui.table.column width="min-w-30">Mã</x-ui.table.column>
            <x-ui.table.column width="min-w-45">Tên phòng ban</x-ui.table.column>
            <x-ui.table.column width="min-w-45">Trưởng phòng</x-ui.table.column>
            <x-ui.table.column width="min-w-24">Nhân sự</x-ui.table.column>
            <x-ui.table.column width="min-w-28">KPI TB</x-ui.table.column>
            <x-ui.table.column width="min-w-40">Trạng thái</x-ui.table.column>
            <x-ui.table.column width="min-w-20" align="right" :muted="true">Thao tác</x-ui.table.column>
        </x-ui.table.head>

        <x-ui.table.body>
            @forelse ($this->departments as $department)
                @php
                    $statusValue =
                        $department->status instanceof \BackedEnum
                            ? $department->status->value
                            : (string) $department->status;
                    $statusEnum = DepartmentStatus::tryFrom($statusValue);
                    $avgKpi = (float) ($department->avg_kpi_score ?? 0);
                @endphp
                <x-ui.table.row wire:key="department-{{ $department->id }}">
                    <x-ui.table.cell>
                        <span
                            class="rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-bold tracking-wide text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ $department->code }}</span>
                    </x-ui.table.cell>

                    <x-ui.table.cell>
                        <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $department->name }}</p>
                    </x-ui.table.cell>

                    <x-ui.table.cell>
                        @if ($department->head)
                            <div class="flex items-center gap-2">
                                <img src="{{ $department->head->avatar_url }}" alt="{{ $department->head->name }}"
                                    class="h-8 w-8 rounded-full object-cover" />
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-slate-800 dark:text-slate-200">
                                        {{ $department->head->name }}</p>
                                    <p class="truncate text-xs text-slate-500">{{ $department->head->email }}</p>
                                </div>
                            </div>
                        @else
                            <span class="text-xs text-slate-400">Chưa gán trưởng phòng</span>
                        @endif
                    </x-ui.table.cell>

                    <x-ui.table.cell>
                        <div class="text-sm font-semibold text-slate-800 dark:text-slate-200">
                            {{ $department->active_member_count }}/{{ $department->member_count }}</div>
                        <div class="text-[11px] text-slate-500">Đang làm việc / Tổng</div>
                    </x-ui.table.cell>

                    <x-ui.table.cell>
                        <div class="text-primary text-sm font-semibold">{{ number_format($avgKpi, 2) }}%</div>
                    </x-ui.table.cell>

                    <x-ui.table.cell>
                        <x-ui.badge :color="$statusValue === 'active' ? 'green' : 'slate'" size="xs">
                            {{ $statusEnum?->label() ?? $statusValue }}
                        </x-ui.badge>
                    </x-ui.table.cell>

                    <x-ui.table.cell align="right" x-on:click.stop>
                        <div class="flex items-center justify-end gap-1">
                            @can('update', $department)
                                <x-ui.icon-button icon="edit" size="sm" tooltip="Sửa"
                                    wire:click="openEditFormModal({{ $department->id }})" />
                            @endcan
                            @can('delete', $department)
                                <x-ui.icon-button icon="delete" size="sm" color="red" tooltip="Xóa"
                                    wire:click="confirmDeleteDepartment({{ $department->id }})" />
                            @endcan
                        </div>
                    </x-ui.table.cell>
                </x-ui.table.row>
            @empty
                <x-ui.table.empty colspan="7" icon="apartment"
                    message="Chưa có phòng ban nào phù hợp với bộ lọc hiện tại." />
            @endforelse
        </x-ui.table.body>
    </x-ui.table>

    <x-ui.slide-panel wire:model="showFormModal" maxWidth="4xl">
        <x-slot name="header">
            <x-ui.form.heading :icon="$mode === 'edit' ? 'edit' : 'add_business'" :title="$mode === 'edit' ? 'Cập nhật phòng ban' : 'Tạo phòng ban mới'" :description="$mode === 'edit' ? 'Chỉnh sửa thông tin phòng ban và lưu thay đổi.' : 'Nhập thông tin phòng ban để khởi tạo đơn vị mới trong hệ thống.'" />
        </x-slot>

        <form id="department-form" wire:submit="save" class="space-y-5">
            <div class="grid grid-cols-1 gap-5">
                <x-ui.input label="Mã phòng ban" name="code" wire:model="code" required class="uppercase"
                    placeholder="Ví dụ: IT, OPS, FIN" />

                <x-ui.input label="Tên phòng ban" name="name" wire:model="name" required
                    placeholder="Ví dụ: Công nghệ thông tin" />

                <x-ui.select :key="'head-select-' . $headOptions->count()" label="Trưởng phòng" name="headUserId" wire:model="headUserId"
                    placeholder="-- Chưa gán --" icon="person" :options="$headOptions
                        ->mapWithKeys(fn($head) => [$head->id => $head->name . ' - ' . $head->email])
                        ->all()" />

                <x-ui.select label="Trạng thái" name="status" wire:model="status" icon="sync" :options="$statusLabels"
                    required />
            </div>
        </form>

        {{-- Hiển thị danh sách người trong phòng ban --}}
        @if ($mode === 'edit' && $users && $users->isNotEmpty())
            <div class="mt-6">
                <x-ui.form.heading icon="person" title="Danh sách thành viên"
                    description="Danh sách thành viên trong phòng ban." />
                <div class="mt-4">
                    <x-ui.table>
                        <x-ui.table.head>
                            <x-ui.table.column>Thành viên</x-ui.table.column>
                            <x-ui.table.column>Liên hệ</x-ui.table.column>
                            <x-ui.table.column>Chức danh</x-ui.table.column>
                            <x-ui.table.column>Trạng thái</x-ui.table.column>
                        </x-ui.table.head>
                        <x-ui.table.body>
                            @foreach ($users as $user)
                                <x-ui.table.row wire:key="user-[{{ $user->id }}]">
                                    <x-ui.table.cell>
                                        <div class="flex items-center gap-2">
                                            <x-ui.avatar :src="$user->avatar_url" :name="$user->name" size="6" />
                                            <span
                                                class="font-medium text-slate-900 dark:text-white">{{ $user->name }}</span>
                                        </div>
                                    </x-ui.table.cell>
                                    <x-ui.table.cell>
                                        <p>{{ $user->email }}</p>
                                        <div class="mt-0.5 flex flex-col gap-y-1">
                                            @if ($user->phone)
                                                <p class="flex items-center gap-0.5 text-[11px] text-slate-500">
                                                    <span class="material-symbols-outlined text-[12px]">call</span>
                                                    {{ $user->phone }}
                                                </p>
                                            @endif
                                            @if ($user->telegram_id)
                                                <p class="flex items-center gap-0.5 text-[11px] text-slate-500">
                                                    <span
                                                        class="material-symbols-outlined text-[12px]">android_wifi_3_bar</span>
                                                    {{ $user->telegram_id }}
                                                </p>
                                            @endif
                                        </div>
                                    </x-ui.table.cell>
                                    <x-ui.table.cell>{{ $user->job_title ?? '--' }}</x-ui.table.cell>
                                    <x-ui.table.cell>
                                        <x-ui.badge :color="$user->status?->value === 'active' ? 'green' : 'slate'" size="xs">
                                            {{ $user->status?->label() ?? '--' }}
                                        </x-ui.badge>
                                    </x-ui.table.cell>
                                </x-ui.table.row>
                            @endforeach
                        </x-ui.table.body>
                    </x-ui.table>
                </div>
            </div>
        @endif


        <x-slot name="footer">
            <x-ui.button variant="secondary" wire:click="closeFormModal">
                Hủy
            </x-ui.button>
            <x-ui.button type="submit" form="department-form" :icon="$mode === 'edit' ? 'save' : 'add'" loading="save">
                {{ $mode === 'edit' ? 'Cập nhật phòng ban' : 'Tạo phòng ban' }}
            </x-ui.button>
        </x-slot>
    </x-ui.slide-panel>

    <x-ui.modal wire:model="showDeleteModal" maxWidth="md">
        <x-slot name="header">
            <x-ui.form.heading icon="warning" title="Xác nhận xóa phòng ban"
                description="Hành động này sẽ xóa phòng ban khỏi hệ thống." />
        </x-slot>

        <div class="space-y-3">
            <p class="text-sm text-slate-600 dark:text-slate-300">Bạn có chắc chắn muốn xóa phòng ban này không?</p>
            @if ($pendingDeleteDepartmentName !== '')
                <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">
                    Phòng ban: {{ $pendingDeleteDepartmentName }}
                </p>
            @endif
        </div>

        <x-slot name="footer">
            <x-ui.button variant="secondary" wire:click="closeDeleteModal">
                Hủy
            </x-ui.button>
            <x-ui.button variant="danger" icon="delete" wire:click="deleteDepartment" loading="deleteDepartment">
                Xóa phòng ban
            </x-ui.button>
        </x-slot>
    </x-ui.modal>
</div>
