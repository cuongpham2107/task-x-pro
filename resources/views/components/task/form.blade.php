<?php

use App\Models\Phase;
use App\Models\Project;
use App\Models\Task;
use App\Services\Tasks\TaskCommentService;
use App\Services\Tasks\TaskService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    use WithFileUploads;

    public ?Project $project = null;

    public ?Phase $phase = null;

    public ?int $project_id = null;

    #[Validate('required|integer|exists:phases,id')]
    public ?int $phase_id = null;

    public bool $showFormModal = false;

    public string $mode = 'create';

    public ?int $editing_task_id = null;

    public bool $hasDependencyBlock = false;

    public ?string $dependencyTaskName = null;

    public bool $showCompletionRatingModal = false;

    public bool $showRejectReasonModal = false;

    public ?int $completionStarRating = null;

    public string $completionApprovalComment = '';

    public string $completionTaskName = '';

    public string $rejectReason = '';

    public string $original_status = 'pending';

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('required|string')]
    public string $type = 'technical';

    #[Validate('required|string')]
    public string $status = 'pending';

    #[Validate('required|string')]
    public string $priority = 'medium';

    #[Validate('required|string')]
    public string $workflow_type = 'single';

    #[Validate('nullable|date')]
    public string $deadline = '';

    #[Validate('nullable|string|max:5000')]
    public string $description = '';

    #[Validate('nullable|string|max:5000')]
    public string $issue_note = '';

    #[Validate('nullable|string|max:5000')]
    public string $recommendation = '';

    #[Validate('nullable|url|max:255')]
    public string $deliverable_url = '';

    #[Validate('required|integer|min:0|max:100')]
    public int $progress = 0;

    #[Validate('required|integer|exists:users,id')]
    public ?int $pic_id = null;

    #[Validate('nullable|integer|exists:tasks,id')]
    public ?int $dependency_task_id = null;

    #[Validate('nullable|string|max:5000')]
    public string $newComment = '';

    public array $co_pic_ids = [];

    /** @var array<int, \Illuminate\Http\UploadedFile> */
    public $files = [];

    public string $activeTab = 'general';

    public Collection $existing_attachments;

    public Collection $taskComments;

    // Form options
    public array $taskTypeLabels = [];

    public array $taskStatusLabels = [];

    public array $taskPriorityLabels = [];

    public array $workflowTypeLabels = [];

    public Collection $projectOptions;

    public Collection $phaseOptions;

    public Collection $picOptions;

    public Collection $dependencyTaskOptions;

    public function mount(?Project $project = null, ?Phase $phase = null): void
    {
        $this->project = $project;
        $this->phase = $phase;
        $this->project_id = $project?->id;
        $this->phase_id = $phase?->id;
        $this->existing_attachments = collect();
        $this->taskComments = collect();
        $this->dependencyTaskOptions = collect();
        $this->loadFormOptions();
    }

    private function loadFormOptions(): void
    {
        $projectId = $this->project_id ?? $this->project?->id;
        $options = app(TaskService::class)->formOptions(
            Auth::user(),
            $projectId !== null ? (int) $projectId : null,
        );
        $this->taskTypeLabels = $options['task_type_labels'];
        $this->taskStatusLabels = $options['task_status_labels'];
        $this->taskPriorityLabels = $options['task_priority_labels'];
        $this->workflowTypeLabels = $options['workflow_type_labels'];
        $this->projectOptions = $options['projects'];
        $this->phaseOptions = $options['phases'];
        $this->picOptions = $options['pics'];
        $this->loadDependencyTaskOptions();
    }

    private function loadDependencyTaskOptions(): void
    {
        $phaseId = $this->phase_id ?? $this->phase?->id;

        if ($phaseId === null) {
            $this->dependencyTaskOptions = collect();

            return;
        }

        $this->dependencyTaskOptions = Task::query()
            ->where('phase_id', $phaseId)
            ->when($this->editing_task_id, fn($q) => $q->where('id', '!=', $this->editing_task_id))
            ->select(['id', 'name', 'status'])
            ->orderBy('name')
            ->get();
    }

    public function updatedProjectId(): void
    {
        $this->phase_id = null;
        $this->dependency_task_id = null;
        $this->loadFormOptions();
    }

    public function updatedPhaseId(): void
    {
        $this->dependency_task_id = null;
        $this->loadDependencyTaskOptions();
    }

    #[Computed]
    public function isPhaseScoped(): bool
    {
        return $this->project?->id !== null && $this->phase?->id !== null;
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function projectSelectOptions(): array
    {
        return $this->projectOptions
            ->mapWithKeys(fn($project): array => [(string) $project->id => $project->name])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function phaseSelectOptions(): array
    {
        $projectId = $this->project_id ?? $this->project?->id;
        $phases = $this->phaseOptions;

        if ($projectId !== null) {
            $phases = $phases->where('project_id', (int) $projectId);
        }

        return $phases
            ->mapWithKeys(fn($phase): array => [(string) $phase->id => $phase->name])
            ->all();
    }

    #[On('task-create-requested')]
    public function showCreateFormModal(): void
    {
        Gate::forUser(auth()->user())->authorize('create', Task::class);

        $this->resetFormModal();
        $this->mode = 'create';
        $this->editing_task_id = null;
        $this->project_id = $this->project?->id;
        $this->phase_id = $this->phase?->id;
        $this->loadFormOptions();
        $this->loadDependencyTaskOptions();
        $this->showFormModal = true;
    }

    #[On('task-edit-requested')]
    public function showEditFormModal(int $taskId): void
    {
        $this->resetFormModal();

        $task = app(TaskService::class)->findForEdit(auth()->user(), $taskId);

        Gate::forUser(auth()->user())->authorize('update', $task);

        $this->mode = 'edit';
        $this->editing_task_id = $task->id;
        $this->name = (string) $task->name;
        $this->completionTaskName = (string) $task->name;
        $this->type = $task->type instanceof \BackedEnum ? (string) $task->type->value : (string) $task->type;
        $this->status = $task->status instanceof \BackedEnum ? (string) $task->status->value : (string) $task->status;
        $this->original_status = $this->status;
        $this->priority = $task->priority instanceof \BackedEnum ? (string) $task->priority->value : (string) $task->priority;
        $this->workflow_type = $task->workflow_type instanceof \BackedEnum ? (string) $task->workflow_type->value : (string) $task->workflow_type;
        $this->deadline = $task->deadline instanceof Carbon ? $task->deadline->toDateString() : '';
        $this->description = (string) ($task->description ?? '');
        $this->deliverable_url = (string) ($task->deliverable_url ?? '');
        $this->progress = (int) ($task->progress ?? 0);
        $this->issue_note = (string) ($task->issue_note ?? '');
        $this->recommendation = (string) ($task->recommendation ?? '');
        $this->pic_id = $task->pic_id;
        $this->dependency_task_id = $task->dependency_task_id;
        $this->co_pic_ids = $task->coPics->pluck('id')->all();
        $this->project_id = $task->phase?->project_id;
        $this->phase_id = $task->phase_id;
        $this->existing_attachments = $task
            ->attachments()
            ->with(['uploader:id,name', 'media'])
            ->get();
        $this->taskComments = collect($task->comments ?? [])->values();
        $this->loadFormOptions();
        $this->loadDependencyTaskOptions();
        $this->activeTab = 'general';

        // Check for dependency block
        $depTask = $task->dependencyTask;
        if ($depTask !== null) {
            $depStatus = $depTask->status instanceof \BackedEnum ? $depTask->status->value : $depTask->status;
            if ($depStatus !== 'completed') {
                $this->hasDependencyBlock = true;
                $this->dependencyTaskName = $depTask->name;
            }
        }

        $this->showFormModal = true;
    }

    public function resetFormModal(): void
    {
        $this->reset(['name', 'type', 'status', 'priority', 'workflow_type', 'deadline', 'description', 'deliverable_url', 'progress', 'issue_note', 'recommendation', 'pic_id', 'dependency_task_id', 'project_id', 'phase_id', 'newComment', 'co_pic_ids', 'editing_task_id', 'files', 'existing_attachments', 'hasDependencyBlock', 'dependencyTaskName', 'activeTab', 'showCompletionRatingModal', 'completionStarRating', 'completionApprovalComment', 'completionTaskName', 'showRejectReasonModal', 'rejectReason', 'original_status']);
        $this->existing_attachments = collect();
        $this->taskComments = collect();
        $this->activeTab = 'general';
        $this->type = 'technical';
        $this->status = 'pending';
        $this->original_status = 'pending';
        $this->priority = 'medium';
        $this->workflow_type = 'single';
        $this->mode = 'create';
        $this->project_id = $this->project?->id;
        $this->phase_id = $this->phase?->id;
        $this->resetValidation();
    }

    protected function messages(): array
    {
        return [
            'name.required' => 'Tên công việc là bắt buộc.',
            'phase_id.required' => 'Giai đoạn là bắt buộc.',
            'phase_id.exists' => 'Giai đoạn không hợp lệ.',
            'pic_id.required' => 'Người phụ trách là bắt buộc.',
        ];
    }

    public function setCompletionStarRating(int $rating): void
    {
        $this->completionStarRating = max(1, min(5, $rating));
        $this->resetErrorBag('completionStarRating');
    }

    public function closeCompletionRatingModal(): void
    {
        $this->showCompletionRatingModal = false;
        $this->completionStarRating = null;
        $this->completionApprovalComment = '';
        $this->completionTaskName = '';
        if ($this->mode === 'edit' && $this->editing_task_id !== null) {
            $this->showFormModal = true;
        }
        $this->resetErrorBag('completionStarRating');
    }

    public function openRejectReasonModal(): void
    {
        if (!$this->canApproveTask()) {
            return;
        }

        $this->rejectReason = '';
        $this->showFormModal = false;
        $this->showRejectReasonModal = true;
        $this->resetErrorBag('rejectReason');
    }

    public function closeRejectReasonModal(): void
    {
        $this->showRejectReasonModal = false;
        $this->rejectReason = '';
        if ($this->mode === 'edit' && $this->editing_task_id !== null) {
            $this->showFormModal = true;
        }
        $this->resetErrorBag('rejectReason');
    }

    public function save(): void
    {
        if ($this->isCompletedLocked()) {
            $this->dispatch('toast', message: 'Công việc đã hoàn thành, không thể chỉnh sửa.', type: 'warning');

            return;
        }

        if ($this->phase_id === null && $this->phase?->id !== null) {
            $this->phase_id = $this->phase->id;
        }
        
        

        try {
            $this->validate();
            $taskService = app(TaskService::class);
            $attributes = [
                'phase_id' => $this->phase_id,
                'name' => $this->name,
                'type' => $this->type,
                'status' => $this->status,
                'priority' => $this->priority,
                'workflow_type' => $this->workflow_type,
                'deadline' => $this->deadline ?: null,
                'description' => $this->description ?: null,
                'deliverable_url' => $this->deliverable_url ?: null,
                'progress' => $this->progress,
                'issue_note' => $this->issue_note ?: null,
                'recommendation' => $this->recommendation ?: null,
                'pic_id' => $this->pic_id,
                'dependency_task_id' => $this->dependency_task_id,
                'attachments' => $this->files,
            ];

            $savedName = $this->name;
            $overloadWarning = null;

            if ($this->mode === 'edit' && $this->editing_task_id !== null) {
                $task = Task::findOrFail($this->editing_task_id);
                $needsCompletionRating = $this->shouldPromptCompletionRating($task);

                if ($needsCompletionRating) {
                    $this->completionTaskName = trim($this->name) !== '' ? trim($this->name) : (string) $task->name;
                }

                if ($needsCompletionRating && $this->completionStarRating === null) {
                    $this->showFormModal = false;
                    $this->showCompletionRatingModal = true;
                    $this->addError('completionStarRating', 'Vui lòng chọn điểm đánh giá từ 1 đến 5 sao.');

                    return;
                }

                if ($needsCompletionRating && $this->completionStarRating !== null) {
                    $attributes['star_rating'] = $this->completionStarRating;
                    $attributes['approval_comment'] = $this->completionApprovalComment !== '' ? $this->completionApprovalComment : null;
                    $this->showCompletionRatingModal = false;
                }

                $result = $taskService->update(auth()->user(), $task, $attributes, $this->co_pic_ids);
                $overloadWarning = $result->overloadWarning;
                $toastMessage = 'Công việc đã được cập nhật!';
            } else {
                $result = $taskService->create(auth()->user(), $attributes, $this->co_pic_ids);
                $overloadWarning = $result->overloadWarning;
                $toastMessage = 'Công việc đã được thêm mới!';
            }

            // Show overload warning popup if PIC is overloaded (BR-006)
            if ($overloadWarning) {
                $this->dispatch('show-overload-warning', message: $overloadWarning);
            }

            $this->showFormModal = false;
            $this->resetFormModal();

            $this->dispatch('toast', message: $toastMessage, type: 'success');
            $this->dispatch('task-saved', taskTitle: $savedName);
        } catch (ValidationException $e) {
            $this->dispatch('toast', message: 'Lỗi: ' . $e->getMessage(), type: 'error');
            throw $e;
        } catch (\Exception $e) {
            $this->dispatch('toast', message: 'Lỗi: ' . $e->getMessage(), type: 'error');
            throw $e;
        }
    }

    public function approveTask(): void
    {
        if (!$this->canApproveTask()) {
            return;
        }

        try {
            $task = Task::findOrFail($this->editing_task_id);
            app(\App\Services\Tasks\TaskApprovalService::class)->approve(auth()->user(), $task);

            $this->showFormModal = false;
            $this->resetFormModal();

            $this->dispatch('toast', message: 'Đã phê duyệt task.', type: 'success');
            $this->dispatch('task-updated', taskId: $task->id);
        } catch (ValidationException $e) {
            $firstError = collect($e->errors())->flatten()->first();
            $this->dispatch('toast', message: (string) ($firstError ?? 'Không thể phê duyệt task.'), type: 'error');
        } catch (\Exception $e) {
            $this->dispatch('toast', message: 'Lỗi: ' . $e->getMessage(), type: 'error');
        }
    }

    public function rejectTask(): void
    {
        if (!$this->canApproveTask() || $this->editing_task_id === null) {
            return;
        }

        $reason = trim($this->rejectReason);
        if ($reason === '') {
            $this->addError('rejectReason', 'Vui lòng nhập lý do không đạt.');

            return;
        }

        try {
            $task = Task::findOrFail($this->editing_task_id);
            app(\App\Services\Tasks\TaskApprovalService::class)->reject(auth()->user(), $task, $reason);

            $updatedTaskId = $task->id;
            $this->showRejectReasonModal = false;
            $this->rejectReason = '';
            $this->showFormModal = false;
            $this->resetFormModal();

            $this->dispatch('toast', message: 'Đã từ chối duyệt và chuyển task về Đang thực hiện.', type: 'warning');
            $this->dispatch('task-updated', taskId: $updatedTaskId);
        } catch (ValidationException $e) {
            $firstError = collect($e->errors())->flatten()->first();
            $this->addError('rejectReason', (string) ($firstError ?? 'Không thể từ chối duyệt task.'));
        } catch (\Exception $e) {
            $this->addError('rejectReason', 'Lỗi: ' . $e->getMessage());
        }
    }

    private function shouldPromptCompletionRating(Task $task): bool
    {
        $actor = auth()->user();
        $fromStatus = $task->status instanceof \BackedEnum ? (string) $task->status->value : (string) $task->status;
        $toStatus = (string) $this->status;
        $workflowType = (string) $this->workflow_type;

        if ($fromStatus !== 'waiting_approval' || $toStatus !== 'completed') {
            return false;
        }

        return $this->canApproveCompletionByWorkflow($actor, $workflowType);
    }

    private function canApproveCompletionByWorkflow(?\App\Models\User $actor, string $workflowType): bool
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

    private function canApproveTask(): bool
    {
        if ($this->mode !== 'edit' || $this->editing_task_id === null || $this->original_status !== 'waiting_approval') {
            return false;
        }

        $actor = auth()->user();
        if (!$actor) {
            return false;
        }

        return $this->canApproveCompletionByWorkflow($actor, $this->workflow_type);
    }

    /**
     * Kiem tra user hien tai co duoc tham gia binh luan trong task nay hay khong.
     */
    public function canCommentCurrentTask(): bool
    {
        if ($this->mode !== 'edit' || $this->editing_task_id === null || auth()->user() === null) {
            return false;
        }

        $actor = auth()->user();
        if ($actor->hasAnyRole(['super_admin', 'ceo', 'leader'])) {
            return true;
        }

        if ($this->pic_id !== null && (int) $this->pic_id === (int) $actor->id) {
            return true;
        }

        return in_array((int) $actor->id, collect($this->co_pic_ids)->map(fn($id): int => (int) $id)->all(), true);
    }

    /**
     * Tai lai comment moi nhat cua task de tab trao doi hien thi dung du lieu.
     */
    private function loadTaskComments(): void
    {
        if ($this->editing_task_id === null || auth()->user() === null) {
            $this->taskComments = collect();

            return;
        }

        $task = app(TaskService::class)->findForEdit(auth()->user(), $this->editing_task_id);
        $this->taskComments = app(TaskCommentService::class)->getForTask(auth()->user(), $task);
    }

    /**
     * Them binh luan moi vao task va cap nhat ngay khung trao doi.
     */
    public function addComment(): void
    {
        if ($this->editing_task_id === null || auth()->user() === null) {
            return;
        }

        $this->validateOnly('newComment');

        try {
            $task = app(TaskService::class)->findForEdit(auth()->user(), $this->editing_task_id);
            app(TaskCommentService::class)->create(auth()->user(), $task, $this->newComment);

            $this->newComment = '';
            $this->loadTaskComments();
            $this->resetErrorBag('newComment');

            $this->dispatch('toast', message: 'Da gui binh luan.', type: 'success');
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());
        } catch (\Exception $e) {
            $this->addError('newComment', 'Khong the gui binh luan: ' . $e->getMessage());
        }
    }

    #[Computed]
    public function isCompletedLocked(): bool
    {
        return $this->mode === 'edit' && $this->original_status === 'completed';
    }

    public function completionModalTaskName(): string
    {
        if (trim($this->completionTaskName) !== '') {
            return trim($this->completionTaskName);
        }

        if (trim($this->name) !== '') {
            return trim($this->name);
        }

        if ($this->editing_task_id !== null) {
            return (string) (Task::query()->whereKey($this->editing_task_id)->value('name') ?? '');
        }

        return '';
    }

    public function deleteAttachment(int $attachmentId): void
    {
        try {
            $attachment = \App\Models\TaskAttachment::findOrFail($attachmentId);
            app(TaskService::class)->deleteAttachment(auth()->user(), $attachment);

            $this->existing_attachments = $this->existing_attachments->reject(fn($a) => $a->id === $attachmentId);
            $this->dispatch('toast', message: 'Đã xóa tệp đính kèm.', type: 'info');
        } catch (\Exception $e) {
            $this->dispatch('toast', message: 'Lỗi khi xóa tệp: ' . $e->getMessage(), type: 'error');
        }
    }

    public function startTask(): void
    {
        if ($this->editing_task_id === null || $this->mode !== 'edit' || $this->status !== 'pending') {
            return;
        }

        try {
            $task = Task::findOrFail($this->editing_task_id);
            app(TaskService::class)->start(auth()->user(), $task);

            $this->showFormModal = false;
            $this->resetFormModal();

            $this->dispatch('toast', message: 'Công việc đã bắt đầu!', type: 'success');
            $this->dispatch('task-updated', taskId: $task->id);
        } catch (\Exception $e) {
            $this->dispatch('toast', message: 'Lỗi: ' . $e->getMessage(), type: 'error');
        }
    }

    public function submitForApproval(): void
    {
        if ($this->editing_task_id === null || $this->mode !== 'edit') {
            return;
        }

        try {
            $task = Task::findOrFail($this->editing_task_id);
            app(TaskService::class)->submitForApproval(auth()->user(), $task);

            $this->showFormModal = false;
            $this->resetFormModal();

            $this->dispatch('toast', message: 'Đã gửi duyệt công việc.', type: 'success');
            $this->dispatch('task-updated', taskId: $task->id);
        } catch (ValidationException $e) {
            $firstError = collect($e->errors())->flatten()->first();
            $this->dispatch('toast', message: (string) ($firstError ?? 'Không thể gửi duyệt.'), type: 'error');
        } catch (\Exception $e) {
            $this->dispatch('toast', message: 'Lỗi: ' . $e->getMessage(), type: 'error');
        }
    }

    #[Computed]
    public function logs(): Collection
    {
        if (!$this->editing_task_id) {
            return collect();
        }

        $task = Task::with(['approvalLogs.reviewer:id,name', 'activityLogs.user:id,name'])->find($this->editing_task_id);

        if (!$task) {
            return collect();
        }

        $approvalLogs = $task->approvalLogs->map(function ($log) {
            $action = \App\Enums\ApprovalAction::tryFrom($log->action);

            return (object) [
                'id' => 'approval-' . $log->id,
                'type' => 'approval',
                'action' => $log->action,
                'action_label' => $action ? $action->label() : $log->action,
                'user_name' => $log->reviewer?->name ?? 'Hệ thống',
                'comment' => $log->comment,
                'star_rating' => $log->star_rating,
                'new_values' => null,
                'created_at' => $log->created_at,
                'icon' => match ($log->action) {
                    'approved' => 'check_circle',
                    'rejected' => 'cancel',
                    'submitted' => 'send',
                    default => 'history_edu',
                },
                'color' => match ($log->action) {
                    'approved' => 'green',
                    'rejected' => 'red',
                    'submitted' => 'blue',
                    default => 'slate',
                },
            ];
        });

        $activityLogs = $task->activityLogs->reject(fn($log) => $log->action === 'approval_rejected')->map(function ($log) {
            return (object) [
                'id' => 'activity-' . $log->id,
                'type' => 'activity',
                'action' => $log->action,
                'action_label' => match ($log->action) {
                    'status_updated' => 'Cập nhật trạng thái',
                    'progress_updated' => 'Cập nhật tiến độ',
                    default => 'Hoạt động',
                },
                'user_name' => $log->user?->name ?? 'Hệ thống',
                'comment' => null,
                'star_rating' => null,
                'old_values' => $log->old_values,
                'new_values' => $log->new_values,
                'created_at' => $log->created_at,
                'icon' => match ($log->action) {
                    'status_updated' => 'sync_alt',
                    'progress_updated' => 'trending_up',
                    default => 'edit_note',
                },
                'color' => 'slate',
            ];
        });

        // dd($approvalLogs->concat($activityLogs)->sortByDesc('created_at'));
        return $approvalLogs->concat($activityLogs)->sortByDesc('created_at');
    }
};
?>

