<?php

use App\Enums\UserStatus;
use App\Models\User;
use App\Services\Users\UserService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Người dùng')] class extends Component {
    use WithPagination;

    protected UserService $userService;

    #[Url(as: 'q', except: '')]
    public ?string $filterSearch = null;

    #[Url(as: 'status', except: '')]
    public ?string $filterStatus = null;

    #[Url(as: 'department', except: '')]
    public ?string $filterDepartmentId = null;

    #[Url(as: 'sort', except: 'name')]
    public string $sortBy = 'name';

    #[Url(as: 'dir', except: 'asc')]
    public string $sortDir = 'asc';

    public bool $showFormModal = false;

    public bool $showDeleteModal = false;

    public string $mode = 'create';

    public ?int $editingUserId = null;

    public ?int $pendingDeleteUserId = null;

    public string $pendingDeleteUserName = '';

    public string $employeeCode = '';

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $phone = '';

    public string $jobTitle = '';

    public ?string $departmentId = null;

    public string $status = UserStatus::Active->value;

    public string $telegramId = '';

    public ?string $roleId = null;

    /** @var array<string, string> */
    public array $statusLabels = [];

    /** @var Collection<int, \App\Models\Department> */
    public Collection $departmentOptions;

    /** @var Collection<int, \Spatie\Permission\Models\Role> */
    public Collection $roleOptions;

    public function boot(UserService $userService): void
    {
        $this->userService = $userService;
    }

    public function mount(): void
    {
        $this->statusLabels = UserStatus::options();
        $this->loadFormOptions();
    }

    public function updatedFilterSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatedFilterDepartmentId(): void
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
            'employeeCode' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique(User::class, 'employee_code')->ignore($this->editingUserId)],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique(User::class, 'email')->ignore($this->editingUserId)],
            'password' => $this->mode === 'create' ? ['required', 'string', 'min:6', 'max:255'] : ['nullable', 'string', 'min:6', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'jobTitle' => ['nullable', 'string', 'max:255'],
            'departmentId' => ['nullable', 'exists:departments,id'],
            'status' => ['required', Rule::in(UserStatus::values())],
            'telegramId' => ['nullable', 'string', 'max:100'],
            'roleId' => ['required', 'exists:roles,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'employeeCode.required' => 'Mã nhân sự là bắt buộc.',
            'employeeCode.max' => 'Mã nhân sự không được vượt quá 20 ký tự.',
            'employeeCode.regex' => 'Mã nhân sự chỉ chấp nhận chữ, số, dấu gạch ngang hoặc gạch dưới.',
            'employeeCode.unique' => 'Mã nhân sự đã tồn tại.',
            'name.required' => 'Tên người dùng là bắt buộc.',
            'name.max' => 'Tên người dùng không được vượt quá 255 ký tự.',
            'email.required' => 'Email là bắt buộc.',
            'email.email' => 'Email không đúng định dạng.',
            'email.unique' => 'Email đã tồn tại.',
            'password.required' => 'Mật khẩu là bắt buộc.',
            'password.min' => 'Mật khẩu tối thiểu 6 ký tự.',
            'phone.max' => 'Số điện thoại không được vượt quá 20 ký tự.',
            'jobTitle.max' => 'Chức danh không được vượt quá 255 ký tự.',
            'departmentId.exists' => 'Phòng ban đã chọn không tồn tại.',
            'status.required' => 'Trạng thái là bắt buộc.',
            'status.in' => 'Trạng thái không hợp lệ.',
            'telegramId.max' => 'Telegram ID không được vượt quá 100 ký tự.',
            'roleId.required' => 'Vui lòng chọn quyền hạn.',
            'roleId.exists' => 'Quyền hạn đã chọn không tồn tại.',
        ];
    }

    public function openCreateFormModal(): void
    {
        Gate::forUser(auth()->user())->authorize('create', User::class);

        $this->resetFormModal();
        $this->loadFormOptions();
        $this->mode = 'create';
        $this->editingUserId = null;
        $this->roleId = null;
        $this->showFormModal = true;
    }

    public function openEditFormModal(int $userId): void
    {
        $this->resetFormModal();

        $actor = auth()->user();
        if ($actor === null) {
            return;
        }

        $targetUser = $this->userService->findForEdit($actor, $userId);

        Gate::forUser($actor)->authorize('update', $targetUser);

        $this->loadFormOptions();
        $this->mode = 'edit';
        $this->editingUserId = $targetUser->id;
        $this->employeeCode = (string) ($targetUser->employee_code ?? '');
        $this->name = (string) $targetUser->name;
        $this->email = (string) $targetUser->email;
        $this->password = '';
        $this->phone = (string) ($targetUser->phone ?? '');
        $this->jobTitle = (string) ($targetUser->job_title ?? '');
        $this->departmentId = $targetUser->department_id !== null ? (string) $targetUser->department_id : null;
        $this->status = $targetUser->status instanceof \BackedEnum ? (string) $targetUser->status->value : (string) $targetUser->status;
        $this->telegramId = (string) ($targetUser->telegram_id ?? '');
        $this->roleId = (string) ($targetUser->roles->first()?->id ?? '');

        $this->showFormModal = true;
    }

    public function closeFormModal(): void
    {
        $this->showFormModal = false;
        $this->resetFormModal();
    }

    public function resetFormModal(): void
    {
        $this->reset(['employeeCode', 'name', 'email', 'password', 'phone', 'jobTitle', 'departmentId', 'telegramId', 'editingUserId', 'roleId']);
        $this->status = UserStatus::Active->value;
        $this->mode = 'create';
        $this->resetValidation();
    }

    private function loadFormOptions(): void
    {
        $options = $this->userService->formOptions();
        $this->departmentOptions = $options['departments'];
        $this->roleOptions = $options['roles'];
    }

    public function save(): void
    {
        $this->validate();

        $actor = auth()->user();
        if ($actor === null) {
            return;
        }

        $payload = [
            'employee_code' => $this->employeeCode,
            'name' => $this->name,
            'email' => $this->email,
            'password' => $this->password,
            'phone' => $this->phone,
            'job_title' => $this->jobTitle,
            'department_id' => $this->departmentId,
            'status' => $this->status,
            'telegram_id' => $this->telegramId,
            'role_ids' => $this->roleId ? [$this->roleId] : [],
        ];

        try {
            if ($this->mode === 'edit' && $this->editingUserId !== null) {
                $targetUser = $this->userService->findForEdit($actor, $this->editingUserId);
                $this->userService->update($actor, $targetUser, $payload);

                $message = 'Cập nhật người dùng thành công!';
            } else {
                $this->userService->create($actor, $payload);

                $message = 'Tạo người dùng thành công!';
            }

            $this->closeFormModal();
            unset($this->users, $this->summaryStats);

            session()->flash('success', $message);
            $this->dispatch('toast', message: $message, type: 'success');
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());
        } catch (\Exception $e) {
            $message = 'Không thể lưu người dùng: ' . $e->getMessage();
            session()->flash('error', $message);
            $this->dispatch('toast', message: $message, type: 'error');
        }
    }

    public function confirmDeleteUser(int $userId): void
    {
        $targetUser = User::query()->findOrFail($userId);

        Gate::forUser(auth()->user())->authorize('delete', $targetUser);

        $this->pendingDeleteUserId = $targetUser->id;
        $this->pendingDeleteUserName = $targetUser->name;
        $this->showDeleteModal = true;
    }

    public function closeDeleteModal(): void
    {
        $this->showDeleteModal = false;
        $this->pendingDeleteUserId = null;
        $this->pendingDeleteUserName = '';
    }

    public function deleteUser(): void
    {
        if ($this->pendingDeleteUserId === null) {
            return;
        }

        $actor = auth()->user();
        if ($actor === null) {
            return;
        }

        try {
            $targetUser = User::query()->findOrFail($this->pendingDeleteUserId);
            $this->userService->delete($actor, $targetUser);

            $this->closeDeleteModal();
            $this->resetPage();
            unset($this->users, $this->summaryStats);

            $message = 'Xóa người dùng thành công!';
            session()->flash('success', $message);
            $this->dispatch('toast', message: $message, type: 'success');
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());
            $message = $e->validator->errors()->first() ?: 'Không thể xóa người dùng.';
            $this->dispatch('toast', message: $message, type: 'error');
        } catch (\Exception $e) {
            $message = 'Không thể xóa người dùng: ' . $e->getMessage();
            session()->flash('error', $message);
            $this->dispatch('toast', message: $message, type: 'error');
        }
    }

    #[Computed]
    public function users()
    {
        return $this->userService->paginateForIndex(
            auth()->user(),
            [
                'search' => $this->filterSearch,
                'status' => $this->filterStatus,
                'department_id' => $this->filterDepartmentId,
                'sort' => $this->sortBy,
                'dir' => $this->sortDir,
            ],
            10,
        );
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function summaryStats(): array
    {
        return $this->userService->summaryStats(auth()->user());
    }

    /**
     * @return array<string, array{label: string, dot: string}>
     */
    #[Computed]
    public function statusFilterOptions(): array
    {
        return collect($this->statusLabels)
            ->mapWithKeys(function (string $label, string $value): array {
                $dot = match ($value) {
                    UserStatus::Active->value => 'bg-green-500',
                    UserStatus::OnLeave->value => 'bg-amber-400',
                    default => 'bg-slate-400',
                };

                return [$value => ['label' => $label, 'dot' => $dot]];
            })
            ->all();
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function departmentFilterOptions(): array
    {
        return $this->departmentOptions->mapWithKeys(fn($department): array => [(string) $department->id => $department->name])->all();
    }
};
?>

<div class="flex flex-col gap-4">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <x-ui.heading title="Danh sách người dùng"
            description="Quản lý nhân sự nội bộ: thông tin liên hệ, phòng ban và trạng thái làm việc." class="mb-0" />

        @can('create', App\Models\User::class)
            <x-ui.button icon="add" size="md" wire:click="openCreateFormModal">
                Thêm người dùng
            </x-ui.button>
        @endcan
    </div>

    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div class="w-full md:w-auto">
            <x-ui.filter-search model="filterSearch" placeholder="Tìm theo mã nhân sự, tên, email, điện thoại..."
                width="w-full md:w-80" />
        </div>
        <div class="flex w-full items-center gap-2 overflow-x-auto pb-2 md:w-auto md:overflow-visible md:pb-0">
            <div class="shrink-0">
                <x-ui.filter-select model="filterStatus" :value="$filterStatus" label="Trạng thái" icon="tune"
                    all-label="Tất cả trạng thái" width="w-44" drop-width="w-52" :options="$this->statusFilterOptions" />
            </div>

            <div class="shrink-0">
                <x-ui.filter-select model="filterDepartmentId" :value="$filterDepartmentId" label="Phòng ban" icon="apartment"
                    all-label="Tất cả phòng ban" width="w-44" drop-width="w-56" :options="$this->departmentFilterOptions" />
            </div>

            <div class="shrink-0">
                <x-ui.filter-sort :sort-by="$sortBy" :sort-dir="$sortDir" :options="[
                    'name' => 'Tên người dùng',
                    'email' => 'Email',
                    'employee_code' => 'Mã nhân sự',
                    'status' => 'Trạng thái',
                    'created_at' => 'Ngày tạo',
                ]" />
            </div>
        </div>
    </div>

    <x-ui.table :paginator="$this->users" paginator-label="người dùng">
        <x-ui.table.head>
            <x-ui.table.sort-column field="name" :sort-by="$sortBy" :sort-dir="$sortDir" width="min-w-62">Người
                dùng</x-ui.table.sort-column>
            <x-ui.table.sort-column field="employee_code" :sort-by="$sortBy" :sort-dir="$sortDir" width="min-w-28">Mã
                NS</x-ui.table.sort-column>
            <x-ui.table.column width="min-w-42">Phòng ban</x-ui.table.column>
            <x-ui.table.column width="min-w-36">Chức danh</x-ui.table.column>
            <x-ui.table.sort-column field="status" :sort-by="$sortBy" :sort-dir="$sortDir" width="min-w-28">Trạng
                thái</x-ui.table.sort-column>
            <x-ui.table.column width="min-w-20" align="right" :muted="true">Thao tác</x-ui.table.column>
        </x-ui.table.head>

        <x-ui.table.body>
            @forelse ($this->users as $user)
                @php
                    $statusValue = $user->status instanceof \BackedEnum ? $user->status->value : (string) $user->status;
                    $statusEnum = UserStatus::tryFrom($statusValue);
                    $statusColor = match ($statusValue) {
                        'active' => 'green',
                        'on_leave' => 'amber',
                        default => 'slate',
                    };
                @endphp
                <x-ui.table.row wire:key="user-{{ $user->id }}">


                    <x-ui.table.cell>
                        <div class="flex items-center gap-2">
                            <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}"
                                class="h-9 w-9 rounded-full object-cover" />
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-slate-900 dark:text-white">
                                    {{ $user->name }}</p>
                                <p class="truncate text-xs text-slate-500">Email: {{ $user->email }}</p>
                                @if ($user->phone)
                                    <p class="truncate text-[11px] text-slate-400">Điện thoại: {{ $user->phone }}</p>
                                @endif
                            </div>
                        </div>
                    </x-ui.table.cell>
                    <x-ui.table.cell>
                        <span
                            class="rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-bold tracking-wide text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ $user->employee_code ?: '--' }}</span>
                    </x-ui.table.cell>

                    <x-ui.table.cell>
                        <span
                            class="text-sm text-slate-700 dark:text-slate-300">{{ $user->department?->name ?? 'Chưa gán' }}</span>
                    </x-ui.table.cell>

                    <x-ui.table.cell>
                        <span class="text-sm text-slate-700 dark:text-slate-300">{{ $user->job_title ?? '--' }}</span>
                    </x-ui.table.cell>

                    <x-ui.table.cell>
                        <x-ui.badge :color="$statusColor" size="xs">
                            {{ $statusEnum?->label() ?? $statusValue }}
                        </x-ui.badge>
                    </x-ui.table.cell>

                    <x-ui.table.cell align="right" x-on:click.stop>
                        <div class="flex items-center justify-end gap-1">
                            @can('update', $user)
                                <x-ui.icon-button icon="edit" size="sm" tooltip="Sửa"
                                    wire:click="openEditFormModal({{ $user->id }})" />
                            @endcan
                            @can('delete', $user)
                                <x-ui.icon-button icon="delete" size="sm" color="red" tooltip="Xóa"
                                    wire:click="confirmDeleteUser({{ $user->id }})" />
                            @endcan
                        </div>
                    </x-ui.table.cell>
                </x-ui.table.row>
            @empty
                <x-ui.table.empty colspan="6" icon="group"
                    message="Chưa có người dùng nào phù hợp với bộ lọc hiện tại." />
            @endforelse
        </x-ui.table.body>
    </x-ui.table>

    <x-ui.slide-panel wire:model="showFormModal" maxWidth="3xl">
        <x-slot name="header">
            <x-ui.form.heading :icon="$mode === 'edit' ? 'edit' : 'person_add'" :title="$mode === 'edit' ? 'Cập nhật người dùng' : 'Tạo người dùng mới'" :description="$mode === 'edit' ? 'Chỉnh sửa thông tin người dùng và lưu thay đổi.' : 'Nhập thông tin tài khoản để thêm người dùng mới.'" />
        </x-slot>

        <form id="user-form" wire:submit="save" class="space-y-5">
            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                <x-ui.input label="Mã nhân sự" name="employeeCode" wire:model="employeeCode" required class="uppercase"
                    placeholder="Ví dụ: NV0009" />

                <x-ui.input label="Họ tên" name="name" wire:model="name" required
                    placeholder="Ví dụ: Trần Hoàng Kiệt" />

                <x-ui.input label="Email" name="email" type="email" wire:model="email" required
                    placeholder="Ví dụ: user@taskxpro.vn" />

                <x-ui.input label="Mật khẩu" name="password" type="password" wire:model="password" :required="$mode === 'create'"
                    :placeholder="$mode === 'edit' ? 'Để trống nếu không đổi mật khẩu' : 'Nhập mật khẩu đăng nhập'" />

                <x-ui.input label="Số điện thoại" name="phone" wire:model="phone" placeholder="Ví dụ: 0909123456" />

                <x-ui.input label="Chức danh" name="jobTitle" wire:model="jobTitle"
                    placeholder="Ví dụ: Trưởng nhóm dự án" />

                <x-ui.select label="Phòng ban" name="departmentId" wire:model="departmentId"
                    placeholder="-- Chưa gán --" icon="apartment" :options="$departmentOptions->mapWithKeys(fn($d) => [$d->id => $d->name])->all()" />

                <x-ui.select label="Trạng thái" name="status" wire:model="status" icon="sync" :options="$statusLabels"
                    required />

                <div class="md:col-span-2">
                    <x-ui.input label="Telegram ID" name="telegramId" wire:model="telegramId" icon="chat" />
                </div>

                <div class="md:col-span-2">
                    <x-ui.select label="Quyền hạn" name="roleId" wire:model="roleId" icon="shield"
                        placeholder="-- Chọn quyền --" :options="$roleOptions->mapWithKeys(fn($r) => [$r->id => strtoupper($r->name)])->all()" required />
                </div>
            </div>
        </form>

        <x-slot name="footer">
            <x-ui.button variant="secondary" wire:click="closeFormModal">
                Hủy
            </x-ui.button>
            <x-ui.button type="submit" form="user-form" :icon="$mode === 'edit' ? 'save' : 'add'" loading="save">
                {{ $mode === 'edit' ? 'Cập nhật người dùng' : 'Tạo người dùng' }}
            </x-ui.button>
        </x-slot>
    </x-ui.slide-panel>

    <x-ui.modal wire:model="showDeleteModal" maxWidth="md">
        <x-slot name="header">
            <x-ui.form.heading icon="warning" title="Xác nhận xóa người dùng"
                description="Hành động này sẽ xóa người dùng khỏi hệ thống." />
        </x-slot>

        <div class="space-y-3">
            <p class="text-sm text-slate-600 dark:text-slate-300">Bạn có chắc chắn muốn xóa người dùng này không?</p>
            @if ($pendingDeleteUserName !== '')
                <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">
                    Người dùng: {{ $pendingDeleteUserName }}
                </p>
            @endif
            <p class="text-xs text-slate-500">Nếu user đã liên kết dữ liệu nghiệp vụ, hệ thống có thể từ chối xóa để
                đảm bảo toàn vẹn dữ liệu.</p>
        </div>

        <x-slot name="footer">
            <x-ui.button variant="secondary" wire:click="closeDeleteModal">
                Hủy
            </x-ui.button>
            <x-ui.button variant="danger" icon="delete" wire:click="deleteUser" loading="deleteUser">
                Xóa người dùng
            </x-ui.button>
        </x-slot>
    </x-ui.modal>
</div>
