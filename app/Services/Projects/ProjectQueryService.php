<?php

namespace App\Services\Projects;

use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Enums\UserStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class ProjectQueryService
{
    /**
     * Lay du lieu danh sach project cho man hinh index.
     */
    public function paginateForIndex(?User $actor, array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $query = Project::query()
            ->with(['creator', 'leaders'])
            ->withCount([
                'tasks',
                'tasks as done_tasks_count' => fn ($taskQuery) => $taskQuery->where('tasks.status', 'completed'),
            ]);

        $this->scopeVisibility($query, $actor);

        $tab = $filters['tab'] ?? 'all';
        if ($tab === 'mine' && $actor) {
            $query->where('created_by', $actor->id);
        } elseif ($tab === 'mine') {
            $query->whereRaw('1 = 0');
        } elseif ($tab !== 'all') {
            $query->where('status', $tab);
        }

        $this->applyAdvancedFilters($query, $filters);

        $sortBy = $filters['sort'] ?? 'created_at';
        $sortDir = $filters['dir'] ?? 'desc';
        $allowedSorts = ['name', 'created_at', 'start_date', 'end_date'];
        $col = in_array($sortBy, $allowedSorts, true) ? $sortBy : 'created_at';

        return $query
            ->orderBy($col, $sortDir)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Lay so luong project theo cac tab.
     *
     * @return array<string, array{label: string, count: int}>
     */
    public function getTabCounts(?User $actor): array
    {
        $baseQuery = Project::query();
        $this->scopeVisibility($baseQuery, $actor);

        $tabs = [
            'all' => ['label' => 'Tất cả', 'count' => $baseQuery->clone()->count()],
        ];

        if ($actor) {
            $tabs['mine'] = ['label' => 'Của tôi', 'count' => $baseQuery->clone()->where('created_by', $actor->id)->count()];
        }

        foreach (ProjectStatus::cases() as $status) {
            $count = $baseQuery->clone()->where('status', $status->value)->count();
            if ($count > 0) {
                $tabs[$status->value] = ['label' => $status->label(), 'count' => $count];
            }
        }

        return $tabs;
    }

    /**
     * Scope query de chi hien thi project ma user co quyen xem.
     */
    public function scopeVisibility(Builder $query, ?User $actor): void
    {
        if ($actor && ! $actor->hasAnyRole(['super_admin', 'ceo'])) {
            $query->where(function (Builder $builder) use ($actor) {
                $builder->where('created_by', $actor->id)
                    ->orWhereHas('leaders', function (Builder $q) use ($actor) {
                        $q->where('users.id', $actor->id);
                    })
                    ->orWhereHas('tasks', function (Builder $q) use ($actor) {
                        $q->where('pic_id', $actor->id)
                            ->orWhere('created_by', $actor->id)
                            ->orWhereHas('coPics', function (Builder $coPicQuery) use ($actor) {
                                $coPicQuery->where('users.id', $actor->id);
                            });
                    });
            });
        }
    }

    /**
     * Tra ve danh sach loai du an lam type filter.
     *
     * @return array<string, string>
     */
    public function getTypeOptions(): array
    {
        return ProjectType::options();
    }

    /**
     * Lay chi tiet project cho man hinh edit.
     */
    public function findForEdit(int $projectId): Project
    {
        return Project::query()
            ->with([
                'creator:id,name,email',
                'leaders:id,name,email',
                'phases' => function (HasMany $query): void {
                    $query
                        ->orderBy('order_index')
                        ->withCount('tasks');
                },
            ])
            ->findOrFail($projectId);
    }

    /**
     * Tra ve danh muc option cho form tao/sua project.
     *
     * @return array{
     *     project_types: list<string>,
     *     project_type_labels: array<string, string>,
     *     project_statuses: list<string>,
     *     project_status_labels: array<string, string>,
     *     leaders: Collection<int, User>
     * }
     */
    public function formOptions(): array
    {
        $leaders = User::query()
            ->role(['ceo', 'leader', 'super_admin'])
            ->where('status', UserStatus::Active->value)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return [
            'project_types' => ProjectType::values(),
            'project_type_labels' => ProjectType::options(),
            'project_statuses' => ProjectStatus::values(),
            'project_status_labels' => ProjectStatus::options(),
            'leaders' => $leaders,
        ];
    }

    /**
     * Nap lai relation can thiet de tra cho Livewire ngay sau khi ghi du lieu.
     */
    public function hydrateProject(Project $project): Project
    {
        return $project->load([
            'creator:id,name,email',
            'leaders:id,name,email',
            'phases' => function (HasMany $query): void {
                $query
                    ->select([
                        'id',
                        'project_id',
                        'name',
                        'description',
                        'weight',
                        'order_index',
                        'start_date',
                        'end_date',
                        'progress',
                        'status',
                        'is_template',
                    ])
                    ->orderBy('order_index')
                    ->withCount('tasks');
            },
        ]);
    }

    /**
     * Ap dung filter nang cao cho danh sach project.
     */
    private function applyAdvancedFilters(Builder $query, array $filters): void
    {
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('objective', 'like', "%{$search}%");
            });
        }

        $type = $filters['type'] ?? null;
        if ($type !== null && $type !== '') {
            $query->where('type', $type);
        }

        $leaderId = $filters['leader_id'] ?? null;
        if ($leaderId !== null && $leaderId !== '') {
            $query->whereHas('projectLeaders', function (Builder $builder) use ($leaderId): void {
                $builder->where('user_id', (int) $leaderId);
            });
        }
    }
}
