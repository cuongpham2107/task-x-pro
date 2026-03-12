<?php

namespace App\Services\Tasks;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Enums\TaskWorkflowType;
use App\Enums\UserStatus;
use App\Models\Phase;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class TaskPayloadService
{
    /**
     * Chuan hoa payload task va validate enum/field bat buoc.
     *
     * @return array<string, mixed>
     */
    public function normalizedTaskAttributes(array $attributes, ?Task $task): array
    {
        $allowedFields = [
            'phase_id',
            'name',
            'description',
            'type',
            'status',
            'priority',
            'progress',
            'pic_id',
            'dependency_task_id',
            'deadline',
            'started_at',
            'completed_at',
            'deliverable_url',
            'issue_note',
            'recommendation',
            'workflow_type',
            'sla_standard_hours',
            'sla_met',
            'delay_days',
        ];

        $payload = collect($attributes)
            ->only($allowedFields)
            ->toArray();

        if ($task === null) {
            $requiredFields = ['phase_id', 'name', 'type', 'pic_id', 'deadline'];

            foreach ($requiredFields as $requiredField) {
                if (! array_key_exists($requiredField, $payload) || $payload[$requiredField] === null || $payload[$requiredField] === '') {
                    throw ValidationException::withMessages([
                        $requiredField => 'Truong nay la bat buoc khi tao task.',
                    ]);
                }
            }
        }

        if (array_key_exists('type', $payload) && ! in_array((string) $payload['type'], TaskType::values(), true)) {
            throw ValidationException::withMessages([
                'type' => 'Loai task khong hop le.',
            ]);
        }

        if (array_key_exists('status', $payload) && ! in_array((string) $payload['status'], TaskStatus::values(), true)) {
            throw ValidationException::withMessages([
                'status' => 'Trang thai task khong hop le.',
            ]);
        }

        if (array_key_exists('priority', $payload) && ! in_array((string) $payload['priority'], TaskPriority::values(), true)) {
            throw ValidationException::withMessages([
                'priority' => 'Muc uu tien task khong hop le.',
            ]);
        }

        if (array_key_exists('workflow_type', $payload) && ! in_array((string) $payload['workflow_type'], TaskWorkflowType::values(), true)) {
            throw ValidationException::withMessages([
                'workflow_type' => 'Workflow khong hop le.',
            ]);
        }

        $intFields = ['phase_id', 'pic_id', 'dependency_task_id', 'progress'];
        foreach ($intFields as $field) {
            if (! array_key_exists($field, $payload)) {
                continue;
            }

            if ($payload[$field] === '' || $payload[$field] === null) {
                if ($field === 'dependency_task_id') {
                    $payload[$field] = null;

                    continue;
                }

                if ($field === 'progress') {
                    $payload[$field] = 0;

                    continue;
                }

                throw ValidationException::withMessages([
                    $field => 'Truong nay khong duoc de trong.',
                ]);
            }

            $payload[$field] = (int) $payload[$field];
        }

        $decimalFields = ['sla_standard_hours', 'delay_days'];
        foreach ($decimalFields as $field) {
            if (! array_key_exists($field, $payload)) {
                continue;
            }

            if ($payload[$field] === '' || $payload[$field] === null) {
                $payload[$field] = $field === 'delay_days'
                    ? 0.0
                    : null;

                continue;
            }

            $payload[$field] = (float) $payload[$field];
        }

        if (array_key_exists('sla_met', $payload) && $payload['sla_met'] !== null) {
            $payload['sla_met'] = (bool) $payload['sla_met'];
        }

        $dateTimeFields = ['deadline', 'started_at', 'completed_at'];
        foreach ($dateTimeFields as $field) {
            if (! array_key_exists($field, $payload)) {
                continue;
            }

            if ($payload[$field] === '' || $payload[$field] === null) {
                $payload[$field] = null;

                continue;
            }

            $payload[$field] = $payload[$field] instanceof Carbon
                ? $payload[$field]
                : Carbon::parse((string) $payload[$field]);
        }

        if (array_key_exists('deadline', $payload) && $payload['deadline'] === null) {
            throw ValidationException::withMessages([
                'deadline' => 'Deadline khong duoc de trong.',
            ]);
        }

        return $payload;
    }

    /**
     * Dam bao dependency da completed truoc khi cho task chuyen trang thai.
     */
    public function ensureDependencyReady(?int $dependencyTaskId, string $targetStatus, ?int $taskId = null): void
    {
        if ($dependencyTaskId === null) {
            return;
        }

        if ($taskId !== null && $dependencyTaskId === $taskId) {
            throw ValidationException::withMessages([
                'dependency_task_id' => 'Task khong the phu thuoc chinh no.',
            ]);
        }

        $dependencyTask = Task::query()
            ->select(['id', 'status'])
            ->find($dependencyTaskId);

        if (! $dependencyTask instanceof Task) {
            throw ValidationException::withMessages([
                'dependency_task_id' => 'Task phu thuoc khong ton tai.',
            ]);
        }

        $dependencyStatus = $dependencyTask->status instanceof \BackedEnum
            ? (string) $dependencyTask->status->value
            : (string) $dependencyTask->status;

        if (
            in_array(
                $targetStatus,
                [
                    TaskStatus::InProgress->value,
                    TaskStatus::WaitingApproval->value,
                    TaskStatus::Completed->value,
                ],
                true,
            )
            && $dependencyStatus !== TaskStatus::Completed->value
        ) {
            throw ValidationException::withMessages([
                'dependency_task_id' => 'Task phu thuoc chua hoan thanh nen khong the tiep tuc.',
            ]);
        }
    }

    /**
     * Lay phase va project lien quan tu payload task.
     */
    public function resolvePhaseForTaskPayload(int $phaseId): Phase
    {
        $phase = Phase::query()
            ->with('project:id,type')
            ->find($phaseId);

        if (! $phase instanceof Phase) {
            throw ValidationException::withMessages([
                'phase_id' => 'Phase khong ton tai.',
            ]);
        }

        return $phase;
    }

    /**
     * Dong bo danh sach PIC phoi hop vao bang pivot task_co_pics.
     */
    public function syncCoPics(Task $task, array $coPicIds): void
    {
        $normalizedCoPicIds = collect($coPicIds)
            ->filter(function (mixed $coPicId): bool {
                return $coPicId !== null && $coPicId !== '';
            })
            ->map(function (mixed $coPicId): int {
                return (int) $coPicId;
            })
            ->unique()
            ->reject(function (int $coPicId) use ($task): bool {
                return $coPicId === (int) $task->pic_id;
            })
            ->values();

        if ($normalizedCoPicIds->isEmpty()) {
            $task->coPics()->sync([]);

            return;
        }

        $validCoPicIds = User::query()
            ->whereIn('id', $normalizedCoPicIds)
            ->where('status', UserStatus::Active->value)
            ->pluck('id')
            ->map(function (mixed $coPicId): int {
                return (int) $coPicId;
            })
            ->values();

        if ($validCoPicIds->count() !== $normalizedCoPicIds->count()) {
            throw ValidationException::withMessages([
                'co_pic_ids' => 'Danh sach PIC phoi hop co user khong hop le hoac da ngung hoat dong.',
            ]);
        }

        $assignedAt = now();
        $syncPayload = $validCoPicIds
            ->mapWithKeys(function (int $coPicId) use ($assignedAt): array {
                return [
                    $coPicId => [
                        'assigned_at' => $assignedAt,
                    ],
                ];
            })
            ->all();

        $task->coPics()->sync($syncPayload);
    }
}
