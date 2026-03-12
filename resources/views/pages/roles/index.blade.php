<?php

use App\Services\Roles\RoleService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

new #[Title('Phân quyền')] class extends Component {
    use WithPagination;

    protected RoleService $roleService;

    #[Url(as: 'q', except: '')]
    public ?string $filterSearch = null;

    #[Url(as: 'sort', except: 'name')]
    public string $sortBy = 'name';

    #[Url(as: 'dir', except: 'asc')]
    public string $sortDir = 'asc';

    public bool $showRoleModal = false;

    public string $mode = 'create';

    public ?int $editingRoleId = null;

    public string $roleName = '';

    /** @var list<string> */
    public array $selectedPermissions = [];

    public bool $showDeleteRoleModal = false;

    public ?int $pendingDeleteRoleId = null;

    public string $pendingDeleteRoleName = '';

    /** @var array<string, list<array{id: int, name: string, label: string}>> */
    public array $permissionGroups = [];

    public function boot(RoleService $roleService): void
    {
        $this->roleService = $roleService;
    }

    public function mount(): void
    {
        Gate::forUser(auth()->user())->authorize('viewAny', Role::class);
        $this->loadPermissionGroups();
    }

    public function updatedFilterSearch(): void
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
     * Nap danh sach permission theo nhom de render checkbox trong form role.
     */
    private function loadPermissionGroups(): void
    {
        $this->permissionGroups = $this->roleService->permissionGridGroups(auth()->user());
    }

    public function openCreateRoleModal(): void
    {
        Gate::forUser(auth()->user())->authorize('create', Role::class);

        $this->resetRoleForm();
        $this->mode = 'create';
        $this->showRoleModal = true;
    }

    public function openEditRoleModal(int $roleId): void
    {
        $this->resetRoleForm();

        $actor = auth()->user();
        if ($actor === null) {
            return;
        }

        $role = $this->roleService->findForEdit($actor, $roleId);
        Gate::forUser($actor)->authorize('update', $role);

        $this->mode = 'edit';
        $this->editingRoleId = $role->id;
        $this->roleName = (string) $role->name;
        $this->selectedPermissions = $role->permissions->pluck('name')->values()->all();

        $this->showRoleModal = true;
    }

    public function closeRoleModal(): void
    {
        $this->showRoleModal = false;
        $this->resetRoleForm();
    }

    public function resetRoleForm(): void
    {
        $this->reset(['roleName', 'selectedPermissions', 'editingRoleId']);
        $this->mode = 'create';
        $this->resetValidation();
    }

    /**
     * @return array<string, array<int|string, \Illuminate\Contracts\Validation\ValidationRule|string>>
     */
    protected function roleRules(): array
    {
        return [
            'roleName' => ['required', 'string', 'min:2', 'max:125', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('roles', 'name')->where(fn($query) => $query->where('guard_name', 'web'))->ignore($this->editingRoleId)],
            'selectedPermissions' => ['array'],
            'selectedPermissions.*' => ['string', Rule::exists('permissions', 'name')->where(fn($query) => $query->where('guard_name', 'web'))],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function roleMessages(): array
    {
        return [
            'roleName.required' => 'Tên vai trò là bắt buộc.',
            'roleName.min' => 'Tên vai trò tối thiểu 2 ký tự.',
            'roleName.max' => 'Tên vai trò tối đa 125 ký tự.',
            'roleName.regex' => 'Tên vai trò chỉ cho phép chữ, số, đấu chấm, gạch ngang, gạch dưới.',
            'roleName.unique' => 'Tên vai trò đã tồn tại.',
            'selectedPermissions.*.exists' => 'Quyền đã chọn không hợp lệ.',
        ];
    }

    public function saveRole(): void
    {
        $this->validate($this->roleRules(), $this->roleMessages());

        $actor = auth()->user();
        if ($actor === null) {
            return;
        }

        $payload = [
            'name' => $this->roleName,
            'permissions' => $this->selectedPermissions,
        ];

        try {
            if ($this->mode === 'edit' && $this->editingRoleId !== null) {
                $role = Role::query()->where('guard_name', 'web')->findOrFail($this->editingRoleId);

                $this->roleService->updateRole($actor, $role, $payload);
                $message = 'Cập nhật vai trò thành công!';
            } else {
                $this->roleService->createRole($actor, $payload);
                $message = 'Tạo vai trò thành công!';
            }

            $this->closeRoleModal();
            unset($this->roles, $this->summaryStats);

            session()->flash('success', $message);
            $this->dispatch('toast', message: $message, type: 'success');
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());
        } catch (\Exception $e) {
            $message = 'Không thể lưu vai trò: ' . $e->getMessage();
            session()->flash('error', $message);
            $this->dispatch('toast', message: $message, type: 'error');
        }
    }

    public function confirmDeleteRole(int $roleId): void
    {
        $role = Role::query()->where('guard_name', 'web')->findOrFail($roleId);

        Gate::forUser(auth()->user())->authorize('delete', $role);

        $this->pendingDeleteRoleId = $role->id;
        $this->pendingDeleteRoleName = (string) $role->name;
        $this->showDeleteRoleModal = true;
    }

    public function closeDeleteRoleModal(): void
    {
        $this->showDeleteRoleModal = false;
        $this->pendingDeleteRoleId = null;
        $this->pendingDeleteRoleName = '';
    }

    public function deleteRole(): void
    {
        if ($this->pendingDeleteRoleId === null) {
            return;
        }

        $actor = auth()->user();
        if ($actor === null) {
            return;
        }

        try {
            $role = Role::query()->where('guard_name', 'web')->findOrFail($this->pendingDeleteRoleId);

            $this->roleService->deleteRole($actor, $role);

            $this->closeDeleteRoleModal();
            $this->resetPage();
            unset($this->roles, $this->summaryStats);

            $message = 'Xóa vai trò thành công!';
            session()->flash('success', $message);
            $this->dispatch('toast', message: $message, type: 'success');
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());
            $message = $e->validator->errors()->first() ?: 'Không thể xóa vai trò.';
            $this->dispatch('toast', message: $message, type: 'error');
        } catch (\Exception $e) {
            $message = 'Không thể xóa vai trò: ' . $e->getMessage();
            session()->flash('error', $message);
            $this->dispatch('toast', message: $message, type: 'error');
        }
    }

    /**
     * Lay ten nhom de hien thi tren giao dien.
     */
    public function permissionGroupLabel(string $group): string
    {
        return Str::headline($group);
    }

    /**
     * Bat/tat tat ca quyen trong mot nhom.
     */
    public function toggleGroupPermissions(string $group): void
    {
        $groupPermissions = collect($this->permissionGroups[$group])->pluck('name')->all();
        $allSelected = collect($groupPermissions)->every(fn($name) => in_array($name, $this->selectedPermissions));

        if ($allSelected) {
            // Deselect all in group
            $this->selectedPermissions = array_values(array_filter($this->selectedPermissions, fn($name) => !in_array($name, $groupPermissions)));
        } else {
            // Select all in group
            $this->selectedPermissions = array_values(array_unique(array_merge($this->selectedPermissions, $groupPermissions)));
        }
    }

    #[Computed]
    public function roles()
    {
        return $this->roleService->paginateForIndex(
            auth()->user(),
            [
                'search' => $this->filterSearch,
                'sort' => $this->sortBy,
                'dir' => $this->sortDir,
            ],
            10,
        );
    }
};
?>

