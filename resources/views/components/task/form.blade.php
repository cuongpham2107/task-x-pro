<?php

use App\Models\Document;
use App\Models\Phase;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\TaskType;
use App\Services\Documents\Contracts\DocumentServiceInterface;
use App\Services\Tasks\TaskCommentService;
use App\Services\Tasks\TaskService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public ?Project $project = null;

    public ?Phase $phase = null;

    public ?int $project_id = null;

    public ?int $phase_id = null;

    public bool $showFormModal = false;

    public string $mode = 'create';

    public ?int $editing_task_id = null;

    public bool $hasDependencyBlock = false;

    public ?string $dependencyTaskName = null;

    public bool $showCompletionRatingModal = false;

    public bool $showRejectReasonModal = false;

    public bool $showTaskTypeModal = false;

    // Modal for editing/adding document versions from an attachment
    public bool $showEditDocumentModal = false;

    public ?int $editingAttachmentForDocument = null;

    public ?int $editingDocumentId = null;

    public string $editingDocumentName = '';

    public string $editingChangeSummary = '';

    /** @var UploadedFile|null */
    public $editingNewVersionFile = null;

    public ?int $completionStarRating = null;

    public string $completionApprovalComment = '';

    public string $completionTaskName = '';

    public string $rejectReason = '';

    public string $original_status = 'pending';

    public ?string $ratingSource = null;

    public bool $isResponsibleLeader = false;

    public bool $isTaskStarted = false;

    public bool $projectBlocked = false;

    public bool $canCancel = false;

    public string $name = '';

    public string $type = 'technical';

    public string $status = 'pending';

    public string $priority = 'medium';

    public string $workflow_type = 'single';

    public string $deadline = '';

    public string $description = '';

    public string $issue_note = '';

    public string $recommendation = '';

    // New: support multiple deliverable links via add/remove list input
    public array $deliverable_urls = [];

    public string $deliverable_url_input = '';

    public Collection $deliverableDocuments;

    public int $progress = 0;

    public ?int $pic_id = null;

    public ?int $dependency_task_id = null;

    public string $newComment = '';

    public array $co_pic_ids = [];

    /**
     * Per-upload metadata collected from the UI: keyed by the file index in `$files`.
     * Example: [0 => ['document_name' => 'Spec', 'change_summary' => 'v1.0'], ...]
     *
     * @var array<int, array<string, string>>
     */
    public array $file_document_meta = [];

    /** @var array<int, \Illuminate\Http\UploadedFile> */
    public $files = [];

    public string $activeTab = 'general';

    public Collection $existing_attachments;

    public Collection $taskComments;

    // Form options
    public array $taskTypeLabels = [];

    public ?int $editingTaskTypeId = null;

    public string $editingTaskTypeKey = '';

    public string $editingTaskTypeLabel = '';

    public bool $showDeliverableEditModal = false;

    public ?int $editingDeliverableIndex = null;

    public string $editingDeliverableUrl = '';

    public string $editingDeliverableNote = '';

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
        $this->deliverableDocuments = collect();
        $this->loadFormOptions();
        $actor = auth()->user();
        if ($actor && ($actor->hasRole('super_admin') || $actor->hasRole('leader'))) {
            $this->isResponsibleLeader = true;
        }
    }

    private function loadFormOptions(): void
    {
        $projectId = $this->project_id ?? $this->project?->id;
        $options = app(TaskService::class)->formOptions(Auth::user(), $projectId !== null ? (int) $projectId : null);
        $this->taskTypeLabels = $options['task_type_labels'];
        $this->taskStatusLabels = $options['task_status_labels'];
        $this->taskPriorityLabels = $options['task_priority_labels'];
        $this->workflowTypeLabels = $options['workflow_type_labels'];
        $this->projectOptions = $options['projects'];
        $this->phaseOptions = $options['phases'];
        $this->picOptions = $options['pics'];
        $this->loadDependencyTaskOptions();
    }

    public function createFreeTextOption(string $text): void
    {
        $text = trim($text);

        if ($text === '') {
            return;
        }

        if (! Schema::hasTable((new TaskType())->getTable())) {
            $this->dispatch('toast', message: 'Vui lòng chạy migration tạo bảng loại công việc trước.', type: 'error');

            return;
        }

        $taskType = TaskType::findByKeyOrLabel($text);

        if ($taskType === null) {
            $key = Str::of($text)->trim()->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->__toString();
            $label = Str::of($text)->trim()->title()->__toString();
            $taskType = TaskType::create(['key' => $key ?: Str::uuid()->toString(), 'label' => $label ?: $text]);
        }

        $this->loadFormOptions();
        $this->type = $taskType->key;
    }

    public function requestEditTaskType(string $key): void
    {
        if (! Schema::hasTable((new TaskType())->getTable())) {
            $this->dispatch('toast', message: 'Vui lòng chạy migration tạo bảng loại công việc trước.', type: 'error');

            return;
        }

        $taskType = TaskType::query()->where('key', $key)->orWhere('label', $key)->first();

        if (! $taskType) {
            return;
        }

        $this->editingTaskTypeId = $taskType->id;
        $this->editingTaskTypeKey = $taskType->key;
        $this->editingTaskTypeLabel = $taskType->label;
        $this->showTaskTypeModal = true;
    }

    public function updateTaskType(): void
    {
        if (! Schema::hasTable((new TaskType())->getTable())) {
            $this->dispatch('toast', message: 'Vui lòng chạy migration tạo bảng loại công việc trước.', type: 'error');

            return;
        }

        $label = trim($this->editingTaskTypeLabel);

        if ($label === '' || $this->editingTaskTypeId === null) {
            return;
        }

        $newKey = Str::of($label)->trim()->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->__toString();

        $exists = TaskType::query()
            ->where('key', $newKey)
            ->where('id', '!=', $this->editingTaskTypeId)
            ->exists();

        if ($exists) {
            $this->dispatch('toast', message: 'Khóa loại công việc đã tồn tại.', type: 'error');

            return;
        }

        $taskType = TaskType::find($this->editingTaskTypeId);

        if (! $taskType) {
            $this->showTaskTypeModal = false;

            return;
        }

        $taskType->update(['label' => $label, 'key' => $newKey]);

        $this->loadFormOptions();
        $this->type = $newKey;
        $this->showTaskTypeModal = false;
        $this->dispatch('toast', message: 'Cập nhật loại công việc thành công.', type: 'success');
    }

    public function deleteTaskType(?string $key = null): void
    {
        if (! Schema::hasTable((new TaskType())->getTable())) {
            $this->dispatch('toast', message: 'Vui lòng chạy migration tạo bảng loại công việc trước.', type: 'error');

            return;
        }

        if ($key === null && $this->editingTaskTypeId !== null) {
            $taskType = TaskType::find($this->editingTaskTypeId);
        } else {
            $taskType = TaskType::query()->where('key', $key)->orWhere('label', $key)->first();
        }

        if (! $taskType) {
            $this->dispatch('toast', message: 'Loại công việc không tồn tại.', type: 'error');

            return;
        }

        $hasTasks = Task::query()->where('type', $taskType->key)->exists();
        if ($hasTasks) {
            $this->dispatch('toast', message: 'Không thể xóa: đã có công việc sử dụng loại này.', type: 'error');

            return;
        }

        $taskType->delete();
        $this->loadFormOptions();

        if ($this->type === $taskType->key) {
            $this->type = array_key_first($this->taskTypeLabels) ?? 'technical';
        }

        $this->dispatch('toast', message: 'Đã xóa loại công việc.', type: 'success');
    }

    public function updatedFiles(): void
    {
        $this->resetErrorBag(['files', 'files.*']);
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
            ->when($this->editing_task_id, fn ($q) => $q->where('id', '!=', $this->editing_task_id))
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

        if ($this->phase_id) {
            $this->phase = Phase::find($this->phase_id);
        } else {
            $this->phase = null;
        }
    }

    #[Computed]
    public function isPhaseScoped(): bool
    {
        return $this->project?->id !== null && $this->phase?->id !== null;
    }

    #[Computed]
    public function canStartTask(): bool
    {
        if ($this->mode !== 'edit' || $this->status !== 'pending' || $this->isCompletedLocked()) {
            return false;
        }

        $actor = auth()->user();
        if (! $actor) {
            return false;
        }

        $isAssignee = (int) $this->pic_id === (int) $actor->id;

        return $actor->hasRole('super_admin') || $isAssignee;
    }

    #[Computed]
    public function isPhaseStarted(): bool
    {
        // If we have a phase_id but no phase model or start_date yet, fetch it fresh and SYNC it to the property
        if ($this->phase_id && (! $this->phase || ! $this->phase->start_date)) {
            $this->phase = Phase::find($this->phase_id);
        }

        if (! $this->phase || ! $this->phase->start_date) {
            return true;
        }

        return now()->greaterThanOrEqualTo($this->phase->start_date->startOfDay());
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function projectSelectOptions(): array
    {
        return $this->projectOptions->mapWithKeys(fn ($project): array => [(string) $project->id => $project->name])->all();
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

        return $phases->mapWithKeys(fn ($phase): array => [(string) $phase->id => $phase->name])->all();
    }

    #[On('task-create-requested')]
    public function showCreateFormModal(): void
    {
        $this->resetFormModal();
        $this->mode = 'create';
        $this->editing_task_id = null;
        $this->project_id = $this->project?->id;
        $this->phase_id = $this->phase?->id;
        $actor = auth()->user();
        $this->isResponsibleLeader = $actor !== null && ($actor->hasRole('super_admin') || $actor->hasRole('leader'));
        $this->loadFormOptions();
        $this->loadDependencyTaskOptions();
        $this->showFormModal = true;
    }

    #[On('task-edit-requested')]
    public function showEditFormModal(int $taskId): void
    {
        try {
            $this->resetFormModal();
            $this->editing_task_id = $taskId;
            $this->loadTaskDataIntoForm();
            $this->showFormModal = true;
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            $this->showFormModal = false;
            $this->dispatch('toast', message: 'Công việc không tồn tại hoặc đã bị xóa.', type: 'error');
        } catch (\Exception $e) {
            $this->showFormModal = false;
            $this->dispatch('toast', message: 'Lỗi: '.$e->getMessage(), type: 'error');
        }
    }

    private function loadTaskDataIntoForm(): void
    {
        $this->reset(['files']);

        if (! $this->editing_task_id) {
            return;
        }

        $task = app(TaskService::class)->findForEdit(auth()->user(), $this->editing_task_id);

        Gate::forUser(auth()->user())->authorize('view', $task);

        $this->mode = 'edit';
        $this->name = (string) $task->name;
        $this->completionTaskName = (string) $task->name;
        $this->type = $task->type instanceof \BackedEnum ? (string) $task->type->value : (string) $task->type;
        $this->status = $task->status instanceof \BackedEnum ? (string) $task->status->value : (string) $task->status;
        $this->original_status = $this->status;
        $this->priority = $task->priority instanceof \BackedEnum ? (string) $task->priority->value : (string) $task->priority;
        $this->workflow_type = $task->workflow_type instanceof \BackedEnum ? (string) $task->workflow_type->value : (string) $task->workflow_type;
        $this->deadline = $task->deadline instanceof Carbon ? $task->deadline->toDateString() : '';
        $this->description = (string) ($task->description ?? '');
        // Load deliverable_urls (casted by model)
        $this->deliverable_urls = is_array($task->deliverable_urls) ? $task->deliverable_urls : ($task->deliverable_urls ? (array) $task->deliverable_urls : []);
        $this->deliverableDocuments = Document::query()
            ->where('task_id', $task->id)
            ->where('document_type', \App\Enums\DocumentType::Deliverable)
            ->with(['versions.uploader', 'uploader'])
            ->get();
        $this->deliverable_url_input = '';
        $this->progress = (int) ($task->progress ?? 0);
        $this->issue_note = (string) ($task->issue_note ?? '');
        $this->recommendation = (string) ($task->recommendation ?? '');
        $this->pic_id = $task->pic_id;
        $this->dependency_task_id = $task->dependency_task_id;
        $this->co_pic_ids = $task->coPics->pluck('id')->all();
        $this->project_id = $task->phase?->project_id;
        $this->phase_id = $task->phase_id;
        $this->phase = $task->phase;
        $this->isTaskStarted = $task->started_at !== null;
        $actor = auth()->user();
        $this->isResponsibleLeader = false;
        if ($actor !== null) {
            if ($actor->hasRole('super_admin')) {
                $this->isResponsibleLeader = true;
            } elseif ($actor->hasRole('leader')) {
                $this->isResponsibleLeader = (bool) $task->phase?->project?->projectLeaders()->where('user_id', $actor->id)->exists();
            }

            // canCancel if super_admin, responsible leader, or task creator
            $this->canCancel = $actor->hasRole('super_admin') || $this->isResponsibleLeader || (int) $task->created_by === (int) $actor->id;
        }
        $this->existing_attachments = $task
            ->attachments()
            ->with(['uploader:id,name', 'media'])
            ->get();
        $this->taskComments = collect($task->comments ?? [])->values();
        $this->loadFormOptions();
        $this->loadDependencyTaskOptions();
        $this->activeTab = 'general';

        // Check if project is paused/blocked
        $project = $task->phase?->project;
        $this->projectBlocked = $project !== null && in_array($project->status, [
            \App\Enums\ProjectStatus::Completed,
            \App\Enums\ProjectStatus::Cancelled,
            \App\Enums\ProjectStatus::Paused,
            \App\Enums\ProjectStatus::Overdue,
        ], true);

        // Check for dependency block
        $depTask = $task->dependencyTask;
        if ($depTask !== null) {
            $depStatus = $depTask->status instanceof \BackedEnum ? $depTask->status->value : $depTask->status;
            if ($depStatus !== 'completed') {
                $this->hasDependencyBlock = true;
                $this->dependencyTaskName = $depTask->name;
            } else {
                $this->hasDependencyBlock = false;
                $this->dependencyTaskName = null;
            }
        } else {
            $this->hasDependencyBlock = false;
            $this->dependencyTaskName = null;
        }
    }

    public function resetFormModal(): void
    {
        $this->reset(['name', 'type', 'status', 'priority', 'workflow_type', 'deadline', 'description', 'deliverable_urls', 'deliverable_url_input', 'progress', 'issue_note', 'recommendation', 'pic_id', 'dependency_task_id', 'project_id', 'phase_id', 'newComment', 'co_pic_ids', 'editing_task_id', 'files', 'file_document_meta', 'existing_attachments', 'hasDependencyBlock', 'dependencyTaskName', 'activeTab', 'showCompletionRatingModal', 'completionStarRating', 'completionApprovalComment', 'completionTaskName', 'showRejectReasonModal', 'rejectReason', 'showEditDocumentModal', 'editingAttachmentForDocument', 'editingDocumentId', 'editingDocumentName', 'editingChangeSummary', 'editingNewVersionFile', 'original_status', 'isResponsibleLeader', 'isTaskStarted', 'projectBlocked', 'canCancel', 'showTaskTypeModal', 'editingTaskTypeId', 'editingTaskTypeKey', 'editingTaskTypeLabel', 'showDeliverableEditModal', 'editingDeliverableIndex', 'editingDeliverableUrl', 'editingDeliverableNote']);
        $this->existing_attachments = collect();
        $this->taskComments = collect();
        $this->deliverableDocuments = collect();
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

    public function addDeliverableUrl(): void
    {
        if ($this->isCompletedLocked()) {
            return;
        }

        $value = trim((string) $this->deliverable_url_input);
        if ($value === '') {
            return;
        }

        if (! filter_var($value, FILTER_VALIDATE_URL)) {
            $this->addError('deliverable_urls', 'Link sản phẩm phải là một URL hợp lệ.');

            return;
        }

        if (mb_strlen($value) > 1000) {
            $this->addError('deliverable_urls', 'Link sản phẩm không được vượt quá 1000 ký tự.');

            return;
        }

        if (in_array($value, $this->deliverable_urls, true)) {
            $this->addError('deliverable_urls', 'Link đã tồn tại trong danh sách.');

            return;
        }

        $this->deliverable_urls[] = $value;
        $this->deliverable_url_input = '';
        $this->resetErrorBag('deliverable_urls');

        if ($this->mode === 'edit' && $this->editing_task_id !== null) {
            $task = Task::find($this->editing_task_id);
            if ($task) {
                $doc = app(DocumentServiceInterface::class)->createDeliverableDocument(auth()->user(), $task, $value);
                $this->deliverableDocuments->push($doc->load('versions'));
            }
        }
    }

    public function removeDeliverableUrl(int $index): void
    {
        if (! isset($this->deliverable_urls[$index])) {
            return;
        }

        if ($this->mode === 'edit' && $this->editing_task_id !== null) {
            $doc = $this->deliverableDocuments->get($index);
            if ($doc) {
                app(DocumentServiceInterface::class)->deleteDocument(auth()->user(), $doc);
            }
        }

        array_splice($this->deliverable_urls, $index, 1);
    }

    public function openDeliverableEditModal(int $index): void
    {
        if (! isset($this->deliverable_urls[$index])) {
            return;
        }

        $this->editingDeliverableIndex = $index;
        $this->editingDeliverableUrl = $this->deliverable_urls[$index];
        $this->editingDeliverableNote = '';
        $this->showDeliverableEditModal = true;
        $this->resetErrorBag(['editingDeliverableUrl', 'editingDeliverableNote']);
    }

    public function saveDeliverableEdit(): void
    {
        if ($this->editingDeliverableIndex === null || ! isset($this->deliverable_urls[$this->editingDeliverableIndex])) {
            return;
        }

        $url = trim($this->editingDeliverableUrl);
        if ($url === '') {
            $this->addError('editingDeliverableUrl', 'Link sản phẩm không được để trống.');

            return;
        }

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            $this->addError('editingDeliverableUrl', 'Link sản phẩm phải là một URL hợp lệ.');

            return;
        }

        if (mb_strlen($url) > 1000) {
            $this->addError('editingDeliverableUrl', 'Link sản phẩm không được vượt quá 1000 ký tự.');

            return;
        }

        $doc = $this->deliverableDocuments[$this->editingDeliverableIndex] ?? null;
        $index = $this->editingDeliverableIndex;

        if ($this->mode === 'edit' && $doc) {
            $updated = app(DocumentServiceInterface::class)->updateDeliverableDocument(
                auth()->user(), $doc, $url, $this->editingDeliverableNote ?: null
            );
            $this->deliverableDocuments[$index] = $updated;
        }

        $this->deliverable_urls[$index] = $url;
        $this->showDeliverableEditModal = false;
        $this->editingDeliverableIndex = null;
        $this->editingDeliverableUrl = '';
        $this->editingDeliverableNote = '';
    }

    public function rules(): array
    {
        $rules = [
            'phase_id' => 'required|integer|exists:phases,id',
            'name' => 'required|string|max:255',
            'type' => 'required|string',
            'status' => 'required|string',
            'priority' => 'required|string',
            'workflow_type' => 'required|string',
            'deadline' => ['required', 'date'],
            'description' => 'nullable|string|max:5000',
            'issue_note' => 'nullable|string|max:5000',
            'recommendation' => 'nullable|string|max:5000',
            'deliverable_urls' => 'nullable|array',
            'deliverable_urls.*' => 'nullable|url|max:1000',
            'progress' => 'required|integer|min:0|max:100',
            'pic_id' => 'required|integer|exists:users,id',
            'dependency_task_id' => 'nullable|integer|exists:tasks,id',
            'newComment' => 'nullable|string|max:5000',
            'files' => 'nullable|array|max:100',
            'files.*' => 'nullable|file|mimes:png,jpg,pdf|max:102400',
        ];

        if ($this->phase) {
            $phaseStart = $this->phase->start_date ? $this->phase->start_date->toDateString() : null;
            $phaseEnd = $this->phase->end_date ? $this->phase->end_date->toDateString() : null;

            if ($phaseStart) {
                $rules['deadline'][] = "after_or_equal:{$phaseStart}";
            }
            if ($phaseEnd) {
                $rules['deadline'][] = "before_or_equal:{$phaseEnd}";
            }
        }

        return $rules;
    }

    protected function messages(): array
    {
        $phaseStartLabel = $this->phase?->start_date ? $this->phase->start_date->format('d/m/Y') : 'N/A';
        $phaseEndLabel = $this->phase?->end_date ? $this->phase->end_date->format('d/m/Y') : 'N/A';

        return [
            'name.required' => 'Tên công việc là bắt buộc.',
            'name.max' => 'Tên công việc không được vượt quá 255 ký tự.',
            'phase_id.required' => 'Giai đoạn là bắt buộc.',
            'phase_id.exists' => 'Giai đoạn không hợp lệ.',
            'pic_id.required' => 'Người phụ trách là bắt buộc.',
            'pic_id.exists' => 'Người phụ trách không hợp lệ.',
            'deadline.required' => 'Hạn chót là bắt buộc.',
            'deadline.date' => 'Hạn chót phải là định dạng ngày hợp lệ.',
            'deadline.after_or_equal' => "Hạn chót phải sau hoặc bằng ngày bắt đầu giai đoạn ({$phaseStartLabel}).",
            'deadline.before_or_equal' => "Hạn chót phải trước hoặc bằng ngày kết thúc giai đoạn ({$phaseEndLabel}).",
            'type.required' => 'Loại công việc là bắt buộc.',
            'status.required' => 'Trạng thái là bắt buộc.',
            'priority.required' => 'Mức độ ưu tiên là bắt buộc.',
            'workflow_type.required' => 'Quy trình phê duyệt là bắt buộc.',
            'progress.required' => 'Tiến độ công việc là bắt buộc.',
            'progress.integer' => 'Tiến độ phải là số nguyên.',
            'progress.min' => 'Tiến độ không được nhỏ hơn 0%.',
            'progress.max' => 'Tiến độ không được lớn hơn 100%.',
            'deliverable_urls.*.url' => 'Link sản phẩm phải là một URL hợp lệ.',
            'deliverable_urls.*.max' => 'Link sản phẩm không được vượt quá 1000 ký tự.',
            'description.max' => 'Mô tả không được vượt quá 5000 ký tự.',
            'issue_note.max' => 'Ghi chú vấn đề không được vượt quá 5000 ký tự.',
            'recommendation.max' => 'Kiến nghị không được vượt quá 5000 ký tự.',
            'dependency_task_id.exists' => 'Công việc phụ thuộc không hợp lệ.',
            'newComment.required' => 'Nội dung bình luận là bắt buộc.',
            'newComment.max' => 'Bình luận không được vượt quá 5000 ký tự.',
            'rejectReason.required' => 'Vui lòng nhập lý do không đạt.',
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
        if (! $this->canApproveTask()) {
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
            $this->dispatch('toast', message: 'Công việc đã hoàn thành hoặc đã hủy, không thể chỉnh sửa.', type: 'warning');

            return;
        }

        if ($this->phase_id === null && $this->phase?->id !== null) {
            $this->phase_id = $this->phase->id;
        }
        try {
            // deliverable_urls is managed by add/remove UI; just validate the current state
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
                'deliverable_urls' => $this->deliverable_urls ?: null,
                'progress' => $this->progress,
                'issue_note' => $this->issue_note ?: null,
                'recommendation' => $this->recommendation ?: null,
                'pic_id' => $this->pic_id,
                'dependency_task_id' => $this->dependency_task_id,
                'attachments' => $this->files,
                // Pass per-file metadata for Document creation, keyed by original file index
                'attachment_meta' => $this->file_document_meta ?: null,
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
                    $this->ratingSource = 'save';
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

                foreach ($this->deliverable_urls as $url) {
                    app(DocumentServiceInterface::class)->createDeliverableDocument(
                        auth()->user(), $result->task, $url
                    );
                }
            }

            // Show overload warning popup if PIC is overloaded (BR-006)
            if ($overloadWarning) {
                $this->dispatch('show-overload-warning', message: $overloadWarning);
            }

            if ($this->mode === 'create') {
                $this->showFormModal = false;
                $this->resetFormModal();
            } else {
                // Keep modal open for edits, but refresh ALL data from DB to ensure sync
                $this->loadTaskDataIntoForm();
                $this->files = []; // Ensure files are cleared after moving to permanent storage
            }

            $this->dispatch('toast', message: $toastMessage, type: 'success');
            $this->dispatch('task-saved', taskTitle: $savedName);
        } catch (ValidationException $e) {
            $this->dispatch('toast', message: 'Lỗi hệ thống: '.$e->getMessage(), type: 'error');
        } catch (\Exception $e) {
            $this->dispatch('toast', message: 'Lỗi hệ thống: '.$e->getMessage(), type: 'error');
        }
    }

    public function approveTask(): void
    {
        if (! $this->canApproveTask()) {
            return;
        }

        try {
            $task = Task::findOrFail($this->editing_task_id);
            $needsRating = $this->shouldPromptRatingForApproval($task);

            if ($needsRating && $this->completionStarRating === null) {
                $this->ratingSource = 'approve';
                $this->completionTaskName = (string) $task->name;
                $this->showFormModal = false;
                $this->showCompletionRatingModal = true;
                $this->addError('completionStarRating', 'Vui lòng chọn điểm đánh giá từ 1 đến 5 sao.');

                return;
            }

            app(\App\Services\Tasks\TaskApprovalService::class)->approve(auth()->user(), $task, $this->completionStarRating, $this->completionApprovalComment !== '' ? $this->completionApprovalComment : null);

            $this->showCompletionRatingModal = false;
            $this->loadTaskDataIntoForm();

            $this->dispatch('toast', message: 'Đã phê duyệt task.', type: 'success');
            $this->dispatch('task-updated', taskId: $task->id);
        } catch (ValidationException $e) {
            $firstError = collect($e->errors())->flatten()->first();
            $this->dispatch('toast', message: (string) ($firstError ?? 'Không thể phê duyệt task.'), type: 'error');
        } catch (\Exception $e) {
            $this->dispatch('toast', message: 'Lỗi: '.$e->getMessage(), type: 'error');
        }
    }

    public function confirmCompletionRating(): void
    {
        if ($this->completionStarRating === null) {
            $this->addError('completionStarRating', 'Vui lòng chọn điểm đánh giá từ 1 đến 5 sao.');

            return;
        }

        if ($this->ratingSource === 'approve') {
            $this->approveTask();
        } else {
            $this->save();
        }
        $this->showCompletionRatingModal = false;
    }

    public function rejectTask(): void
    {
        if (! $this->canApproveTask() || $this->editing_task_id === null) {
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

            $this->loadTaskDataIntoForm();
            $this->showFormModal = true;

            $this->dispatch('toast', message: 'Đã từ chối duyệt và chuyển task về Đang thực hiện.', type: 'warning');
            $this->dispatch('task-updated', taskId: $updatedTaskId);
        } catch (ValidationException $e) {
            $firstError = collect($e->errors())->flatten()->first();
            $this->addError('rejectReason', (string) ($firstError ?? 'Không thể từ chối duyệt task.'));
        } catch (\Exception $e) {
            $this->addError('rejectReason', 'Lỗi: '.$e->getMessage());
        }
    }

    private function shouldPromptCompletionRating(Task $task): bool
    {
        $actor = auth()->user();
        $fromStatus = $task->status instanceof \BackedEnum ? (string) $task->status->value : (string) $task->status;
        $toStatus = (string) $this->status;

        if ($fromStatus !== 'waiting_approval' || $toStatus !== 'completed') {
            return false;
        }

        return $actor !== null && $actor->can('approve', $task);
    }

    private function shouldPromptRatingForApproval(Task $task): bool
    {
        $actor = auth()->user();
        if (! $actor) {
            return false;
        }

        // Neu dang o trang thai cho duyet
        if ($this->original_status !== 'waiting_approval') {
            return false;
        }

        return $actor->can('approve', $task);
    }

    private function canApproveTask(): bool
    {
        if ($this->mode !== 'edit' || $this->editing_task_id === null || $this->original_status !== 'waiting_approval') {
            return false;
        }

        $actor = auth()->user();
        if (! $actor) {
            return false;
        }

        $task = Task::find($this->editing_task_id);

        return $task !== null && $actor->can('approve', $task);
    }

    /**
     * Kiem tra user hien tai co duoc tham gia binh luan trong task nay hay khong.
     */
    public function canCommentCurrentTask(): bool
    {
        if ($this->mode !== 'edit' || $this->editing_task_id === null || auth()->user() === null) {
            return false;
        }

        if ($this->isCompletedLocked()) {
            return false;
        }

        $actor = auth()->user();
        if ($actor->hasAnyRole(['super_admin', 'ceo', 'leader'])) {
            return true;
        }

        if ($this->pic_id !== null && (int) $this->pic_id === (int) $actor->id) {
            return true;
        }

        return in_array((int) $actor->id, collect($this->co_pic_ids)->map(fn ($id): int => (int) $id)->all(), true);
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

        if ($this->isCompletedLocked()) {
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
            $this->addError('newComment', 'Khong the gui binh luan: '.$e->getMessage());
        }
    }

    #[Computed]
    public function isCompletedLocked(): bool
    {
        return $this->mode === 'edit' && ($this->original_status === 'completed' || $this->original_status === 'cancelled');
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
        if ($this->isCompletedLocked()) {
            return;
        }

        try {
            $attachment = \App\Models\TaskAttachment::findOrFail($attachmentId);
            app(TaskService::class)->deleteAttachment(auth()->user(), $attachment);

            $this->existing_attachments = $this->existing_attachments->reject(fn ($a) => $a->id === $attachmentId);
            $this->dispatch('toast', message: 'Đã xóa tệp đính kèm.', type: 'info');
        } catch (\Exception $e) {
            $this->dispatch('toast', message: 'Lỗi khi xóa tệp: '.$e->getMessage(), type: 'error');
        }
    }

    public function openDocumentEditor(int $attachmentId): void
    {
        try {
            $attachment = TaskAttachment::with('task')->findOrFail($attachmentId);

            $this->editingAttachmentForDocument = $attachment->id;
            $this->editingDocumentName = (string) ($attachment->original_name ?? '');
            $this->editingChangeSummary = '';
            $this->editingNewVersionFile = null;

            // Try to find an existing document for this task and original name
            $document = Document::query()
                ->where('task_id', $attachment->task_id)
                ->where('name', $attachment->original_name)
                ->first();

            $this->editingDocumentId = $document ? $document->id : null;

            // Hide main form and show modal
            $this->showFormModal = false;
            $this->showEditDocumentModal = true;
            $this->resetErrorBag(['editingDocumentName', 'editingChangeSummary', 'editingNewVersionFile']);
        } catch (\Exception $e) {
            $this->dispatch('toast', message: 'Không thể mở trình chỉnh sửa tài liệu: '.$e->getMessage(), type: 'error');
        }
    }

    public function closeEditDocumentModal(): void
    {
        $this->showEditDocumentModal = false;
        $this->editingAttachmentForDocument = null;
        $this->editingDocumentId = null;
        $this->editingDocumentName = '';
        $this->editingChangeSummary = '';
        $this->editingNewVersionFile = null;
        if ($this->mode === 'edit' && $this->editing_task_id !== null) {
            $this->showFormModal = true;
        }
        $this->resetErrorBag(['editingDocumentName', 'editingChangeSummary', 'editingNewVersionFile']);
    }

    public function saveDocumentModal(): void
    {
        try {
            $this->validate([
                'editingDocumentName' => 'nullable|string|max:255',
                'editingChangeSummary' => 'nullable|string|max:1000',
                'editingNewVersionFile' => 'nullable|file|mimes:png,jpg,pdf|max:102400',
            ]);

            $actor = auth()->user();

            // If user uploaded a new file, create a new TaskAttachment (and DocumentVersion) via TaskService
            if ($this->editingNewVersionFile instanceof UploadedFile) {
                if ($this->editing_task_id === null) {
                    $this->addError('editingNewVersionFile', 'Không thể thêm phiên bản mới cho task chưa lưu.');

                    return;
                }

                $task = Task::findOrFail($this->editing_task_id);

                $meta = [
                    0 => [
                        'document_name' => $this->editingDocumentName ?: null,
                        'change_summary' => $this->editingChangeSummary ?: null,
                    ],
                ];

                app(TaskService::class)->addAttachments($actor, $task, [$this->editingNewVersionFile], $meta);

                $this->dispatch('toast', message: 'Phiên bản mới đã được tải lên.', type: 'success');
                $this->loadTaskDataIntoForm();
                $this->closeEditDocumentModal();

                return;
            }

            // Otherwise update existing document metadata if available
            if ($this->editingDocumentId !== null) {
                $document = Document::findOrFail($this->editingDocumentId);
                app(DocumentServiceInterface::class)->updateDocument($actor, $document, [
                    'name' => $this->editingDocumentName ?: $document->name,
                    'description' => $this->editingChangeSummary ?: $document->description,
                ]);

                $this->dispatch('toast', message: 'Thông tin tài liệu đã được cập nhật.', type: 'success');
                $this->loadTaskDataIntoForm();
                $this->closeEditDocumentModal();

                return;
            }

            $this->dispatch('toast', message: 'Không có tài liệu liên quan để cập nhật.', type: 'warning');
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());
        } catch (\Exception $e) {
            $this->dispatch('toast', message: 'Lỗi: '.$e->getMessage(), type: 'error');
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

            $this->loadTaskDataIntoForm();

            $this->dispatch('toast', message: 'Công việc đã bắt đầu!', type: 'success');
            $this->dispatch('task-updated', taskId: $task->id);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->dispatch('toast', message: 'Lỗi: Chỉ PIC của task mới có thể bắt đầu công việc.', type: 'error');
        } catch (\Exception $e) {
            $this->dispatch('toast', message: 'Lỗi: '.$e->getMessage(), type: 'error');
        }
    }

    public function cancelTask(): void
    {
        if ($this->mode !== 'edit' || $this->editing_task_id === null) {
            return;
        }

        try {
            $task = Task::findOrFail($this->editing_task_id);

            // Authorization check: project leader, task creator, or super admin
            $actor = auth()->user();
            $isCreator = (int) $task->created_by === (int) $actor->id;

            if (! $actor->hasRole('super_admin') && ! $this->isResponsibleLeader && ! $isCreator) {
                $this->dispatch('toast', message: 'Bạn không có quyền hủy công việc này.', type: 'error');

                return;
            }

            if ($task->status === \App\Enums\TaskStatus::Completed) {
                $this->dispatch('toast', message: 'Không thể hủy công việc đã hoàn thành.', type: 'warning');

                return;
            }

            if ($task->status === \App\Enums\TaskStatus::Cancelled) {
                $this->dispatch('toast', message: 'Công việc đã được hủy trước đó.', type: 'info');

                return;
            }

            $task->update(['status' => \App\Enums\TaskStatus::Cancelled]);

            $this->loadTaskDataIntoForm();
            $this->dispatch('toast', message: 'Đã hủy công việc thành công.', type: 'success');
            $this->dispatch('task-updated', taskId: $task->id);
        } catch (\Exception $e) {
            $this->dispatch('toast', message: 'Lỗi hệ thống: '.$e->getMessage(), type: 'error');
        }
    }

    public function submitForApproval(): void
    {
        if ($this->progress < 100) {
            $this->dispatch('toast', message: 'Tiến độ công việc phải đạt 100% trước khi gửi duyệt.', type: 'error');

            return;
        }
        if ($this->editing_task_id === null || $this->mode !== 'edit') {
            return;
        }

        try {
            $task = Task::findOrFail($this->editing_task_id);
            app(TaskService::class)->submitForApproval(auth()->user(), $task);

            $this->loadTaskDataIntoForm();

            $this->dispatch('toast', message: 'Đã gửi duyệt công việc.', type: 'success');
            $this->dispatch('task-updated', taskId: $task->id);
        } catch (ValidationException $e) {
            $firstError = collect($e->errors())->flatten()->first();
            $this->dispatch('toast', message: (string) ($firstError ?? 'Không thể gửi duyệt công việc.'), type: 'error');
        } catch (\Exception $e) {
            $this->dispatch('toast', message: 'Lỗi: '.$e->getMessage(), type: 'error');
        }
    }

    #[Computed]
    public function logs(): Collection
    {
        if (! $this->editing_task_id) {
            return collect();
        }

        $task = Task::with(['approvalLogs.reviewer:id,name', 'activityLogs.user:id,name'])->find($this->editing_task_id);

        if (! $task) {
            return collect();
        }

        $approvalLogs = $task->approvalLogs->map(function ($log) {
            $action = \App\Enums\ApprovalAction::tryFrom($log->action);

            return (object) [
                'id' => 'approval-'.$log->id,
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

        $activityLogs = $task->activityLogs->reject(fn ($log) => $log->action === 'approval_rejected')->map(function ($log) {
            return (object) [
                'id' => 'activity-'.$log->id,
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

    #[Computed]
    public function hasLeaderApproved(): bool
    {
        if (! $this->editing_task_id) {
            return false;
        }
        $task = Task::find($this->editing_task_id);
        if (! $task) {
            return false;
        }

        $lastRejectedLogId = $task->approvalLogs()->where('action', \App\Enums\ApprovalAction::Rejected->value)->max('id');

        return $task
            ->approvalLogs()
            ->when($lastRejectedLogId !== null, function ($query) use ($lastRejectedLogId): void {
                $query->where('id', '>', (int) $lastRejectedLogId);
            })
            ->where('approval_level', \App\Enums\ApprovalLevel::Leader->value)
            ->where('action', \App\Enums\ApprovalAction::Approved->value)
            ->exists();
    }

    #[Computed]
    public function hasCeoApproved(): bool
    {
        if (! $this->editing_task_id) {
            return false;
        }
        $task = Task::find($this->editing_task_id);
        if (! $task) {
            return false;
        }

        $lastRejectedLogId = $task->approvalLogs()->where('action', \App\Enums\ApprovalAction::Rejected->value)->max('id');

        return $task
            ->approvalLogs()
            ->when($lastRejectedLogId !== null, function ($query) use ($lastRejectedLogId): void {
                $query->where('id', '>', (int) $lastRejectedLogId);
            })
            ->where('approval_level', \App\Enums\ApprovalLevel::Ceo->value)
            ->where('action', \App\Enums\ApprovalAction::Approved->value)
            ->exists();
    }
};
?>

<div>
    <x-ui.slide-panel wire:model="showFormModal" maxWidth="4xl"
        wire:key="task-form-panel-{{ $editing_task_id ?? 'create' }}">
        <x-slot name="header">
            @if (!empty($editing_task_id))
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
                    <span>
                        @if ($this->original_status === 'completed')
                            Công việc đã hoàn thành nên form được khóa để tránh chỉnh sửa.
                        @else
                            Công việc đã hủy nên form được khóa để tránh chỉnh sửa.
                        @endif
                    </span>
                </div>
            @endif

            @if ($projectBlocked)
                <div
                    class="mb-4 flex items-center gap-2 rounded-lg border border-orange-200 bg-orange-50 px-3 py-2 text-sm text-orange-700 dark:border-orange-900/40 dark:bg-orange-900/10 dark:text-orange-300">
                    <span class="material-symbols-outlined text-base">warning_amber</span>
                    <span>Dự án đang tạm dừng hoặc đã kết thúc. Chế độ chỉ xem.</span>
                </div>
            @endif

            @include('components.task.form.partials.tabs')

            <div class="relative" x-data="{ blocked: @js($projectBlocked) }"
                x-init="if (blocked) { $el.querySelectorAll('input, select, textarea, button, [href], [tabindex]:not([tabindex=\\'-1\\'])').forEach(el => el.tabIndex = -1) }">

                <div class="{{ $projectBlocked ? 'opacity-60' : '' }}">
                    @if ($activeTab === 'general')
                        @include('components.task.form.partials.tab-general')
                    @elseif ($activeTab === 'issues')
                        @include('components.task.form.partials.tab-issues')
                    @elseif($activeTab === 'comments')
                        @include('components.task.form.partials.tab-comments')
                    @elseif ($activeTab === 'logs')
                        @include('components.task.form.partials.tab-logs')
                    @endif
                </div>

                @if ($projectBlocked)
                    <div class="absolute inset-0 z-10 cursor-not-allowed"
                        @click.prevent @click.stop
                        @keydown.prevent @keydown.stop></div>
                @endif
            </div>
        </form>

        <x-slot name="footer">
            @include('components.task.form.partials.panel-footer')
        </x-slot>
    </x-ui.slide-panel>

    <x-ui.slide-panel wire:model="showEditDocumentModal" maxWidth="lg">
        <x-slot name="header">
            <x-ui.form.heading icon="edit_square" title="Chỉnh sửa tài liệu"
                description="Chỉnh sửa thông tin tài liệu hoặc tải lên phiên bản mới." />
        </x-slot>

        <form wire:submit.prevent="saveDocumentModal" class="p-4 space-y-4">
            <div class="grid grid-cols-1 gap-3">
                <x-ui.input label="Tên tài liệu" wire:model.defer="editingDocumentName" />

                <x-ui.textarea label="Ghi chú / Mô tả" wire:model.defer="editingChangeSummary" />

                <div x-data="{ fileName: '' }">
                    <label class="block text-sm font-medium text-slate-700">Tải lên phiên bản mới (tùy chọn)</label>
                    <div class="mt-2 flex items-center gap-3">
                        <label
                            class="inline-flex items-center gap-2 cursor-pointer rounded-md bg-white border px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">
                            <span class="material-symbols-outlined">upload_file</span>
                            <span>Chọn tệp</span>
                            <input type="file" wire:model="editingNewVersionFile"
                                @change="fileName = $event.target.files[0] ? $event.target.files[0].name : ''"
                                class="hidden" />
                        </label>

                        <div class="text-sm text-slate-500 truncate max-w-[36ch]">
                            <span x-text="fileName ? fileName : 'Chưa chọn tệp'"></span>
                        </div>
                    </div>

                    @error('editingNewVersionFile')
                        <div class="text-xs text-red-500">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="mt-4 flex justify-end gap-2">
                <button type="button" wire:click="closeEditDocumentModal"
                    class="rounded-md bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700">Hủy</button>
                <button type="button" wire:click="saveDocumentModal"
                    class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-white">Lưu</button>
            </div>
        </form>
    </x-ui.slide-panel>

    <x-ui.modal wire:model="showTaskTypeModal" maxWidth="md">
        <x-slot name="header">
            <x-ui.form.heading icon="category" title="Chỉnh sửa loại công việc"
                description="Cập nhật nhãn hoặc xóa loại công việc đang chọn." />
        </x-slot>

        <form wire:submit.prevent="updateTaskType" class="space-y-4 p-4">
            <x-ui.input label="Tên loại công việc" name="editingTaskTypeLabel" wire:model.defer="editingTaskTypeLabel"
                required />

            <div class="flex justify-end gap-2">
                <x-ui.button type="button" variant="secondary" @click="$wire.set('showTaskTypeModal', false)">Hủy</x-ui.button>
                <x-ui.button type="button" variant="danger" wire:click="deleteTaskType" onclick="return confirm('Xác nhận xóa loại công việc này?')">
                    Xóa
                </x-ui.button>
                <x-ui.button type="submit" variant="primary">Lưu</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    @include('components.task.form.partials.modal-reject-reason')
    @include('components.task.form.partials.modal-completion-rating')
</div>
