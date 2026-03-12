<?php

namespace App\Services\Tasks;

use App\Enums\ApprovalAction;
use App\Enums\ApprovalLevel;
use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Enums\SystemNotificationType;
use App\Enums\TaskStatus;
use App\Enums\TaskWorkflowType;
use App\Models\ApprovalLog;
use App\Models\SystemNotification;
use App\Models\Task;
use App\Models\User;
use App\Notifications\ApprovalResults;
use App\Notifications\TaskRejectedNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class TaskApprovalService
{
    /**
     * Ghi nhan phe duyet task va xu ly workflow 1 cap / 2 cap.
     */
    public function approve(User $actor, Task $task, ?int $starRating = null, ?string $comment = null): void
    {
        $taskStatus = $this->normalizeStatus($task->status);
        $workflowType = $this->normalizeWorkflowType($task->workflow_type);

        if ($taskStatus !== TaskStatus::WaitingApproval->value) {
            throw ValidationException::withMessages([
                'status' => 'Task hiện tại không ở trạng thái chờ phê duyệt.',
            ]);
        }

        if (! $this->canApproveByWorkflow($actor, $workflowType)) {
            throw ValidationException::withMessages([
                'status' => $workflowType === TaskWorkflowType::Single->value
                    ? 'Workflow 1 cấp chỉ Leader được phê duyệt hoàn thành.'
                    : 'Workflow 2 cấp chỉ Leader hoặc CEO được phê duyệt hoàn thành.',
            ]);
        }

        if ($starRating !== null && ($starRating < 1 || $starRating > 5)) {
            throw ValidationException::withMessages([
                'star_rating' => 'Điểm đánh giá phải nằm trong khoảng 1 đến 5.',
            ]);
        }

        $approvalLevel = $this->resolveApprovalLevel($actor);

        if (
            $workflowType === TaskWorkflowType::Double->value
            && $approvalLevel === ApprovalLevel::Ceo->value
            && ! $this->hasApprovedAtLevel($task, ApprovalLevel::Leader->value)
        ) {
            throw ValidationException::withMessages([
                'approval' => 'Workflow 2 cấp cần leader duyệt trước khi CEO duyệt.',
            ]);
        }

        if ($this->hasApprovedAtLevel($task, $approvalLevel)) {
            throw ValidationException::withMessages([
                'approval' => 'Cấp duyệt này đã được phê duyệt trước đó.',
            ]);
        }

        ApprovalLog::query()->create([
            'task_id' => $task->id,
            'reviewer_id' => $actor->id,
            'approval_level' => $approvalLevel,
            'action' => ApprovalAction::Approved->value,
            'star_rating' => $starRating,
            'comment' => $comment,
            'created_at' => now(),
        ]);

        if (
            $workflowType === TaskWorkflowType::Single->value
            || $approvalLevel === ApprovalLevel::Ceo->value
        ) {
            $task->forceFill([
                'status' => TaskStatus::Completed->value,
                'completed_at' => $task->completed_at ?? now(),
            ])->save();

            return;
        }

        $task->forceFill([
            'status' => TaskStatus::WaitingApproval->value,
        ])->save();

        // Neu la workflow 2 cap va cap duyet hien tai la leader, gui thong bao cho CEO
        if ($workflowType === TaskWorkflowType::Double->value && $approvalLevel === ApprovalLevel::Leader->value) {
            $this->sendCEOApprovalNotification($task, $actor);
        }
    }

    /**
     * Ghi nhan tu choi task va dua task ve trang thai dang thuc hien.
     */
    public function reject(User $actor, Task $task, string $comment): void
    {
        $taskStatus = $this->normalizeStatus($task->status);
        $workflowType = $this->normalizeWorkflowType($task->workflow_type);

        if ($taskStatus !== TaskStatus::WaitingApproval->value) {
            throw ValidationException::withMessages([
                'status' => 'Chỉ task ở trạng thái Chờ duyệt mới được từ chối.',
            ]);
        }

        if (! $this->canApproveByWorkflow($actor, $workflowType)) {
            throw ValidationException::withMessages([
                'status' => $workflowType === TaskWorkflowType::Single->value
                    ? 'Workflow 1 cấp chỉ Leader được từ chối duyệt.'
                    : 'Workflow 2 cấp chỉ Leader hoặc CEO được từ chối duyệt.',
            ]);
        }

        $trimmedComment = trim($comment);
        if ($trimmedComment === '') {
            throw ValidationException::withMessages([
                'comment' => 'Nội dung từ chối là bắt buộc.',
            ]);
        }

        $approvalLevel = $this->resolveApprovalLevel($actor);

        if (
            $workflowType === TaskWorkflowType::Double->value
            && $approvalLevel === ApprovalLevel::Ceo->value
            && ! $this->hasApprovedAtLevel($task, ApprovalLevel::Leader->value)
        ) {
            throw ValidationException::withMessages([
                'approval' => 'Workflow 2 cấp yêu cầu Leader đánh giá đạt trước khi CEO đánh giá.',
            ]);
        }

        if ($this->hasApprovedAtLevel($task, $approvalLevel)) {
            throw ValidationException::withMessages([
                'approval' => 'Cấp duyệt này đã đánh giá đạt trước đó, không thể đánh giá lại trong cùng vòng duyệt.',
            ]);
        }

        ApprovalLog::query()->create([
            'task_id' => $task->id,
            'reviewer_id' => $actor->id,
            'approval_level' => $approvalLevel,
            'action' => ApprovalAction::Rejected->value,
            'comment' => $trimmedComment,
            'created_at' => now(),
        ]);

        $task->forceFill([
            'status' => TaskStatus::InProgress->value,
            'started_at' => $task->started_at ?? now(),
            'completed_at' => null,
            'sla_met' => null,
            'delay_days' => 0,
            'progress' => min((int) $task->progress, 99),
        ])->save();

        $this->createRejectionNotification($actor, $task, $trimmedComment);
    }

    /**
     * Xac dinh cap duyet dua theo vai tro cua user.
     */
    private function resolveApprovalLevel(User $actor): string
    {
        return $actor->hasAnyRole(['ceo', 'super_admin'])
            ? ApprovalLevel::Ceo->value
            : ApprovalLevel::Leader->value;
    }

    /**
     * Kiem tra task da co log approved o cap duyet chi dinh hay chua.
     */
    private function hasApprovedAtLevel(Task $task, string $approvalLevel): bool
    {
        $lastRejectedLogId = $task->approvalLogs()
            ->where('action', ApprovalAction::Rejected->value)
            ->max('id');

        return $task->approvalLogs()
            ->when($lastRejectedLogId !== null, function ($query) use ($lastRejectedLogId): void {
                $query->where('id', '>', (int) $lastRejectedLogId);
            })
            ->where('approval_level', $approvalLevel)
            ->where('action', ApprovalAction::Approved->value)
            ->exists();
    }

    /**
     * Kiem tra actor co duoc phe duyet theo workflow hay khong.
     */
    private function canApproveByWorkflow(User $actor, string $workflowType): bool
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
     * Tao thong bao cho PIC khi task bi tu choi phe duyet.
     */
    private function createRejectionNotification(User $actor, Task $task, string $reason): void
    {
        if ($task->pic_id === null) {
            return;
        }

        $task->loadMissing('pic:id,name,telegram_id');
        $actorName = trim((string) $actor->name) !== '' ? $actor->name : 'Người duyệt';
        $taskName = trim((string) $task->name) !== '' ? $task->name : "Task #{$task->id}";
        $body = "Task \"{$taskName}\" vừa bị từ chối duyệt bởi {$actorName}. Lý do: {$reason}";

        SystemNotification::query()->create([
            'user_id' => (int) $task->pic_id,
            'type' => SystemNotificationType::TaskRejected->value,
            'channel' => NotificationChannel::Both->value,
            'title' => 'Task bị từ chối duyệt',
            'body' => $body,
            'notifiable_type' => Task::class,
            'notifiable_id' => $task->id,
            'status' => NotificationStatus::Pending->value,
            'created_at' => now(),
        ]);

        $pic = $task->pic;
        if ($pic !== null && trim((string) $pic->telegram_id) !== '') {
            Notification::send($pic, new TaskRejectedNotification($task, $actor, $reason));
        }
    }

    /**
     * Chuan hoa gia tri status de xu ly nhat quan.
     */
    private function normalizeStatus(mixed $status): string
    {
        return $status instanceof \BackedEnum
            ? (string) $status->value
            : (string) $status;
    }

    /**
     * Chuan hoa workflow type de xu ly nhat quan.
     */
    private function normalizeWorkflowType(mixed $workflowType): string
    {
        return $workflowType instanceof \BackedEnum
            ? (string) $workflowType->value
            : (string) $workflowType;
    }

    /**
     * Gui thong bao cho CEO khi task yeu cau duyet 2 cap.
     */
    public function sendCEOApprovalNotification(Task $task, User $leader): void
    {
        $workflowType = $this->normalizeWorkflowType($task->workflow_type);

        // Chi gui notification khi task la 2 cap va da duoc leader duyet
        if ($workflowType !== TaskWorkflowType::Double->value) {
            return;
        }

        if (! $this->hasApprovedAtLevel($task, ApprovalLevel::Leader->value)) {
            return;
        }

        // Lay thong tin CEO
        $ceos = User::role('ceo')->get();

        if ($ceos->isEmpty()) {
            // Log warning hoac xu ly khi khong tim thay CEO
            return;
        }

        $leaderName = trim((string) $leader->name) !== '' ? $leader->name : 'Leader';
        $taskName = trim((string) $task->name) !== '' ? $task->name : "Task #{$task->id}";
        $body = "Task \"{$taskName}\" đã được {$leaderName} phê duyệt và cần CEO phê duyệt để hoàn tất quy trình.";

        foreach ($ceos as $ceo) {
            SystemNotification::query()->create([
                'user_id' => $ceo->id,
                'type' => SystemNotificationType::ApprovalRequestCeo->value,
                'channel' => NotificationChannel::Both->value,
                'title' => 'Yêu cầu phê duyệt từ Leader',
                'body' => $body,
                'notifiable_type' => Task::class,
                'notifiable_id' => $task->id,
                'status' => NotificationStatus::Pending->value,
                'created_at' => now(),
            ]);
        }

        $telegramRecipients = $ceos->filter(function (User $ceo): bool {
            return trim((string) $ceo->telegram_id) !== '';
        });

        if ($telegramRecipients->isNotEmpty()) {
            Notification::send($telegramRecipients, new ApprovalResults($task, $leader));
        }
    }
}
