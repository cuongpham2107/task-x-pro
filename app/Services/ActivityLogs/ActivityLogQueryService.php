<?php

namespace App\Services\ActivityLogs;

use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ActivityLogQueryService
{
    /**
     * Lay du lieu danh sach activity log cho man hinh index.
     */
    public function paginateForIndex(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = ActivityLog::query()
            ->with('user:id,name,email,avatar')
            ->with([
                'entity' => function (MorphTo $morphTo): void {
                    $morphTo->morphWith([
                        Task::class => [
                            'phase:id,project_id,name',
                            'phase.project:id,name',
                        ],
                        Project::class => [],
                    ]);
                },
            ]);

        $this->applyFilters($query, $filters);

        $sortBy = $filters['sort'] ?? 'created_at';
        $sortDir = $filters['dir'] ?? 'desc';
        $allowedSorts = ['created_at', 'action', 'entity_type'];
        $sortColumn = in_array($sortBy, $allowedSorts, true) ? $sortBy : 'created_at';
        $sortDirection = $sortDir === 'asc' ? 'asc' : 'desc';

        return $query
            ->orderBy($sortColumn, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Tra ve danh muc option cho bo loc.
     *
     * @return array{
     *     actions: array<string, string>,
     *     entity_types: array<string, string>,
     *     users: Collection<int, User>
     * }
     */
    public function filterOptions(): array
    {
        $actionOptions = ActivityLog::query()
            ->select('action')
            ->whereNotNull('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->filter()
            ->mapWithKeys(fn (string $action): array => [$action => $this->actionLabel($action)])
            ->all();

        $entityTypeOptions = ActivityLog::query()
            ->select('entity_type')
            ->whereNotNull('entity_type')
            ->distinct()
            ->orderBy('entity_type')
            ->pluck('entity_type')
            ->filter()
            ->mapWithKeys(
                fn (string $entityType): array => [
                    $this->entityTypeFilterKey($entityType) => $this->entityTypeLabel($entityType),
                ]
            )
            ->all();

        $userOptions = User::query()
            ->whereIn(
                'id',
                ActivityLog::query()
                    ->whereNotNull('user_id')
                    ->distinct()
                    ->pluck('user_id')
                    ->all()
            )
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'avatar']);

        return [
            'actions' => $actionOptions,
            'entity_types' => $entityTypeOptions,
            'users' => $userOptions,
        ];
    }

    /**
     * Chuyen action code sang nhan hien thi.
     */
    public function actionLabel(string $action): string
    {
        return match ($action) {
            'status_updated' => 'Cap nhat trang thai',
            'status_changed' => 'Thay doi trang thai',
            'progress_updated' => 'Cap nhat tien do',
            'created' => 'Tao moi',
            'updated' => 'Cap nhat',
            'deleted' => 'Xoa',
            'approved' => 'Phe duyet',
            default => Str::headline(str_replace('_', ' ', $action)),
        };
    }

    /**
     * Chuyen entity type sang nhan hien thi.
     */
    public function entityTypeLabel(string $entityType): string
    {
        return match ($entityType) {
            Task::class => 'Task',
            Project::class => 'Project',
            default => class_basename($entityType),
        };
    }

    /**
     * Ap dung bo loc tim kiem cho danh sach activity log.
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('action', 'like', "%{$search}%")
                    ->orWhere('entity_type', 'like', "%{$search}%")
                    ->orWhereHas('user', function (Builder $userQuery) use ($search): void {
                        $userQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });

                if (ctype_digit($search)) {
                    $builder->orWhere('entity_id', (int) $search);
                }
            });
        }

        $action = trim((string) ($filters['action'] ?? ''));
        if ($action !== '') {
            $query->where('action', $action);
        }

        $userId = $filters['user_id'] ?? null;
        if ($userId !== null && $userId !== '') {
            $query->where('user_id', (int) $userId);
        }

        $entityTypeFilter = trim((string) ($filters['entity_type'] ?? ''));
        if ($entityTypeFilter !== '') {
            $resolvedEntityType = $this->resolveEntityTypeFilter($entityTypeFilter);
            if ($resolvedEntityType !== null) {
                $query->where('entity_type', $resolvedEntityType);
            } else {
                $query->where('entity_type', $entityTypeFilter);
            }
        }
    }

    /**
     * Chuyen entity class ve filter key an toan cho query-string/UI.
     */
    private function entityTypeFilterKey(string $entityType): string
    {
        return match ($entityType) {
            Task::class => 'task',
            Project::class => 'project',
            default => Str::snake(class_basename($entityType)),
        };
    }

    /**
     * Chuyen filter key ve entity class.
     */
    private function resolveEntityTypeFilter(string $entityTypeFilter): ?string
    {
        return match ($entityTypeFilter) {
            'task' => Task::class,
            'project' => Project::class,
            default => null,
        };
    }
}
