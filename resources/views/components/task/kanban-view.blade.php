<?php

use App\Enums\TaskStatus;
use App\Enums\ApprovalAction;
use App\Models\Task;
use App\Models\User;
use App\Services\Tasks\TaskQueryService;
use App\Services\Tasks\TaskService;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    public int $projectId;

    public int $phaseId;

    /** @var array<string, \Illuminate\Support\Collection> */
    public array $columns = [];

    public bool $showCompletionRatingModal = false;

    public bool $showRejectReasonModal = false;

    public ?int $pendingTaskId = null;

    public ?string $pendingStatus = null;

    public ?int $pendingStarRating = null;

    public string $pendingApprovalComment = '';

    public string $pendingTaskName = '';

    public ?int $pendingRejectTaskId = null;

    public string $pendingRejectComment = '';

    public string $pendingRejectTaskName = '';

    public function mount(int $projectId, int $phaseId): void
    {
        $this->projectId = $projectId;
        $this->phaseId = $phaseId;
        $this->loadTasks();
    }

    public function loadTasks(): void
    {
        $queryService = app(TaskQueryService::class);
        $tasks = $queryService
            ->taskScopeForActor(auth()->user(), $this->projectId)
            ->where('phase_id', $this->phaseId)
            ->with([
                'pic',
                'coPics',
                'dependencyTask:id,name,status',
                'approvalLogs' => function ($query): void {
                    $query
                        ->whereIn('action', [ApprovalAction::Approved->value, ApprovalAction::Rejected->value])
                        ->with('reviewer:id,name')
                        ->orderByDesc('id')
                        ->limit(5);
                },
            ])
            ->get();

        $this->columns = collect(TaskStatus::cases())
            ->mapWithKeys(
                fn($status) => [
                    $status->value => $tasks->filter(fn($t) => $t->status === $status->value || ($t->status instanceof TaskStatus && $t->status->value === $status->value))->values(),
                ],
            )
            ->all();
    }

    /** Called via Alpine/x-sort when user drops a card into a new column */
    public function moveTask(int $taskId, string $newStatus): void
    {
        // dd($taskId);
        try {
            $task = Task::findOrFail($taskId);
            $taskService = app(TaskService::class);

            if ($this->shouldPromptRejectionReason($task, $newStatus)) {
                $this->pendingRejectTaskId = $task->id;
                $this->pendingRejectTaskName = $task->name;
                $this->pendingRejectComment = '';
                $this->showRejectReasonModal = true;
                $this->resetErrorBag('pendingRejectComment');
                $this->loadTasks();

                return;
            }

            if ($this->shouldPromptCompletionRating($task, $newStatus)) {
                $this->pendingTaskId = $task->id;
                $this->pendingStatus = $newStatus;
                $this->pendingTaskName = $task->name;
                $this->pendingStarRating = null;
                $this->pendingApprovalComment = '';
                $this->showCompletionRatingModal = true;
                $this->loadTasks();

                return;
            }

            $taskService->update(auth()->user(), $task, [
                'status' => $newStatus,
            ]);

            $this->loadTasks();
            $this->dispatch('toast', message: 'Đã cập nhật trạng thái công việc', type: 'success');
            $this->dispatch('task-updated', taskId: $taskId);
        } catch (ValidationException $e) {
            $firstError = collect($e->errors())->flatten()->first();
            $this->dispatch('toast', message: (string) ($firstError ?? $e->getMessage()), type: 'error');
            $this->loadTasks();
        } catch (\Exception $e) {
            $this->dispatch('toast', message: 'Lỗi: ' . $e->getMessage(), type: 'error');
            $this->loadTasks();
        }
    }

    public function setPendingStarRating(int $rating): void
    {
        $this->pendingStarRating = max(1, min(5, $rating));
        $this->resetErrorBag('pendingStarRating');
    }

    public function closeCompletionRatingModal(): void
    {
        $this->showCompletionRatingModal = false;
        $this->pendingTaskId = null;
        $this->pendingStatus = null;
        $this->pendingStarRating = null;
        $this->pendingApprovalComment = '';
        $this->pendingTaskName = '';
        $this->loadTasks();
    }

    public function closeRejectReasonModal(): void
    {
        $this->showRejectReasonModal = false;
        $this->pendingRejectTaskId = null;
        $this->pendingRejectTaskName = '';
        $this->pendingRejectComment = '';
        $this->resetErrorBag('pendingRejectComment');
        $this->loadTasks();
    }

    public function completionModalTaskName(): string
    {
        if ($this->pendingTaskName !== '') {
            return $this->pendingTaskName;
        }

        if ($this->pendingTaskId === null) {
            return '';
        }

        foreach ($this->columns as $tasks) {
            $matchedTask = $tasks->firstWhere('id', $this->pendingTaskId);
            if ($matchedTask !== null) {
                return (string) $matchedTask->name;
            }
        }

        return (string) (Task::query()->whereKey($this->pendingTaskId)->value('name') ?? '');
    }

    public function rejectModalTaskName(): string
    {
        if ($this->pendingRejectTaskName !== '') {
            return $this->pendingRejectTaskName;
        }

        if ($this->pendingRejectTaskId === null) {
            return '';
        }

        foreach ($this->columns as $tasks) {
            $matchedTask = $tasks->firstWhere('id', $this->pendingRejectTaskId);
            if ($matchedTask !== null) {
                return (string) $matchedTask->name;
            }
        }

        return (string) (Task::query()->whereKey($this->pendingRejectTaskId)->value('name') ?? '');
    }

    public function confirmCompletionWithRating(): void
    {
        if ($this->pendingTaskId === null || $this->pendingStatus === null) {
            $this->closeCompletionRatingModal();

            return;
        }

        if ($this->pendingStarRating === null) {
            $this->addError('pendingStarRating', 'Vui lòng chọn điểm đánh giá từ 1 đến 5 sao.');

            return;
        }

        try {
            $task = Task::findOrFail($this->pendingTaskId);

            app(TaskService::class)->update(auth()->user(), $task, [
                'status' => $this->pendingStatus,
                'star_rating' => $this->pendingStarRating,
                'approval_comment' => $this->pendingApprovalComment !== '' ? $this->pendingApprovalComment : null,
            ]);

            $updatedTaskId = $task->id;

            $this->closeCompletionRatingModal();
            $this->dispatch('toast', message: 'Đã cập nhật trạng thái công việc', type: 'success');
            $this->dispatch('task-updated', taskId: $updatedTaskId);
        } catch (ValidationException $e) {
            $firstError = collect($e->errors())->flatten()->first();
            $this->dispatch('toast', message: (string) ($firstError ?? $e->getMessage()), type: 'error');
            $this->loadTasks();
        } catch (\Exception $e) {
            $this->dispatch('toast', message: 'Lỗi: ' . $e->getMessage(), type: 'error');
            $this->loadTasks();
        }
    }

    public function confirmRejectWithReason(): void
    {
        if ($this->pendingRejectTaskId === null) {
            $this->closeRejectReasonModal();

            return;
        }

        $comment = trim($this->pendingRejectComment);
        if ($comment === '') {
            $this->addError('pendingRejectComment', 'Vui lòng nhập lý do không đạt.');

            return;
        }

        try {
            $task = Task::findOrFail($this->pendingRejectTaskId);
            app(TaskService::class)->reject(auth()->user(), $task, $comment);

            $updatedTaskId = $task->id;
            $this->closeRejectReasonModal();
            $this->dispatch('toast', message: 'Đã từ chối duyệt và chuyển task về Đang thực hiện.', type: 'warning');
            $this->dispatch('task-updated', taskId: $updatedTaskId);
        } catch (ValidationException $e) {
            $firstError = collect($e->errors())->flatten()->first();
            $this->addError('pendingRejectComment', (string) ($firstError ?? $e->getMessage()));
        } catch (\Exception $e) {
            $this->addError('pendingRejectComment', 'Lỗi: ' . $e->getMessage());
        }
    }

    private function shouldPromptCompletionRating(Task $task, string $newStatus): bool
    {
        $actor = auth()->user();
        $currentStatus = $task->status instanceof \BackedEnum ? (string) $task->status->value : (string) $task->status;
        $targetStatus = (string) $newStatus;
        $workflowType = $task->workflow_type instanceof \BackedEnum ? (string) $task->workflow_type->value : (string) $task->workflow_type;

        if ($currentStatus !== TaskStatus::WaitingApproval->value || $targetStatus !== TaskStatus::Completed->value) {
            return false;
        }

        return $this->canApproveCompletionByWorkflow($actor, $workflowType);
    }

    private function shouldPromptRejectionReason(Task $task, string $newStatus): bool
    {
        $actor = auth()->user();
        $currentStatus = $task->status instanceof \BackedEnum ? (string) $task->status->value : (string) $task->status;
        $targetStatus = (string) $newStatus;
        $workflowType = $task->workflow_type instanceof \BackedEnum ? (string) $task->workflow_type->value : (string) $task->workflow_type;

        if ($currentStatus !== TaskStatus::WaitingApproval->value || $targetStatus !== TaskStatus::InProgress->value) {
            return false;
        }

        return $this->canApproveCompletionByWorkflow($actor, $workflowType);
    }

    private function canApproveCompletionByWorkflow(?User $actor, string $workflowType): bool
    {
        if ($actor === null) {
            return false;
        }

        if ($actor->hasRole('super_admin')) {
            return true;
        }

        if ($workflowType === 'single') {
            return $actor->hasRole('leader');
        }

        if ($workflowType === 'double') {
            return $actor->hasAnyRole(['leader', 'ceo']);
        }

        return false;
    }

    #[On('task-saved')]
    #[On('task-updated')]
    #[On('task-deleted')]
    public function refresh(): void
    {
        $this->loadTasks();
    }

    public function startTask(int $taskId): void
    {
        try {
            $task = Task::findOrFail($taskId);
            app(TaskService::class)->start(auth()->user(), $task);
            $this->loadTasks();
            $this->dispatch('toast', message: 'Công việc đã bắt đầu!', type: 'success');
            $this->dispatch('task-updated', taskId: $taskId);
        } catch (\Exception $e) {
            $this->dispatch('toast', message: 'Lỗi: Chỉ có người được giao hoặc người hỗ trợ mới có thể bắt đầu công việc', type: 'error');
        }
    }
};
?>

