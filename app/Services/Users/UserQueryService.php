<?php

namespace App\Services\Users;

use App\Enums\UserStatus;
use App\Models\Department;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class UserQueryService
{
    /**
     * Lay du lieu danh sach user cho man hinh index.
     */
    public function paginateForIndex(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $query = User::query()
            ->with('department:id,name');

        $this->applyFilters($query, $filters);

        $sortBy = $filters['sort'] ?? 'name';
        $sortDir = $filters['dir'] ?? 'asc';
        $allowedSorts = ['name', 'email', 'employee_code', 'status', 'created_at'];
        $sortColumn = in_array($sortBy, $allowedSorts, true) ? $sortBy : 'name';
        $sortDirection = $sortDir === 'desc' ? 'desc' : 'asc';

        return $query
            ->orderBy($sortColumn, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Lay chi tiet user cho man hinh sua.
     */
    public function findForEdit(int $userId): User
    {
        return User::query()
            ->with(['department:id,name', 'roles:id,name'])
            ->findOrFail($userId);
    }

    /**
     * Tra ve du lieu tong quan cho card thong ke user.
     *
     * @return array{
     *     total_users: int,
     *     active_users: int,
     *     on_leave_users: int,
     *     resigned_users: int
     * }
     */
    public function summaryStats(): array
    {
        return [
            'total_users' => User::query()->count(),
            'active_users' => User::query()->where('status', UserStatus::Active->value)->count(),
            'on_leave_users' => User::query()->where('status', UserStatus::OnLeave->value)->count(),
            'resigned_users' => User::query()->where('status', UserStatus::Resigned->value)->count(),
        ];
    }

    /**
     * Lay danh sach du an ma user tham gia (Leader/PIC/Co-PIC).
     */
    public function getParticipatingProjects(int $userId, int $limit = 4): Collection
    {
        return \App\Models\Project::query()
            ->where(function ($q) use ($userId) {
                $q->whereHas('leaders', fn ($q) => $q->where('users.id', $userId))
                    ->orWhereHas('tasks', function ($q) use ($userId) {
                        $q->where('tasks.pic_id', $userId)
                            ->orWhereHas('coPics', fn ($q) => $q->where('users.id', $userId));
                    });
            })
            ->with(['leaders'])
            ->take($limit)
            ->get();
    }

    /**
     * Lay danh sach task gan day cua user (PIC/Co-PIC).
     */
    public function getRecentTasks(int $userId, int $limit = 5): Collection
    {
        return \App\Models\Task::query()
            ->where(function ($q) use ($userId) {
                $q->where('tasks.pic_id', $userId)
                    ->orWhereHas('coPics', fn ($q) => $q->where('users.id', $userId));
            })
            ->with(['phase.project'])
            ->latest('updated_at')
            ->take($limit)
            ->get();
    }

    /**
     * Tra ve danh muc option cho form tao/sua user.
     *
     * @return array{
     *     status_labels: array<string, string>,
     *     departments: Collection<int, Department>
     * }
     */
    public function formOptions(): array
    {
        $departments = Department::query()
            ->orderBy('name')
            ->get(['id', 'name', 'status']);

        $roles = \Spatie\Permission\Models\Role::query()
            ->where(fn ($q) => $q->where('name', '!=', 'super_admin'))
            ->orderBy('name')
            ->get(['id', 'name']);

        return [
            'status_labels' => UserStatus::options(),
            'departments' => $departments,
            'roles' => $roles,
        ];
    }

    /**
     * Ap dung filter cho danh sach user.
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('employee_code', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $query->where('status', $status);
        }

        $departmentId = $filters['department_id'] ?? null;
        if ($departmentId !== null && $departmentId !== '') {
            $query->where('department_id', (int) $departmentId);
        }
    }
}
