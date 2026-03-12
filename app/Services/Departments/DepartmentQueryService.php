<?php

namespace App\Services\Departments;

use App\Enums\DepartmentStatus;
use App\Enums\UserStatus;
use App\Models\Department;
use App\Models\KpiScore;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DepartmentQueryService
{
    /**
     * Lay du lieu danh sach phong ban cho man hinh index.
     */
    public function paginateForIndex(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $query = Department::query()
            ->with('head:id,name,email,avatar')
            ->withCount([
                'users as member_count',
                'activeUsers as active_member_count',
            ])
            ->withAvg('kpiScores as avg_kpi_score', 'final_score');

        $this->applyFilters($query, $filters);

        $sortBy = $filters['sort'] ?? 'name';
        $sortDir = $filters['dir'] ?? 'asc';
        $allowedSorts = ['name', 'code', 'status', 'created_at'];
        $sortColumn = in_array($sortBy, $allowedSorts, true) ? $sortBy : 'name';
        $sortDirection = $sortDir === 'desc' ? 'desc' : 'asc';

        return $query
            ->orderBy($sortColumn, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Lay chi tiet phong ban cho man hinh sua.
     */
    public function findForEdit(int $departmentId): Department
    {
        return Department::query()
            ->with('head:id,name,email,avatar')
            ->with('users:id,name,email,avatar,department_id,job_title,phone,status,telegram_id')
            ->findOrFail($departmentId);
    }

    /**
     * Tra ve du lieu tong quan cho cac card thong ke.
     *
     * @return array{
     *     total_departments: int,
     *     active_departments: int,
     *     inactive_departments: int,
     *     total_members: int,
     *     active_members: int,
     *     average_kpi: float
     * }
     */
    public function summaryStats(): array
    {
        $totalDepartments = Department::query()->count();
        $activeDepartments = Department::query()
            ->where('status', DepartmentStatus::Active->value)
            ->count();
        $inactiveDepartments = Department::query()
            ->where('status', DepartmentStatus::Inactive->value)
            ->count();

        $totalMembers = User::query()
            ->whereNotNull('department_id')
            ->count();

        $activeMembers = User::query()
            ->whereNotNull('department_id')
            ->where('status', UserStatus::Active->value)
            ->count();

        $averageKpi = (float) (KpiScore::query()
            ->whereHas('user', function (Builder $query): void {
                $query->whereNotNull('department_id');
            })
            ->avg('final_score') ?? 0);

        return [
            'total_departments' => $totalDepartments,
            'active_departments' => $activeDepartments,
            'inactive_departments' => $inactiveDepartments,
            'total_members' => $totalMembers,
            'active_members' => $activeMembers,
            'average_kpi' => round($averageKpi, 2),
        ];
    }

    /**
     * Tra ve danh muc option cho form tao/sua phong ban.
     *
     * @return array{
     *     status_labels: array<string, string>,
     *     heads: Collection<int, User>
     * }
     */
    public function formOptions(): array
    {
        $heads = User::query()
            ->where('status', '!=', UserStatus::Resigned->value)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'avatar', 'status']);

        return [
            'status_labels' => DepartmentStatus::options(),
            'heads' => $heads,
        ];
    }

    /**
     * Ap dung filter tim kiem va trang thai cho danh sach phong ban.
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $query->where('status', $status);
        }
    }
}
