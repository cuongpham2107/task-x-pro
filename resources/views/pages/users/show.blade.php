<?php

use Livewire\Component;
use App\Models\User;
use App\Models\Project;
use App\Models\Task;
use App\Services\Users\UserService;
use Livewire\Attributes\Title;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\WithFileUploads;

new #[Title('Thông tin cá nhân')] class extends Component {
    use WithFileUploads;

    protected UserService $userService;

    public User $user;

    public bool $showEditModal = false;
    public bool $showPasswordModal = false;

    #[Validate(['required', 'string', 'max:255'])]
    public string $editName = '';

    #[Validate]
    public string $editEmail = '';

    #[Validate(['nullable', 'string', 'max:20'])]
    public string $editPhone = '';

    #[Validate(['nullable', 'string', 'max:50'])]
    public string $editTelegramId = '';

    #[Validate(['nullable', 'string', 'max:100'])]
    public string $editJobTitle = '';

    #[Validate(['nullable', 'exists:departments,id'])]
    public ?int $editDepartmentId = null;

    #[Validate(['nullable', 'image', 'max:2048'])]
    public $newAvatar;

    #[Validate(['required', 'current_password'])]
    public string $currentPassword = '';

    #[Validate(['required', 'confirmed', 'min:8'])]
    public string $newPassword = '';

    public string $newPassword_confirmation = '';

    public $departmentOptions = [];

    public function boot(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function mount(User $user)
    {
        $this->user = $user->load(['department', 'roles']);

        // If viewing own profile or admin/manager, allow. Otherwise 403.
        // For now we assume the route middleware handles 'view' permission,
        // but we might want to restrict viewing other's detailed profile unless admin.
        if (auth()->id() !== $user->id && !auth()->user()->can('update', $user)) {
            abort(403);
        }
    }

    public function getProjectsProperty()
    {
        return $this->userService->getParticipatingProjects(auth()->user(), $this->user->id, 4);
    }

    public function getRecentTasksProperty()
    {
        return $this->userService->getRecentTasks(auth()->user(), $this->user->id, 5);
    }

    public function openEditModal()
    {
        if (auth()->id() !== $this->user->id && !auth()->user()->can('update', $this->user)) {
            abort(403);
        }

        $this->editName = $this->user->name;
        $this->editEmail = $this->user->email;
        $this->editPhone = $this->user->phone ?? '';
        $this->editTelegramId = $this->user->telegram_id ?? '';
        $this->editJobTitle = $this->user->job_title ?? '';
        $this->editDepartmentId = $this->user->department_id;
        $this->newAvatar = null;

        $this->departmentOptions = $this->userService->formOptions()['departments'];

        $this->showEditModal = true;
    }

    public function saveUser()
    {
        if (auth()->id() !== $this->user->id && !auth()->user()->can('update', $this->user)) {
            abort(403);
        }

        $this->validate([
            'editName' => 'required',
            'editEmail' => 'required|email|unique:users,email,' . $this->user->id,
            'editPhone' => 'nullable',
            'editTelegramId' => 'nullable',
            'editJobTitle' => 'nullable',
            'editDepartmentId' => 'nullable',
            'newAvatar' => 'nullable',
        ]);

        $payload = [
            'name' => $this->editName,
            'email' => $this->editEmail,
            'phone' => $this->editPhone,
            'telegram_id' => $this->editTelegramId,
            'job_title' => $this->editJobTitle,
            'department_id' => $this->editDepartmentId,
        ];

        if ($this->newAvatar) {
            // $path = $this->newAvatar->store('avatars', 'public');
            // Assuming the mutation service handles file upload if passed as UploadedFile or we need to handle it here.
            // Let's check UserMutationService.
            $payload['avatar'] = $this->newAvatar;
        }

        $this->userService->update(auth()->user(), $this->user, $payload);

        $this->showEditModal = false;
        $this->dispatch('toast', message: 'Cập nhật thông tin thành công!', type: 'success');
    }

    public function openPasswordModal()
    {
        if (auth()->id() !== $this->user->id) {
            abort(403);
        }

        $this->reset(['currentPassword', 'newPassword', 'newPassword_confirmation']);
        $this->showPasswordModal = true;
    }

    public function changePassword()
    {
        if (auth()->id() !== $this->user->id) {
            abort(403);
        }

        $this->validate([
            'currentPassword' => 'required',
            'newPassword' => 'required',
        ]);

        $this->userService->update(auth()->user(), $this->user, [
            'password' => $this->newPassword,
        ]);

        $this->showPasswordModal = false;
        $this->dispatch('toast', message: 'Đổi mật khẩu thành công!', type: 'success');
    }

    public function getTelegramId(){
        try {
            $response = Http::get('https://api.telegram.org/bot' . env('TELEGRAM_TOKEN') . '/getUpdates');
            $result =$response->json()['result'][0]['message']['from']['id'];
            $this->editTelegramId = $result;
            $this->dispatch('toast', message: 'Lấy ID Telegram thành công!', type: 'success');
        } catch (\Exception $e) {
            $this->dispatch('toast', message: 'Lỗi: ' . $e->getMessage(), type: 'error');
        }   
    }
};
?>
<div>

    <main class="mx-auto w-full flex-1 space-y-8 p-2 md:p-4">
        <section
            class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="from-primary bg-linear-to-r h-32 to-blue-400"
                data-alt="Blue gradient abstract background pattern"></div>
            <div class="-mt-12 flex flex-col items-end gap-6 px-8 pb-8 md:flex-row">
                <div
                    class="relative h-32 w-32 overflow-hidden rounded-2xl border-4 border-white bg-slate-200 dark:border-slate-900">
                    <img class="h-full w-full object-cover" data-alt="User avatar" src="{{ $user->avatar_url }}" />
                </div>
                <div class="flex flex-1 flex-col items-start justify-between gap-4 md:flex-row md:items-center">
                    <div>
                        <h1 class="text-2xl font-bold">{{ $user->name }}</h1>
                        <p class="font-medium text-slate-500 dark:text-slate-400">
                            {{ $user->job_title ?? 'N/A' }} • {{ $user->department?->name ?? 'Chưa phân phòng ban' }}
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        @if (auth()->id() === $user->id)
                            <x-ui.button wire:click="openEditModal" icon="edit" variant="primary">
                                Chỉnh sửa
                            </x-ui.button>
                            <x-ui.button wire:click="openPasswordModal" icon="lock_reset" variant="secondary">
                                Đổi mật khẩu
                            </x-ui.button>
                        @endif

                        @can('update', $user)
                            @if (auth()->id() !== $user->id)
                                <button
                                    class="flex items-center gap-2 rounded-lg bg-red-50 px-4 py-2 font-semibold text-red-600 transition-colors hover:bg-red-100">
                                    <span class="material-symbols-outlined text-sm">block</span>
                                    Khóa tài khoản
                                </button>
                            @endif
                        @endcan
                    </div>
                </div>
            </div>
        </section>
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <!-- Details Section -->
            <div class="space-y-6 lg:col-span-1">
                <div
                    class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h3 class="mb-6 flex items-center gap-2 text-lg font-bold">
                        <span class="material-symbols-outlined text-primary">badge</span>
                        Thông tin chi tiết
                    </h3>
                    <div class="space-y-4">
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-semibold uppercase tracking-wider text-slate-500">Mã nhân
                                viên</label>
                            <p class="font-medium">{{ $user->employee_code ?? 'N/A' }}</p>
                        </div>
                        <hr class="border-slate-100 dark:border-slate-800" />
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-semibold uppercase tracking-wider text-slate-500">Email công
                                việc</label>
                            <p class="font-medium">{{ $user->email }}</p>
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-semibold uppercase tracking-wider text-slate-500">Số điện
                                thoại</label>
                            <p class="font-medium">{{ $user->phone ?? 'N/A' }}</p>
                        </div>
                        <hr class="border-slate-100 dark:border-slate-800" />
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-semibold uppercase tracking-wider text-slate-500">Trạng
                                thái</label>
                            <div class="flex">
                                @if ($user->status->value === 'active')
                                    <span
                                        class="flex items-center gap-1 rounded-full border border-green-200 bg-green-100 px-2.5 py-0.5 text-xs font-bold text-green-700">
                                        <span class="h-1.5 w-1.5 rounded-full bg-green-500"></span>
                                        Đang làm việc
                                    </span>
                                @else
                                    <span
                                        class="flex items-center gap-1 rounded-full border border-slate-200 bg-slate-100 px-2.5 py-0.5 text-xs font-bold text-slate-700">
                                        <span class="h-1.5 w-1.5 rounded-full bg-slate-500"></span>
                                        {{ $user->status->label() }}
                                    </span>
                                @endif
                            </div>
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-semibold uppercase tracking-wider text-slate-500">Telegram
                                ID</label>
                            <p class="text-primary font-medium">
                                {{ $user->telegram_id ? '@' . $user->telegram_id : 'N/A' }}</p>
                        </div>

                        @if (auth()->id() === $user->id)
                            <div class="flex flex-col gap-1">
                                <label class="text-xs font-semibold uppercase tracking-wider text-slate-500">Mật
                                    khẩu</label>
                                <div
                                    class="flex items-center justify-between rounded bg-slate-50 p-2 dark:bg-slate-800/50">
                                    <p class="text-slate-400">••••••••••••</p>
                                    <span
                                        class="material-symbols-outlined hover:text-primary cursor-pointer text-slate-400 transition-colors">visibility</span>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
            <!-- Projects and Tasks Section -->
            <div class="space-y-6 lg:col-span-2">
                <!-- Current Projects -->
                <div
                    class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="mb-6 flex items-center justify-between">
                        <h3 class="flex items-center gap-2 text-lg font-bold">
                            <span class="material-symbols-outlined text-primary">account_tree</span>
                            Dự án đang tham gia
                        </h3>
                        <a class="text-primary text-sm font-semibold hover:underline"
                            href="{{ route('projects.index', ['tab' => 'mine']) }}" wire:navigate>Xem tất cả</a>
                    </div>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        @forelse($this->projects as $project)
                            <div
                                class="hover:border-primary/50 rounded-xl border border-slate-200 p-4 transition-all dark:border-slate-800">
                                <div class="mb-3 flex items-start justify-between">
                                    <div class="bg-primary/10 text-primary rounded-lg p-2">
                                        <span class="material-symbols-outlined">rocket_launch</span>
                                    </div>
                                    @if ($project->leaders->contains($user->id))
                                        <span
                                            class="rounded bg-blue-100 px-2 py-1 text-[10px] font-bold uppercase text-blue-700">Lead</span>
                                    @else
                                        <span
                                            class="rounded bg-slate-100 px-2 py-1 text-[10px] font-bold uppercase text-slate-600">Member</span>
                                    @endif
                                </div>
                                <h4 class="mb-1 truncate font-bold">{{ $project->name }}</h4>
                                <p class="mb-4 line-clamp-2 text-sm text-slate-500 dark:text-slate-400">
                                    {{ $project->objective }}</p>
                                <div class="h-1.5 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                    <div class="bg-primary h-full rounded-full"
                                        style="width: {{ $project->progress }}%"></div>
                                </div>
                                <div class="mt-2 flex justify-between text-[11px] font-bold uppercase text-slate-400">
                                    <span>Tiến độ</span>
                                    <span class="text-slate-900 dark:text-slate-100">{{ $project->progress }}%</span>
                                </div>
                            </div>
                        @empty
                            <div class="col-span-2 py-8 text-center text-slate-500">
                                Chưa tham gia dự án nào
                            </div>
                        @endforelse
                    </div>
                </div>
                <!-- Recent Tasks -->
                <div
                    class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-center justify-between border-b border-slate-100 p-6 dark:border-slate-800">
                        <h3 class="flex items-center gap-2 text-lg font-bold">
                            <span class="material-symbols-outlined text-primary">task_alt</span>
                            Công việc gần đây
                        </h3>
                    </div>
                    <div class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse($this->recentTasks as $task)
                            @if ($task->phase && $task->phase->project)
                                <a href="{{ route('projects.phases.tasks.index', ['project' => $task->phase->project, 'phase' => $task->phase, 'task' => $task->id]) }}"
                                    wire:navigate
                                    class="flex cursor-pointer items-center gap-4 p-4 transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                @else
                                    <div
                                        class="flex items-center gap-4 p-4 transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/50">
                            @endif
                            <div
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800">
                                @if ($task->status->value === 'completed')
                                    <span class="material-symbols-outlined text-green-600">check_circle</span>
                                @elseif($task->status->value === 'pending')
                                    <span class="material-symbols-outlined text-slate-500">article</span>
                                @else
                                    <span class="material-symbols-outlined text-blue-500">timelapse</span>
                                @endif
                            </div>
                            <div class="min-w-0 flex-1">
                                <h5 class="truncate text-sm font-semibold">{{ $task->name }}</h5>
                                <p class="truncate text-xs text-slate-500">
                                    {{ $task->phase?->project?->name ?? 'Unknown Project' }}</p>
                            </div>
                            <div class="flex flex-col items-end gap-1">
                                <span class="rounded-full border px-2 py-0.5 text-[10px] font-bold"
                                    style="background-color: {{ $task->status->color() }}10; color: {{ $task->status->color() }}; border-color: {{ $task->status->color() }}30;">
                                    {{ $task->status->label() }}
                                </span>
                                @if ($task->status->value === 'completed')
                                    <span class="text-[10px] font-medium text-slate-400">Xong:
                                        {{ $task->completed_at ? $task->completed_at->format('d/m/Y') : 'N/A' }}</span>
                                @else
                                    <span class="text-[10px] font-medium text-slate-400">Hạn:
                                        {{ $task->deadline ? $task->deadline->format('d/m/Y') : 'N/A' }}</span>
                                @endif
                            </div>
                            @if ($task->phase && $task->phase->project)
                                </a>
                            @else
                    </div>
                    @endif
                @empty
                    <div class="p-8 text-center text-slate-500">
                        Chưa có công việc nào gần đây
                    </div>
                    @endforelse
                </div>
            </div>
        </div>
