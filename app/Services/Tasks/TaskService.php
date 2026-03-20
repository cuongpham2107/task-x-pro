<?php

namespace App\Services\Tasks;

use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Enums\SystemNotificationType;
use App\Enums\TaskStatus;
use App\Enums\TaskWorkflowType;
use App\Models\SystemNotification;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\User;
use App\Notifications\TaskApprovalRequestLeaderNotification;
use App\Notifications\TaskAssignedNotification;
use App\Services\Documents\Contracts\DocumentServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class TaskService
{
    /**
     * Khoi tao TaskService va cac service con theo tung trach nhiem.
     */
    public function __construct(
        private readonly TaskQueryService $queryService,
        private readonly TaskPayloadService $payloadService,
        private readonly TaskSlaService $slaService,
        private readonly TaskOverloadService $overloadService,
        private readonly TaskApprovalService $approvalService,
        private readonly DocumentServiceInterface $documentService,
    ) {}

    /**
     * Lay danh sach task cho man hinh index.
     */
    public function paginateForIndex(User $actor, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        Gate::forUser($actor)->authorize('viewAny', Task::class);

        return $this->queryService->paginateForIndex($actor, $filters, $perPage);
    }

    /**
     * Lay chi tiet task cho man hinh edit.
     */
    public function findForEdit(User $actor, int $taskId): Task
    {
        $task = $this->queryService->findForEdit($taskId);
        Gate::forUser($actor)->authorize('view', $task);

        return $task;
    }

    /**
     * Tra ve option cho form task de Livewire co the dung truc tiep.
     *
     * @return array<string, mixed>
     */
    public function formOptions(User $actor, ?int $projectId = null): array
    {
        return $this->queryService->formOptions($actor, $projectId);
    }

    /**
     * Tao task moi, tu dong snapshot SLA va phat hien overload PIC.
     */
    public function create(User $actor, array $attributes, array $coPicIds = []): TaskMutationResult
    {
        Gate::forUser($actor)->authorize('create', Task::class);

        return DB::transaction(function () use ($actor, $attributes, $coPicIds): TaskMutationResult {
            $payload = $this->payloadService->normalizedTaskAttributes($attributes, null);

            $phase = $this->payloadService->resolvePhaseForTaskPayload((int) $payload['phase_id']);
            Gate::forUser($actor)->authorize('update', $phase->project);

            $targetStatus = (string) ($payload['status'] ?? TaskStatus::Pending->value);
            $dependencyTaskId = $payload['dependency_task_id'] ?? null;
            $this->payloadService->ensureDependencyReady($dependencyTaskId !== null ? (int) $dependencyTaskId : null, $targetStatus);

            $this->applyAutoDatesForStatus($payload, $targetStatus);

            $payload['created_by'] = $actor->id;
            $payload['sla_standard_hours'] = $this->slaService->resolveSlaStandardHours(
                $phase,
                (int) $payload['pic_id'],
                (string) $payload['type'],
                $this->slaService->resolveSlaReferenceDate($payload),
            );

            $task = Task::query()->create($payload);
            $this->payloadService->syncCoPics($task, $coPicIds);

            $task = $task->refresh();
            $this->sendAssignmentNotification($actor, $task);
            $overloadWarning = $this->overloadService->warnIfPicOverloaded($actor, $task);

            return new TaskMutationResult(
                task: $this->queryService->hydrateTask($task->refresh()),
                overloadWarning: $overloadWarning,
                attachments: $this->addAttachments($actor, $task, $attributes['attachments'] ?? []),
            );
        });
    }

    /**
     * Cap nhat task, xu ly dependency lock, SLA snapshot va overload warning.
     */
    public function update(User $actor, Task $task, array $attributes, ?array $coPicIds = null): TaskMutationResult
    {
        Gate::forUser($actor)->authorize('update', $task);

        return DB::transaction(function () use ($actor, $task, $attributes, $coPicIds): TaskMutationResult {
            $statusBeforeUpdate = $task->status instanceof \BackedEnum
                ? (string) $task->status->value
                : (string) $task->status;

            $incomingStatus = $attributes['status'] ?? null;
            $targetStatus = $incomingStatus instanceof \BackedEnum
                ? (string) $incomingStatus->value
                : (string) ($incomingStatus ?? $statusBeforeUpdate);
            $workflowType = $this->resolveWorkflowType($attributes['workflow_type'] ?? $task->workflow_type);
            $isStatusChanged = $targetStatus !== $statusBeforeUpdate;
            $isCompletionTransition = $targetStatus === TaskStatus::Completed->value
                && $statusBeforeUpdate !== TaskStatus::Completed->value;
            $isApprovalStep = $statusBeforeUpdate === TaskStatus::WaitingApproval->value
                && $targetStatus === TaskStatus::Completed->value;

            if ($isStatusChanged) {
                $this->assertAllowedStatusTransition($statusBeforeUpdate, $targetStatus);
            }

            if (
                $isStatusChanged
                && in_array($targetStatus, [TaskStatus::InProgress->value, TaskStatus::WaitingApproval->value], true)
                && ! $this->canPicControlExecutionFlow($actor, $task)
            ) {
                throw ValidationException::withMessages([
                    'status' => 'Chỉ PIC của task mới được chuyển sang Đang thực hiện hoặc Chờ duyệt.',
                ]);
            }

            if ($isApprovalStep && ! $this->canApproveCompletionByWorkflow($actor, $workflowType)) {
                throw ValidationException::withMessages([
                    'status' => $this->completionPermissionMessage($workflowType),
                ]);
            }

            $starRating = array_key_exists('star_rating', $attributes)
                && $attributes['star_rating'] !== null
                && $attributes['star_rating'] !== ''
                ? (int) $attributes['star_rating']
                : null;

            $approvalComment = array_key_exists('approval_comment', $attributes)
                ? trim((string) $attributes['approval_comment'])
                : null;

            if ($approvalComment === '') {
                $approvalComment = null;
            }

            if ($isApprovalStep && $starRating === null) {
                throw ValidationException::withMessages([
                    'star_rating' => 'Vui lòng chọn điểm đánh giá từ 1 đến 5 sao trước khi hoàn thành công việc.',
                ]);
            }

            if ($starRating !== null && ($starRating < 1 || $starRating > 5)) {
                throw ValidationException::withMessages([
                    'star_rating' => 'Điểm đánh giá phải nằm trong khoảng từ 1 đến 5.',
                ]);
            }

            $payload = $this->payloadService->normalizedTaskAttributes($attributes, $task);

            if (
                array_key_exists('pic_id', $payload)
                && (int) $payload['pic_id'] !== (int) $task->pic_id
            ) {
                Gate::forUser($actor)->authorize('assign', $task);
            }

            if ($coPicIds !== null) {
                $currentCoPicIds = $task->coPics->pluck('id')->sort()->values()->all();
                $incomingCoPicIds = collect($coPicIds)
                    ->filter(fn ($id) => $id !== null && $id !== '')
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->sort()
                    ->values()
                    ->all();

                if ($currentCoPicIds !== $incomingCoPicIds) {
                    Gate::forUser($actor)->authorize('assign', $task);
                }
            }

            $phaseId = (int) ($payload['phase_id'] ?? $task->phase_id);
            $phase = $this->payloadService->resolvePhaseForTaskPayload($phaseId);

            if (array_key_exists('phase_id', $payload) && (int) $payload['phase_id'] !== (int) $task->phase_id) {
                Gate::forUser($actor)->authorize('update', $phase->project);
            }

            $targetStatus = $this->normalizeStatus($payload['status'] ?? $task->status);
            $workflowType = $this->resolveWorkflowType($payload['workflow_type'] ?? $workflowType);
            if ($isApprovalStep && ! $this->canApproveCompletionByWorkflow($actor, $workflowType)) {
                throw ValidationException::withMessages([
                    'status' => $this->completionPermissionMessage($workflowType),
                ]);
            }
            $dependencyTaskId = array_key_exists('dependency_task_id', $payload)
                ? ($payload['dependency_task_id'] !== null ? (int) $payload['dependency_task_id'] : null)
                : ($task->dependency_task_id !== null ? (int) $task->dependency_task_id : null);

            $this->payloadService->ensureDependencyReady($dependencyTaskId, $targetStatus, $task->id);
            if ($isApprovalStep) {
                unset($payload['status'], $payload['completed_at']);
            } else {
                $this->applyAutoDatesForStatus($payload, $targetStatus, $task);
            }

            if ($this->slaService->shouldRefreshSlaSnapshot($task, $payload)) {
                $picId = (int) ($payload['pic_id'] ?? $task->pic_id);
                $taskType = (string) ($payload['type'] ?? $task->type);

                $payload['sla_standard_hours'] = $this->slaService->resolveSlaStandardHours(
                    $phase,
                    $picId,
                    $taskType,
                    $this->slaService->resolveSlaReferenceDate($payload, $task),
                );
            }

            $task->fill($payload);
            $task->save();

            if ($isApprovalStep) {
                $this->approvalService->approve($actor, $task, $starRating, $approvalComment);
            }

            if ($coPicIds !== null) {
                $this->payloadService->syncCoPics($task, $coPicIds);
            }

            $task = $task->refresh();
            $overloadWarning = $this->overloadService->warnIfPicOverloaded($actor, $task);

            return new TaskMutationResult(
                task: $this->queryService->hydrateTask($task->refresh()),
                overloadWarning: $overloadWarning,
                attachments: $this->addAttachments($actor, $task, $attributes['attachments'] ?? []),
            );
        });
    }

    /**
     * Chuyen task sang trang thai dang thuc hien neu vuot qua duoc dependency lock.
     */
    public function start(User $actor, Task $task): Task
    {
        Gate::forUser($actor)->authorize('start', $task);

        $currentStatus = $this->normalizeStatus($task->status);
        if ($currentStatus !== TaskStatus::Pending->value) {
            throw ValidationException::withMessages([
                'status' => 'Chỉ task ở trạng thái Chưa bắt đầu mới được bắt đầu.',
            ]);
        }

        if (! $this->canPicControlExecutionFlow($actor, $task)) {
            throw ValidationException::withMessages([
                'status' => 'Chỉ PIC của task mới được phép bắt đầu công việc.',
            ]);
        }

        $dependencyTaskId = $task->dependency_task_id !== null ? (int) $task->dependency_task_id : null;
        $this->payloadService->ensureDependencyReady($dependencyTaskId, TaskStatus::InProgress->value, $task->id);

        $task->forceFill([
            'status' => TaskStatus::InProgress->value,
            'started_at' => $task->started_at ?? now(),
        ])->save();

        return $this->queryService->hydrateTask($task->refresh());
    }

    /**
     * Chuyen task sang trang thai cho duyet.
     */
    public function submitForApproval(User $actor, Task $task): Task
    {
        Gate::forUser($actor)->authorize('update', $task);

        $currentStatus = $this->normalizeStatus($task->status);
        if (! in_array($currentStatus, [TaskStatus::InProgress->value, TaskStatus::Late->value], true)) {
            throw ValidationException::withMessages([
                'status' => 'Chỉ task đang thực hiện mới được chuyển sang Chờ duyệt.',
            ]);
        }

        if (! $this->canPicControlExecutionFlow($actor, $task)) {
            throw ValidationException::withMessages([
                'status' => 'Chỉ PIC của task mới được gửi duyệt.',
            ]);
        }

        $dependencyTaskId = $task->dependency_task_id !== null ? (int) $task->dependency_task_id : null;
        $this->payloadService->ensureDependencyReady($dependencyTaskId, TaskStatus::WaitingApproval->value, $task->id);

        $task->forceFill([
            'status' => TaskStatus::WaitingApproval->value,
            'started_at' => $task->started_at ?? now(),
        ])->save();

        // Tạo notification cho các Leader để họ biết có task cần duyệt
        try {
            $task->loadMissing('phase:id,project_id');
            $taskName = trim((string) $task->name) !== '' ? $task->name : "Task #{$task->id}";
            $body = "Task \"{$taskName}\" đã được gửi duyệt và cần leader phê duyệt.";

            // Lấy leaders được chỉ định cho project của task (qua phase)
            $projectLeaders = $task->phase->project->projectLeaders()->with('user')->get();
            $leaders = $projectLeaders
                ->pluck('user')
                ->filter()
                ->unique('id')
                ->values();

            foreach ($leaders as $leader) {
                SystemNotification::query()->create([
                    'user_id' => $leader->id,
                    'type' => SystemNotificationType::ApprovalRequestLeader->value,
                    'channel' => NotificationChannel::Both->value,
                    'title' => 'Yêu cầu phê duyệt',
                    'body' => $body,
                    'notifiable_type' => Task::class,
                    'notifiable_id' => $task->id,
                    'status' => NotificationStatus::Pending->value,
                    'created_at' => now(),
                ]);
            }

            $telegramRecipients = $leaders->filter(function (User $leader): bool {
                return trim((string) $leader->telegram_id) !== '';
            });

            if ($telegramRecipients->isNotEmpty()) {
                foreach ($telegramRecipients as $leader) {
                    try {
                        Notification::send($leader, new TaskApprovalRequestLeaderNotification($task, $actor));
                    } catch (\Throwable $exception) {
                        report($exception);
                    }
                }
            }
        } catch (\Exception $e) {
            // Không phá vỡ luồng chính nếu gửi notification thất bại; ghi log nếu cần
        }

        return $this->queryService->hydrateTask($task->refresh());
    }

    /**
     * Ghi nhan phe duyet task theo workflow hien tai.
     */
    public function approve(
        User $actor,
        Task $task,
        ?int $starRating = null,
        ?string $comment = null,
    ): Task {
        Gate::forUser($actor)->authorize('approve', $task);

        $this->approvalService->approve($actor, $task, $starRating, $comment);

        return $this->queryService->hydrateTask($task->refresh());
    }

    /**
     * Ghi nhan tu choi task va dua task ve trang thai dang thuc hien.
     */
    public function reject(User $actor, Task $task, string $comment): Task
    {
        Gate::forUser($actor)->authorize('approve', $task);

        $this->approvalService->reject($actor, $task, $comment);

        return $this->queryService->hydrateTask($task->refresh());
    }

    /**
     * Xoa task.
     */
    public function delete(User $actor, Task $task): bool
    {
        Gate::forUser($actor)->authorize('delete', $task);

        return DB::transaction(function () use ($task): bool {
            // Logic xoa phu (attachment, etc.) neu can
            return $task->delete();
        });
    }

    private function sendAssignmentNotification(User $actor, Task $task): void
    {
        $task->loadMissing(['pic:id,name,telegram_id', 'phase.project:id,name']);
        $pic = $task->pic;

        if ($pic === null || trim((string) $pic->telegram_id) === '') {
            return;
        }

        try {
            Notification::send($pic, new TaskAssignedNotification($task, $actor));
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Danh dau task tre han theo BR-004 de co the goi trong Command/Cron.
     */
    public function markLateTasks(): int
    {
        return Task::query()
            ->where('deadline', '<', now())
            ->whereNotIn('status', [TaskStatus::Completed->value, TaskStatus::Late->value])
            ->update([
                'status' => TaskStatus::Late->value,
                'updated_at' => now(),
            ]);
    }

    /**
     * Them tep dinh kem cho task.
     *
     * @param  array<int, \Illuminate\Http\UploadedFile>  $files
     * @return Collection<int, TaskAttachment>
     */
    public function addAttachments(User $actor, Task $task, array $files): Collection
    {
        $files = collect($files)
            ->filter(function (mixed $file): bool {
                return $file instanceof UploadedFile;
            })
            ->values()
            ->all();

        if ($files === []) {
            return collect();
        }

        $projectId = $task->phase()->value('project_id');
        $currentMaxAttachmentVersion = (int) ($task->attachments()->max('version') ?? 0);

        return collect($files)->values()->map(function ($file, int $index) use ($actor, $task, $projectId, $currentMaxAttachmentVersion) {
            $attachment = TaskAttachment::create([
                'task_id' => $task->id,
                'uploader_id' => $actor->id,
                'original_name' => $file->getClientOriginalName(),
                'stored_path' => '',
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
                'disk' => config('media-library.disk_name'),
                'version' => $currentMaxAttachmentVersion + $index + 1,
            ]);

            $attachmentMedia = $attachment->addMedia($file)
                ->usingFileName($file->hashName())
                ->toMediaCollection('attachment');

            $attachment->forceFill([
                'stored_path' => $attachmentMedia->getPathRelativeToRoot(),
                'disk' => $attachmentMedia->disk,
                'mime_type' => $attachmentMedia->mime_type,
                'size_bytes' => $attachmentMedia->size,
            ])->save();

            $this->syncTaskAttachmentToDocument(
                actor: $actor,
                task: $task,
                attachment: $attachment,
                attachmentMedia: $attachmentMedia,
                projectId: $projectId,
            );

            return $attachment->refresh();
        });
    }

    /**
     * Xoa tep dinh kem.
     */
    public function deleteAttachment(User $actor, TaskAttachment $attachment): bool
    {
        Gate::forUser($actor)->authorize('delete', $attachment);

        return $attachment->delete();
    }

    /**
     * Tu dong bo sung thoi diem bat dau/hoan thanh theo trang thai task.
     *
     * @param  array<string, mixed>  $payload
     */
    private function applyAutoDatesForStatus(array &$payload, string $targetStatus, ?Task $task = null): void
    {
        if (
            $targetStatus === TaskStatus::InProgress->value
            && ! array_key_exists('started_at', $payload)
            && ($task === null || $task->started_at === null)
        ) {
            $payload['started_at'] = now();
        }

        if (
            $targetStatus === TaskStatus::Completed->value
            && ! array_key_exists('completed_at', $payload)
            && ($task === null || $task->completed_at === null)
        ) {
            $payload['completed_at'] = now();
        }

        if (
            $task !== null
            && $targetStatus !== TaskStatus::Completed->value
            && ! array_key_exists('completed_at', $payload)
        ) {
            $taskStatus = $this->normalizeStatus($task->status);
            if ($taskStatus === TaskStatus::Completed->value) {
                $payload['completed_at'] = null;
                $payload['sla_met'] = null;
                $payload['delay_days'] = 0;
            }
        }
    }

    /**
     * Chuan hoa gia tri status ve string de xu ly o service.
     */
    private function normalizeStatus(mixed $status): string
    {
        return $status instanceof \BackedEnum
            ? (string) $status->value
            : (string) $status;
    }

    /**
     * Chuan hoa workflow ve gia tri string.
     */
    private function resolveWorkflowType(mixed $workflowType): string
    {
        if ($workflowType instanceof \BackedEnum) {
            return (string) $workflowType->value;
        }

        return (string) $workflowType;
    }

    /**
     * Kiem tra actor co duoc phe duyet hoan thanh theo workflow hay khong.
     */
    private function canApproveCompletionByWorkflow(User $actor, string $workflowType): bool
    {
        if ($actor->hasRole('super_admin')) {
            return true;
        }

        if ($workflowType === TaskWorkflowType::Single->value) {
            return $actor->hasRole('leader');
        }

        if ($workflowType === TaskWorkflowType::Double->value) {
            return $actor->hasAnyRole(['leader', 'ceo']);
        }

        return false;
    }

    /**
     * Tra ve thong diep loi quyen theo workflow.
     */
    private function completionPermissionMessage(string $workflowType): string
    {
        if ($workflowType === TaskWorkflowType::Single->value) {
            return 'Workflow 1 cấp chỉ Leader mới được chuyển task sang Hoàn thành.';
        }

        return 'Workflow 2 cấp chỉ Leader/CEO mới được chuyển task sang Hoàn thành.';
    }

    /**
     * Kiem tra actor co phai PIC (hoac super_admin) de dieu huong flow thuc thi hay khong.
     */
    private function canPicControlExecutionFlow(User $actor, Task $task): bool
    {
        if ($actor->hasRole('super_admin')) {
            return true;
        }

        return (int) $actor->id === (int) $task->pic_id;
    }

    /**
     * Dam bao task chi duoc chuyen trang thai theo dung ma tran workflow.
     */
    private function assertAllowedStatusTransition(string $currentStatus, string $targetStatus): void
    {
        if ($currentStatus === $targetStatus) {
            return;
        }

        if ($currentStatus === TaskStatus::Completed->value) {
            throw ValidationException::withMessages([
                'status' => 'Task đã Hoàn thành không thể chuyển ngược về trạng thái khác.',
            ]);
        }

        if ($targetStatus === TaskStatus::Late->value) {
            throw ValidationException::withMessages([
                'status' => 'Trạng thái Trễ hạn do hệ thống tự động cập nhật, không chỉnh tay.',
            ]);
        }

        if ($targetStatus === TaskStatus::Pending->value) {
            throw ValidationException::withMessages([
                'status' => 'Không thể chuyển ngược công việc về trạng thái "Chưa bắt đầu".',
            ]);
        }

        if (
            $targetStatus === TaskStatus::InProgress->value
            && ! in_array($currentStatus, [TaskStatus::Pending->value, TaskStatus::Late->value], true)
        ) {
            throw ValidationException::withMessages([
                'status' => 'Chỉ task Chưa bắt đầu hoặc Trễ hạn mới được chuyển sang Đang thực hiện.',
            ]);
        }

        if (
            $targetStatus === TaskStatus::InProgress->value
            && in_array($currentStatus, [TaskStatus::Pending->value, TaskStatus::Late->value], true)
        ) {
            return;
        }

        if (
            $targetStatus === TaskStatus::WaitingApproval->value
            && ! in_array($currentStatus, [TaskStatus::InProgress->value, TaskStatus::Late->value], true)
        ) {
            throw ValidationException::withMessages([
                'status' => 'Chỉ task đang thực hiện mới được chuyển sang Chờ duyệt.',
            ]);
        }

        if (
            $targetStatus === TaskStatus::WaitingApproval->value
            && in_array($currentStatus, [TaskStatus::InProgress->value, TaskStatus::Late->value], true)
        ) {
            return;
        }

        if (
            $targetStatus === TaskStatus::Completed->value
            && $currentStatus !== TaskStatus::WaitingApproval->value
        ) {
            throw ValidationException::withMessages([
                'status' => 'Chỉ có thể chuyển sang Hoàn thành từ trạng thái Chờ duyệt.',
            ]);
        }

        if ($targetStatus === TaskStatus::Completed->value) {
            return;
        }

        throw ValidationException::withMessages([
            'status' => 'Luồng chuyển trạng thái không hợp lệ.',
        ]);
    }

    /**
     * Dong bo tep dinh kem task sang document/document_version de quan ly tap trung.
     */
    private function syncTaskAttachmentToDocument(
        User $actor,
        Task $task,
        TaskAttachment $attachment,
        Media $attachmentMedia,
        ?int $projectId,
    ): void {
        $this->documentService->createFromTaskAttachment($actor, $task, $attachment, $attachmentMedia, $projectId);
    }
}