<div class="flex flex-col gap-4">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <x-ui.heading title="Phân quyền hệ thống" description="Quản lý vai trò và gán quyền cho vai trò." class="mb-0" />

        <div class="flex flex-wrap items-center gap-2">
            @can('create', Spatie\Permission\Models\Role::class)
                <x-ui.button icon="shield_person" wire:click="openCreateRoleModal">
                    Thêm vai trò
                </x-ui.button>
            @endcan
        </div>
    </div>

    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <x-ui.filter-search model="filterSearch" placeholder="Tìm vai trò..." width="w-full md:w-72" />

        <div class="flex items-center gap-2 overflow-x-auto pb-2 md:overflow-visible md:pb-0">
            <div class="shrink-0">
                <x-ui.filter-sort :sort-by="$sortBy" :sort-dir="$sortDir" :options="[
                    'name' => 'Tên vai trò',
                    'permissions_count' => 'Số quyền',
                    'users_count' => 'Số người dùng',
                    'created_at' => 'Ngày tạo',
                ]" />
            </div>
        </div>
    </div>

    <x-ui.table :paginator="$this->roles" paginator-label="Vai trò">
        <x-ui.table.head>
            <x-ui.table.column width="min-w-44">Vai trò</x-ui.table.column>
            <x-ui.table.column width="min-w-72">Quyền</x-ui.table.column>
            <x-ui.table.column width="min-w-28">Người dùng</x-ui.table.column>
            <x-ui.table.column width="min-w-32">Cập nhật</x-ui.table.column>
            <x-ui.table.column width="min-w-20" align="right" :muted="true">Thao tác</x-ui.table.column>
        </x-ui.table.head>

        <x-ui.table.body>
            @forelse ($this->roles as $role)
                <x-ui.table.row wire:key="role-{{ $role->id }}">
                    <x-ui.table.cell>
                        <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $role->name }}</p>
                        <p class="text-xs text-slate-500">guard: {{ $role->guard_name }}</p>
                    </x-ui.table.cell>

                    <x-ui.table.cell>
                        @if ($role->permissions->isNotEmpty())
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($role->permissions->take(6) as $permission)
                                    <span
                                        class="rounded-full bg-slate-100 px-2 py-1 text-[11px] font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-300">{{ $permission->name }}</span>
                                @endforeach
                                @if ($role->permissions_count > 6)
                                    <span
                                        class="bg-primary/10 text-primary rounded-full px-2 py-1 text-[11px] font-semibold">+{{ $role->permissions_count - 6 }}</span>
                                @endif
                            </div>
                        @else
                            <span class="text-xs text-slate-400">Chưa có quyền</span>
                        @endif
                    </x-ui.table.cell>

                    <x-ui.table.cell>
                        <span
                            class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $role->users_count }}</span>
                    </x-ui.table.cell>

                    <x-ui.table.cell>
                        <span
                            class="text-xs text-slate-600 dark:text-slate-300">{{ $role->updated_at?->format('d/m/Y H:i') }}</span>
                    </x-ui.table.cell>

                    <x-ui.table.cell align="right" x-on:click.stop>
                        <div class="flex items-center justify-end gap-1">
                            @can('update', $role)
                                <x-ui.icon-button icon="edit" size="sm" tooltip="Sửa vai trò"
                                    wire:click="openEditRoleModal({{ $role->id }})" />
                            @endcan
                            @can('delete', $role)
                                <x-ui.icon-button icon="delete" size="sm" color="red" tooltip="Xóa vai trò"
                                    wire:click="confirmDeleteRole({{ $role->id }})" />
                            @endcan
                        </div>
                    </x-ui.table.cell>
                </x-ui.table.row>
            @empty
                <x-ui.table.empty colspan="5" icon="shield_person"
                    message="Chưa có vai trò phù hợp với bộ lọc hiện tại." />
            @endforelse
        </x-ui.table.body>
    </x-ui.table>

    <x-ui.slide-panel wire:model="showRoleModal" maxWidth="4xl">
        <x-slot name="header">
            <x-ui.form.heading :icon="$mode === 'edit' ? 'edit' : 'shield_person'" :title="$mode === 'edit' ? 'Cập nhập vai trò' : 'Tạo vai trò mới'" :description="$mode === 'edit' ? 'Cập nhật tên vai trò và bộ quyền được gán.' : 'Nhập tên vai trò và chọn quyền cần cấp.'" />
        </x-slot>

        <form id="role-form" wire:submit="saveRole" class="space-y-5">
            <div>
                <x-ui.input label="Tên vai trò" name="roleName" wire:model="roleName"
                    placeholder="Ví dụ: qa_manager, support_leader" icon="badge" required />
            </div>

            <div class="space-y-3">
                <div class="flex items-center justify-between gap-2">
                    <p class="label-text">Quyền</p>
                    <p class="text-xs text-slate-500">Chọn nhiều quyền cho vai trò</p>
                </div>

                <div class="max-h-[60vh] overflow-auto pr-2">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        @forelse ($permissionGroups as $group => $permissions)
                            @php
                                $groupPermissionNames = collect($permissions)->pluck('name')->all();
                                $allGroupSelected = collect($groupPermissionNames)->every(
                                    fn($name) => in_array($name, $this->selectedPermissions),
                                );
                            @endphp
                            <div class="h-fit rounded-xl border border-slate-200 p-3 dark:border-slate-800">
                                <div class="mb-2.5 flex items-center justify-between gap-2">
                                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                                        {{ $this->permissionGroupLabel($group) }}
                                    </p>
                                    <button type="button" wire:click="toggleGroupPermissions('{{ $group }}')"
                                        class="text-primary hover:text-primary-dark select-none text-[10px] font-semibold uppercase tracking-wider transition-colors">
                                        {{ $allGroupSelected ? 'Bỏ chọn tất cả' : 'Chọn tất cả' }}
                                    </button>
                                </div>
                                <div class="grid grid-cols-1 gap-1.5">
                                    @foreach ($permissions as $permission)
                                        <label
                                            class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-100 px-2 py-1.5 text-xs transition-colors hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/60">
                                            <input type="checkbox" wire:model="selectedPermissions"
                                                value="{{ $permission['name'] }}"
                                                class="text-primary focus:ring-primary h-3.5 w-3.5 rounded border-slate-300" />
                                            <span class="truncate font-medium text-slate-700 dark:text-slate-200"
                                                title="{{ $permission['name'] }}">
                                                {{ $permission['name'] }}
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @empty
                            <div
                                class="col-span-full rounded-lg border border-dashed border-slate-300 px-4 py-6 text-center text-xs text-slate-500 dark:border-slate-700 dark:text-slate-400">
                                Chưa có quyền nào để gán vai trò.
                            </div>
                        @endforelse
                    </div>
                </div>

                <x-ui.field-error field="selectedPermissions.*" />
            </div>
        </form>

        <x-slot name="footer">
            <x-ui.button variant="secondary" wire:click="closeRoleModal">
                Hủy
            </x-ui.button>
            <x-ui.button type="submit" form="role-form" :icon="$mode === 'edit' ? 'save' : 'add'" loading="saveRole">
                {{ $mode === 'edit' ? 'Lưu thay đổi' : 'Tạo vai trò' }}
            </x-ui.button>
        </x-slot>
    </x-ui.slide-panel>

    <x-ui.modal wire:model="showDeleteRoleModal" maxWidth="md">
        <x-slot name="header">
            <x-ui.form.heading icon="warning" title="Xác nhận xóa vai trò"
                description="Vai trò sẽ bị xóa khỏi hệ thống." />
        </x-slot>

        <div class="space-y-3">
            <p class="text-sm text-slate-600 dark:text-slate-300">Bạn có chắc chắn muốn xóa vai trò này không?</p>
            @if ($pendingDeleteRoleName !== '')
                <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Vai trò:
                    {{ $pendingDeleteRoleName }}</p>
            @endif
        </div>

        <x-slot name="footer">
            <x-ui.button variant="secondary" wire:click="closeDeleteRoleModal">
                Hủy
            </x-ui.button>
            <x-ui.button variant="danger" icon="delete" wire:click="deleteRole" loading="deleteRole">
                Xóa vai trò
            </x-ui.button>
        </x-slot>
    </x-ui.modal>
</div>