<div class="w-full">
    <style>
        .sortable-ghost {
            opacity: 0.4 !important;
            background: #e0e7ff !important;
            border: 2px dashed #1337ec !important;
            border-radius: 0.75rem !important;
        }

        .sortable-drag {
            opacity: 1 !important;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.18) !important;
            transform: rotate(1.5deg) !important;
        }
    </style>

    <div class="flex w-full items-start gap-4 overflow-x-auto pb-6">
        @foreach (TaskStatus::cases() as $status)
            @php
                $tasks = collect($columns[$status->value] ?? []);
                $count = $tasks->count();
            @endphp

            {{-- ===== COLUMN ===== --}}
            <div class="kanban-column w-70 flex shrink-0 flex-col gap-2">
                {{-- Column header --}}
                <div class="mb-1 flex items-center gap-2 px-1">
                    <span class="{{ $status->dotClass() }} h-2.5 w-2.5 rounded-full"></span>
                    <h3 class="text-sm font-bold text-slate-800 dark:text-slate-200">{{ $status->label() }}</h3>
                    <span
                        class="{{ $status->badgeClass() }} text-2xs rounded-full px-2 py-0.5 font-bold">{{ $count }}</span>
                </div>

                {{-- ===== DROP ZONE (x-sort group) ===== --}}
                <div x-sort="(taskId) => $wire.moveTask(taskId, '{{ $status->value }}')" x-sort:group="kanban"
                    x-sort:config="{ animation: 200, filter: '.no-drag', preventOnFilter: false }"
                    class="animate-stagger -m-1 flex min-h-20 flex-col gap-3 rounded-xl p-1 transition-colors">
                    @forelse ($tasks as $task)
                        @php
                            $priority =
                                $task->priority instanceof App\Enums\TaskPriority
                                    ? $task->priority
                                    : App\Enums\TaskPriority::from($task->priority);
                            $isDone = $status === TaskStatus::Completed;
                            $canDrag = auth()->user()->can('update', $task);
                            $canViewTask = auth()->user()->can('view', $task);

                            // Dependency block: task has a dependency that is NOT completed
                            $depTask = $task->dependencyTask;
                            $depStatus =
                                $depTask?->status instanceof \BackedEnum ? $depTask->status->value : $depTask?->status;
                            $hasDependencyBlock = $depTask !== null && $depStatus !== 'completed';
                            if ($hasDependencyBlock) {
                                $canDrag = false;
                            }

                            $isNearDeadline = $task->deadline && !$isDone && $task->deadline->lte(now()->addDays(3));
                            $recentApprovalLogs = $task->approvalLogs->take(1);
                        @endphp

                        {{-- ===== CARD ===== --}}
                        <div x-sort:item="{{ $task->id }}" wire:key="task-card-{{ $task->id }}"
                            x-data="{ _startX: 0, _startY: 0, _dragged: false }"
                            @pointerdown="_startX = $event.clientX; _startY = $event.clientY; _dragged = false"
                            @pointermove="if (!_dragged && (Math.abs($event.clientX - _startX) > 5 || Math.abs($event.clientY - _startY) > 5)) _dragged = true"
                            @if ($canViewTask)
                            @click="if (!_dragged) $dispatch('task-edit-requested', { taskId: {{ $task->id }} })"
                    @endif
                    class="animate-enter {{ $status->borderClass() ?: 'border-l-slate-200 dark:border-l-slate-700' }} {{ $canDrag ? 'cursor-grab active:cursor-grabbing' : ($canViewTask ? 'cursor-pointer' : 'cursor-default') }} {{ !$canDrag ? 'no-drag' : '' }} {{ $isDone ? 'opacity-70' : '' }} {{ $hasDependencyBlock ? 'opacity-50 grayscale-30' : '' }} {{ $isNearDeadline ? 'bg-amber-50 dark:bg-amber-900/20 border-amber-200 dark:border-amber-800/50' : 'bg-white dark:bg-slate-900' }} group relative z-0 select-none rounded-xl border border-l-4 border-slate-200 p-3 shadow-sm transition-all hover:z-30 hover:shadow-md dark:border-slate-800">
                    <div class="flex flex-col gap-2.5">
                        {{-- Priority badge + drag icon --}}
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-1.5">
                                <x-ui.badge :color="match ($priority) {
                                    App\Enums\TaskPriority::Urgent => 'red',
                                    App\Enums\TaskPriority::High => 'orange',
                                    App\Enums\TaskPriority::Medium => 'amber',
                                    default => 'blue',
                                }" size="xs" class="font-bold tracking-tight">
                                    {{ $priority->label() }}
                                </x-ui.badge>
                                {{-- Nếu là cấp 2 cấp duyệt thì đánh dấu --}}
                                @if (($task->workflow_type->value ?? $task->workflow_type) === 'double')
                                    <x-ui.badge color="blue" size="xs" class="font-bold tracking-tight">
                                        PD 2 cấp
                                    </x-ui.badge>
                                @endif
                            </div>
                            <div class="flex items-center gap-1.5">
                                @if ($status === TaskStatus::Pending && !$hasDependencyBlock)
                                    <button wire:click.stop="startTask({{ $task->id }})"
                                        class="text-primary hover:bg-primary/10 flex size-6 items-center justify-center rounded-full transition-all"
                                        title="Bắt đầu ngay">
                                        <span class="material-symbols-outlined text-[18px]">play_arrow</span>
                                    </button>
                                @endif

                                @if ($canDrag)
                                    <span
                                        class="material-symbols-outlined text-md cursor-grab text-slate-300 transition-colors active:cursor-grabbing group-hover:text-slate-400">
                                        drag_indicator
                                    </span>
                                @endif
                            </div>
                        </div>


                        {{-- Title & Description --}}
                        <div class="space-y-1">
                            <h4
                                class="{{ $isDone ? 'line-through text-slate-400 font-medium' : 'text-slate-900 dark:text-slate-100 font-bold' }} line-clamp-2 text-[13px] leading-snug">
                                {{ $task->name }}
                            </h4>
                            @if ($task->description)
                                <p class="line-clamp-1 text-[11px] leading-relaxed text-slate-400">
                                    {{ $task->description }}
                                </p>
                            @endif
                        </div>
                        {{-- Dependency block warning --}}
                        @if ($hasDependencyBlock)
                            <div
                                class="flex items-center gap-2 rounded-lg bg-amber-50/50 px-2.5 py-1.5 ring-1 ring-amber-100/50 dark:bg-amber-900/10 dark:ring-amber-800/30">
                                <span class="material-symbols-outlined text-sm text-amber-500">lock</span>
                                <p class="text-[10px] font-medium leading-tight text-amber-700 dark:text-amber-400">
                                    Chặn bởi: <span class="font-bold">{{ $depTask->name }}</span>
                                </p>
                            </div>
                        @endif


                        {{-- Approval Logs Section --}}
                        @if ($recentApprovalLogs->isNotEmpty())
                            <div class="flex flex-col gap-1">
                                <span class="text-[9px] font-bold uppercase tracking-wider text-slate-400">Đánh
                                    giá:</span>
                                <div class="flex flex-row justify-between space-x-1 overflow-visible">
                                    {{-- @dd($recentApprovalLogs) --}}
                                    @foreach ($recentApprovalLogs as $approvalLog)
                                        @php
                                            $approvalLevelLabel = match ($approvalLog->approval_level) {
                                                'leader' => 'LD',
                                                'ceo' => 'CEO',
                                                default => '??',
                                            };
                                            $isApproved = $approvalLog->action === ApprovalAction::Approved->value;

                                        @endphp

                                        <div class="flex flex-col gap-0.5 overflow-visible">
                                            <div class="flex items-center justify-between gap-2 overflow-visible">
                                                <div class="flex items-center gap-1.5 overflow-visible">
                                                    {{-- Reviewer Avatar with Premium Tooltip --}}
                                                    <x-ui.avatar :user="$approvalLog->reviewer" :label="$approvalLevelLabel" size="6" />

                                                    <div class="flex flex-col items-center gap-1">
                                                        @if ($approvalLog->star_rating !== null)
                                                            <div class="flex shrink-0 items-center gap-0.5">
                                                                <x-ui.star-rating :rating="$approvalLog->star_rating" size="3" />
                                                            </div>
                                                        @endif
                                                        @if (!$isApproved && $approvalLog->comment)
                                                            <div class="group/comment relative">
                                                                <p class="truncate text-[10px] font-bold italic text-red-400 transition-colors hover:text-red-600"
                                                                    title="{{ $approvalLog->comment }}">
                                                                    “{{ $approvalLog->comment }}”
                                                                </p>
                                                            </div>
                                                        @endif
                                                    </div>

                                                </div>


                                            </div>


                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif


                        {{-- Contextual Info --}}
                        <div class="flex items-center justify-between pt-1">
                            <div class="flex items-center gap-1.5">
                                <span class="text-[9px] font-bold uppercase tracking-wider text-slate-400">Thành
                                    viên:</span>
                                <div class="flex items-center -space-x-1.5">
                                    <x-ui.avatar-stack :users="collect([$task->pic])
                                        ->concat($task->coPics)
                                        ->filter()" :max="3" :size="6" />
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                @if ($task->deadline)
                                    @php $overdue = $task->deadline->isPast() && !$isDone; @endphp
                                    <div class="flex flex-col items-end">
                                        <span
                                            class="{{ $overdue ? 'text-red-500 font-bold' : 'text-slate-400' }} flex items-center gap-1 text-[10px]">
                                            <span
                                                class="material-symbols-outlined {{ $overdue ? 'animate-pulse' : '' }} text-[14px]">calendar_clock</span>
                                            {{ $task->deadline->format('d/m') }}
                                        </span>
                                    </div>
                                @endif

                                @if ($task->completed_at && $isDone)
                                    <span
                                        class="flex items-center gap-0.5 rounded-md bg-emerald-50 px-1.5 py-0.5 text-[10px] font-bold text-emerald-500 dark:bg-emerald-900/20">
                                        <span class="material-symbols-outlined text-[14px]">check_circle</span>
                                        {{ $task->completed_at->format('d/m') }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="flex flex-col items-center justify-center py-8 text-slate-400">
                    <span class="material-symbols-outlined mb-1 text-2xl opacity-20">inventory_2</span>
                    <span class="text-[11px] font-medium uppercase tracking-wider opacity-50">Trống</span>
                </div>
        @endforelse
    </div>

    {{-- Add task button --}}
    @can('create', App\Models\Task::class)
        @if ($status === TaskStatus::Pending)
            <button @click="$dispatch('task-create-requested')"
                class="hover:text-primary hover:border-primary mt-1 flex items-center justify-center gap-2 rounded-xl border-2 border-dashed border-slate-200 py-2 text-sm font-medium text-slate-400 transition-all dark:border-slate-800">
                <span class="material-symbols-outlined text-sm">add</span>
                <span>Thêm công việc</span>
            </button>
        @endif
    @endcan
</div>
@endforeach
</div>

<x-ui.modal wire:model="showRejectReasonModal"
    wire:key="kanban-reject-reason-modal-{{ $pendingRejectTaskId ?? 'none' }}-{{ $showRejectReasonModal ? 'open' : 'closed' }}"
    maxWidth="md">
    <x-slot name="header">
        <x-ui.form.heading icon="rule" title="Từ chối phê duyệt"
            description="Nhập lý do không đạt để chuyển task về trạng thái Đang thực hiện." />
    </x-slot>

    <div class="space-y-4">
        @php
            $rejectTaskName = $this->rejectModalTaskName();
        @endphp
        <p class="text-sm text-slate-600 dark:text-slate-300">Công việc: <span
                class="font-semibold text-slate-900 dark:text-slate-100">{{ $rejectTaskName !== '' ? $rejectTaskName : 'Chưa xác định' }}</span>
        </p>

        <div>
            <label class="label-text">Lý do không đạt <span class="text-red-500">*</span></label>
            <textarea rows="4" class="input-field" placeholder="Nhập lý do từ chối để PIC cập nhật lại công việc..."
                wire:model="pendingRejectComment"></textarea>
            @error('pendingRejectComment')
                <p class="mt-1 text-xs font-medium text-red-500">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <x-slot name="footer">
        <x-ui.button variant="secondary" wire:click="closeRejectReasonModal">Hủy</x-ui.button>
        <x-ui.button variant="danger" icon="close" wire:click="confirmRejectWithReason"
            loading="confirmRejectWithReason">Xác nhận không đạt</x-ui.button>
    </x-slot>
</x-ui.modal>

<x-ui.modal wire:model="showCompletionRatingModal"
    wire:key="kanban-completion-rating-modal-{{ $pendingTaskId ?? 'none' }}-{{ $showCompletionRatingModal ? 'open' : 'closed' }}"
    maxWidth="md">
    <x-slot name="header">
        <x-ui.form.heading icon="star_rate" title="Đánh giá hoàn thành công việc"
            description="Vui lòng chọn số sao trước khi chuyển trạng thái sang Hoàn thành." />
    </x-slot>

    <div class="space-y-4">
        @php
            $modalTaskName = $this->completionModalTaskName();
        @endphp
        <p class="text-sm text-slate-600 dark:text-slate-300">Công việc: <span
                class="font-semibold text-slate-900 dark:text-slate-100">{{ $modalTaskName !== '' ? $modalTaskName : 'Chưa xác định' }}</span>
        </p>

        <div class="flex items-center justify-center gap-1">
            @for ($i = 1; $i <= 5; $i++)
                <button type="button" wire:click="setPendingStarRating({{ $i }})"
                    class="transition-transform hover:scale-110">
                    <span
                        class="material-symbols-outlined {{ $pendingStarRating !== null && $i <= $pendingStarRating ? 'text-amber-400' : 'text-slate-300 dark:text-slate-600' }} text-4xl">star</span>
                </button>
            @endfor
        </div>

        @error('pendingStarRating')
            <p class="text-center text-xs font-medium text-red-500">{{ $message }}</p>
        @enderror

        <div>
            <label class="label-text">Nhận xét (tùy chọn)</label>
            <textarea rows="3" class="input-field" placeholder="Nhập nhận xét đánh giá..."
                wire:model="pendingApprovalComment"></textarea>
        </div>
    </div>

    <x-slot name="footer">
        <x-ui.button variant="secondary" wire:click="closeCompletionRatingModal">Hủy</x-ui.button>
        <x-ui.button variant="primary" icon="check" wire:click="confirmCompletionWithRating"
            loading="confirmCompletionWithRating">Xác nhận hoàn thành</x-ui.button>
    </x-slot>
</x-ui.modal>
</div>