</div>
</main>

{{-- Edit User Modal --}}
<x-ui.modal wire:model="showEditModal" maxWidth="lg">
    <x-slot name="header">
        <h3 class="text-lg font-bold">Chỉnh sửa thông tin</h3>
    </x-slot>

    <form wire:submit="saveUser" class="space-y-4">
        <div>
            <label class="mb-2 block text-sm font-semibold text-slate-700 dark:text-slate-300">Ảnh đại diện</label>
            <div class="flex items-center gap-6" x-data="{ isHovering: false }">
                <div class="group relative">
                    <div
                        class="h-20 w-20 overflow-hidden rounded-full border-2 border-slate-200 shadow-sm dark:border-slate-700">
                        @if ($newAvatar)
                            <img src="{{ $newAvatar->temporaryUrl() }}" class="h-full w-full object-cover" />
                        @else
                            <img src="{{ $user->avatar_url }}" class="h-full w-full object-cover" />
                        @endif
                    </div>
                    <label
                        class="absolute inset-0 flex cursor-pointer items-center justify-center rounded-full bg-black/40 opacity-0 transition-opacity group-hover:opacity-100"
                        for="avatar-upload">
                        <span class="material-symbols-outlined text-white">photo_camera</span>
                    </label>
                </div>

                <div class="flex flex-col gap-1">
                    <div class="flex items-center gap-3">
                        <label for="avatar-upload"
                            class="cursor-pointer rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700">
                            Chọn ảnh mới
                        </label>
                        @if ($newAvatar)
                            <button type="button" wire:click="$set('newAvatar', null)"
                                class="text-xs font-medium text-red-500 hover:underline">
                                Hủy bỏ
                            </button>
                        @endif
                    </div>
                    <p class="text-[10px] text-slate-500 dark:text-slate-400">
                        Hỗ trợ JPG, PNG hoặc GIF. Tối đa 2MB.
                    </p>
                </div>

                <input id="avatar-upload" type="file" wire:model="newAvatar" accept="image/*" class="hidden" />
            </div>
            @error('newAvatar')
                <span class="mt-1 block text-xs text-red-500">{{ $message }}</span>
            @enderror
        </div>

        <x-ui.input label="Họ và tên" name="editName" wire:model="editName" required />

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <x-ui.input label="Chức danh" name="editJobTitle" wire:model="editJobTitle" />
            <div>
                <label class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-300">Phòng ban</label>
                <select wire:model="editDepartmentId"
                    class="focus:border-primary focus:ring-primary w-full rounded-lg border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
                    <option value="">-- Chọn phòng ban --</option>
                    @foreach ($departmentOptions as $dept)
                        <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                    @endforeach
                </select>
                @error('editDepartmentId')
                    <span class="text-xs text-red-500">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <x-ui.input label="Email" name="editEmail" type="email" wire:model="editEmail" required />

        <x-ui.input label="Số điện thoại" name="editPhone" wire:model="editPhone" />

        <x-ui.input label="Telegram ID" name="editTelegramId" wire:model="editTelegramId" />
        <p class="text-xs text-slate-600">Cách lấy id telegram: 
            <a class="text-blue-500 hover:underline" href="https://web.telegram.org/a/#8053328462" target="_blank">Nhấn vào link và gửi 1 tin nhắn cho bot</a> ->
            <a wire:click.stop="getTelegramId"  class="text-blue-500 hover:underline cursor-pointer">Nhấn vào đây để lấy ID Telegram</a>
        </p>

        <div class="flex justify-end gap-3 pt-4">
            <button type="button" wire:click="$set('showEditModal', false)"
                class="rounded-lg bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-300 dark:hover:bg-slate-600">Hủy</button>
            <button type="submit"
                class="bg-primary hover:bg-primary/90 rounded-lg px-4 py-2 text-sm font-semibold text-white">Lưu thay
                đổi</button>
        </div>
    </form>
</x-ui.modal>

{{-- Change Password Modal --}}
<x-ui.modal wire:model="showPasswordModal" maxWidth="md">
    <x-slot name="header">
        <h3 class="text-lg font-bold">Đổi mật khẩu</h3>
    </x-slot>

    <form wire:submit="changePassword" class="space-y-4">
        <x-ui.input label="Mật khẩu hiện tại" name="currentPassword" type="password" wire:model="currentPassword"
            required />

        <x-ui.input label="Mật khẩu mới" name="newPassword" type="password" wire:model="newPassword" required />

        <x-ui.input label="Xác nhận mật khẩu mới" name="newPassword_confirmation" type="password"
            wire:model="newPassword_confirmation" required />

        <div class="flex justify-end gap-3 pt-4">
            <button type="button" wire:click="$set('showPasswordModal', false)"
                class="rounded-lg bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-300 dark:hover:bg-slate-600">Hủy</button>
            <button type="submit"
                class="bg-primary hover:bg-primary/90 rounded-lg px-4 py-2 text-sm font-semibold text-white">Đổi mật
                khẩu</button>
        </div>
    </form>
</x-ui.modal>
</div>
