<?php

namespace App\Services\SlaConfigs;

use App\Enums\SlaProjectType;
use App\Enums\SlaTaskType;
use App\Models\SlaConfig;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SlaConfigMutationService
{
    /**
     * Tao cau hinh SLA moi.
     */
    public function create(User $actor, array $attributes): SlaConfig
    {
        Gate::forUser($actor)->authorize('create', SlaConfig::class);

        $payload = $this->normalizedAttributes($attributes, $actor);
        $this->assertNoOverlappingEffectivePeriod($payload, null);

        $slaConfig = SlaConfig::query()->create($payload);

        return $slaConfig->load([
            'department:id,name,code',
            'creator:id,name,email,avatar',
        ]);
    }

    /**
     * Cap nhat cau hinh SLA.
     */
    public function update(User $actor, SlaConfig $slaConfig, array $attributes): SlaConfig
    {
        Gate::forUser($actor)->authorize('update', $slaConfig);

        $payload = $this->normalizedAttributes($attributes, $actor, $slaConfig);
        $this->assertNoOverlappingEffectivePeriod($payload, $slaConfig->id);

        $slaConfig->fill($payload);
        $slaConfig->save();

        return $slaConfig->load([
            'department:id,name,code',
            'creator:id,name,email,avatar',
        ]);
    }

    /**
     * Xoa cau hinh SLA.
     */
    public function delete(User $actor, SlaConfig $slaConfig): void
    {
        Gate::forUser($actor)->authorize('delete', $slaConfig);
        $slaConfig->delete();
    }

    /**
     * Chuan hoa payload truoc khi ghi DB.
     *
     * @return array{
     *     department_id: ?int,
     *     task_type: string,
     *     project_type: string,
     *     standard_hours: float,
     *     effective_date: string,
     *     expired_date: ?string,
     *     note: ?string,
     *     created_by: int
     * }
     */
    private function normalizedAttributes(array $attributes, User $actor, ?SlaConfig $current = null): array
    {
        $departmentId = $attributes['department_id'] ?? null;
        $taskType = trim((string) ($attributes['task_type'] ?? SlaTaskType::All->value));
        $projectType = trim((string) ($attributes['project_type'] ?? SlaProjectType::All->value));
        $effectiveDate = Carbon::parse((string) ($attributes['effective_date'] ?? now()->toDateString()))->toDateString();
        $expiredDateValue = $attributes['expired_date'] ?? null;
        $expiredDate = $expiredDateValue !== null && $expiredDateValue !== ''
            ? Carbon::parse((string) $expiredDateValue)->toDateString()
            : null;

        if ($expiredDate !== null && $expiredDate < $effectiveDate) {
            throw ValidationException::withMessages([
                'expiredDate' => 'Ngay ket thuc hieu luc phai lon hon hoac bang ngay bat dau hieu luc.',
            ]);
        }

        if (! in_array($taskType, SlaTaskType::values(), true)) {
            $taskType = SlaTaskType::All->value;
        }

        if (! in_array($projectType, SlaProjectType::values(), true)) {
            $projectType = SlaProjectType::All->value;
        }

        return [
            'department_id' => $departmentId !== null && $departmentId !== ''
                ? (int) $departmentId
                : null,
            'task_type' => $taskType,
            'project_type' => $projectType,
            'standard_hours' => (float) ($attributes['standard_hours'] ?? 0),
            'effective_date' => $effectiveDate,
            'expired_date' => $expiredDate,
            'note' => $this->nullableTrimmedString($attributes['note'] ?? null),
            'created_by' => $current?->created_by ?? $actor->id,
        ];
    }

    /**
     * Dam bao khong trung/cheo khoang hieu luc theo cung bo department-task-project.
     *
     * @param  array{
     *     department_id: ?int,
     *     task_type: string,
     *     project_type: string,
     *     effective_date: string,
     *     expired_date: ?string
     * }  $payload
     */
    private function assertNoOverlappingEffectivePeriod(array $payload, ?int $ignoreId): void
    {
        $effectiveDate = $payload['effective_date'];
        $expiredDate = $payload['expired_date'];
        $endPoint = $expiredDate ?? '9999-12-31';

        $query = SlaConfig::query()
            ->where('task_type', $payload['task_type'])
            ->where('project_type', $payload['project_type'])
            ->whereDate('effective_date', '<=', $endPoint)
            ->where(function (Builder $builder) use ($effectiveDate): void {
                $builder
                    ->whereNull('expired_date')
                    ->orWhereDate('expired_date', '>=', $effectiveDate);
            });

        if ($payload['department_id'] === null) {
            $query->whereNull('department_id');
        } else {
            $query->where('department_id', $payload['department_id']);
        }

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'effectiveDate' => 'Khoang hieu luc bi trung voi cau hinh SLA da ton tai cho cung bo phong ban/loai cong viec/loai du an.',
            ]);
        }
    }

    private function nullableTrimmedString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string !== '' ? $string : null;
    }
}