<div>
    <x-ui.slide-panel wire:model="showFormModal" maxWidth="4xl"
        wire:key="task-form-panel-{{ $editing_task_id ?? 'create' }}">
        <x-slot name="header">
            @if (!empty($editing_task_id))
                edit
                <x-ui.form.heading icon="edit_square" title="Cập nhật công việc"
                    description="Chỉnh sửa thông tin công việc." />
            @else
                <x-ui.form.heading icon="add_task" title="Thêm công việc mới"
                    description="Điền thông tin để tạo công việc mới." />
            @endif
        </x-slot>

        <form id="task-form" wire:submit="save" class="p-2">
            @if ($this->isCompletedLocked)
                <div
                    class="mb-4 flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-900/10 dark:text-emerald-300">
                    <span class="material-symbols-outlined text-base">lock</span>
                    <span>Task đã hoàn thành nên form được khóa để tránh chỉnh sửa.</span>
                </div>
            @endif

            @include('components.task.form.partials.tabs')

            @if ($activeTab === 'general')
                @include('components.task.form.partials.tab-general')
            @elseif ($activeTab === 'issues')
                @include('components.task.form.partials.tab-issues')
            @elseif($activeTab === 'comments')
                @include('components.task.form.partials.tab-comments')
            @elseif ($activeTab === 'logs')
                @include('components.task.form.partials.tab-logs')
            @endif
        </form>

        <x-slot name="footer">
            @include('components.task.form.partials.panel-footer')
        </x-slot>
    </x-ui.slide-panel>

    @include('components.task.form.partials.modal-reject-reason')
    @include('components.task.form.partials.modal-completion-rating')
</div>
