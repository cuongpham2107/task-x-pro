<?php

namespace App\Services\SlaConfigs;

use App\Enums\SlaProjectType;
use App\Enums\SlaTaskType;
use App\Models\Department;
use App\Models\SlaConfig;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class SlaConfigQueryService
{
    /**
     * Lay danh sach cau hinh SLA cho man hinh index.
     */
    public function paginateForIndex(array $filters = [], int $perPage = 12): LengthAwarePaginator
    {
        $query = SlaConfig::query()
            ->with([
                'department:id,name,code',
                'creator:id,name,email,avatar',
            ]);

        $this->applyFilters($query, $filters);

        $sortBy = $filters['sort'] ?? 'effective_date';
        $sortDir = $filters['dir'] ?? 'desc';
        $allowedSorts = ['effective_date', 'expired_date', 'standard_hours', 'task_type', 'project_type', 'created_at'];
        $sortColumn = in_array($sortBy, $allowedSorts, true) ? $sortBy : 'effective_date';
        $sortDirection = $sortDir === 'asc' ? 'asc' : 'desc';

        return $query
            ->orderBy($sortColumn, $sortDirection)
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Lay chi tiet cau hinh SLA cho man hinh sua.
     */
    public function findForEdit(int $slaConfigId): SlaConfig
    {
        return SlaConfig::query()
            ->with([
                'department:id,name,code',
                'creator:id,name,email,avatar',
            ])
            ->findOrFail($slaConfigId);
    }

    /**
     * @return array{
     *     departments: Collection<int, Department>,
     *     task_type_labels: array<string, string>,
     *     project_type_labels: array<string, string>
     * }
     */
    public function formOptions(): array
    {
        $departments = Department::query()
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return [
            'departments' => $departments,
            'task_type_labels' => SlaTaskType::options(),
            'project_type_labels' => SlaProjectType::options(),
        ];
    }

    /**
     * Ap dung filter tim kiem cho danh sach cau hinh SLA.
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('note', 'like', "%{$search}%")
                    ->orWhereHas('department', function (Builder $departmentQuery) use ($search): void {
                        $departmentQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    });
            });
        }

        $departmentId = trim((string) ($filters['department_id'] ?? ''));
        if ($departmentId !== '') {
            if ($departmentId === 'global') {
                $query->whereNull('department_id');
            } else {
                $query->where('department_id', (int) $departmentId);
            }
        }

        $taskType = trim((string) ($filters['task_type'] ?? ''));
        if ($taskType !== '') {
            $query->where('task_type', $taskType);
        }

        $projectType = trim((string) ($filters['project_type'] ?? ''));
        if ($projectType !== '') {
            $query->where('project_type', $projectType);
        }

        $state = trim((string) ($filters['state'] ?? ''));
        if ($state !== '') {
            $today = Carbon::today()->toDateString();

            if ($state === 'active') {
                $query->effectiveAt($today);
            } elseif ($state === 'upcoming') {
                $query->whereDate('effective_date', '>', $today);
            } elseif ($state === 'expired') {
                $query->whereNotNull('expired_date')
                    ->whereDate('expired_date', '<', $today);
            }
        }
    }
}
