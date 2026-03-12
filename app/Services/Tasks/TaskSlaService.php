<?php

namespace App\Services\Tasks;

use App\Enums\SlaProjectType;
use App\Enums\SlaTaskType;
use App\Models\Phase;
use App\Models\SlaConfig;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class TaskSlaService
{
    /**
     * Xac dinh gio SLA snapshot theo BR-005.
     */
    public function resolveSlaStandardHours(
        Phase $phase,
        int $picId,
        string $taskType,
        Carbon $referenceDate,
    ): ?float {
        $pic = User::query()
            ->select(['id', 'department_id'])
            ->find($picId);

        if (! $pic instanceof User) {
            throw ValidationException::withMessages([
                'pic_id' => 'PIC khong ton tai.',
            ]);
        }

        $projectType = $phase->project->type instanceof \BackedEnum ? $phase->project->type->value : (string) $phase->project->type;
        $referenceDateText = $referenceDate->toDateString();

        $query = SlaConfig::query()
            ->whereIn('task_type', [$taskType, SlaTaskType::All->value])
            ->whereIn('project_type', [$projectType, SlaProjectType::All->value])
            ->effectiveAt($referenceDateText);

        if ($pic->department_id !== null) {
            $query->where(function (Builder $builder) use ($pic): void {
                $builder
                    ->where('department_id', $pic->department_id)
                    ->orWhereNull('department_id');
            });
        } else {
            $query->whereNull('department_id');
        }

        $match = $query
            ->orderByRaw('CASE WHEN department_id IS NULL THEN 1 ELSE 0 END')
            ->orderByRaw('CASE WHEN task_type = ? THEN 0 ELSE 1 END', [$taskType])
            ->orderByRaw('CASE WHEN project_type = ? THEN 0 ELSE 1 END', [$projectType])
            ->orderByDesc('effective_date')
            ->first(['standard_hours']);

        return $match instanceof SlaConfig
            ? (float) $match->standard_hours
            : null;
    }

    /**
     * Tinh ngay tham chieu de lookup SLA.
     *
     * @param  array<string, mixed>  $payload
     */
    public function resolveSlaReferenceDate(array $payload, ?Task $task = null): Carbon
    {
        if (array_key_exists('deadline', $payload) && $payload['deadline'] instanceof Carbon) {
            return $payload['deadline']->copy();
        }

        if ($task?->deadline instanceof Carbon) {
            return $task->deadline->copy();
        }

        return now();
    }

    /**
     * Xac dinh khi nao can snapshot lai SLA trong luc cap nhat task.
     *
     * @param  array<string, mixed>  $payload
     */
    public function shouldRefreshSlaSnapshot(Task $task, array $payload): bool
    {
        if ($task->sla_standard_hours === null) {
            return true;
        }

        if (array_key_exists('phase_id', $payload) && (int) $payload['phase_id'] !== (int) $task->phase_id) {
            return true;
        }

        if (array_key_exists('pic_id', $payload) && (int) $payload['pic_id'] !== (int) $task->pic_id) {
            return true;
        }

        if (array_key_exists('type', $payload) && (string) $payload['type'] !== ($task->type instanceof \BackedEnum ? $task->type->value : (string) $task->type)) {
            return true;
        }

        if (array_key_exists('deadline', $payload) && $payload['deadline'] instanceof Carbon) {
            $currentDeadline = $task->deadline?->toDateTimeString();
            $incomingDeadline = $payload['deadline']->toDateTimeString();

            if ($incomingDeadline !== $currentDeadline) {
                return true;
            }
        }

        return false;
    }
}
