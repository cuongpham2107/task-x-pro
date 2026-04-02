<?php

namespace App\Services\Tasks;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Enums\TaskWorkflowType;
use App\Enums\UserStatus;
use App\Models\Phase;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class TaskQueryService
{
    /**
     * Lay danh sach task cho man hinh index theo quyen user.
     */
    public function paginateForIndex(
        User $actor,
        array $filters = [],
        int $perPage = 15,
        string $sortBy = 'deadline',
        string $sortDir = 'asc'
    ): LengthAwarePaginator {
        $projectId = $filters['project_id'] ?? null;
        if (! $projectId && isset($filters['phase_id'])) {
            $projectId = Phase::where('id', $filters['phase_id'])->value('project_id');
        }

        $query = $this->taskScopeForActor($actor, (int) $projectId)
            ->with([
                'phase:id,project_id,name',
                'phase.project:id,name,type,status',
                'phase.project.leaders:id,name,email,avatar,status',
                'pic:id,name,email,avatar,status',
                'coPics:id,name,email,avatar,status',
            ]);

        $this->applyIndexFilters($query, $filters);

        // Sanitize sortBy
        $allowedSorts = ['name', 'deadline', 'priority', 'status', 'id'];
        $actualSort = in_array($sortBy, $allowedSorts) ? $sortBy : 'deadline';
        $actualDir = strtolower($sortDir) === 'desc' ? 'desc' : 'asc';

        $statusDir = ($actualSort === 'status') ? $actualDir : 'asc';

        // Mặc định luôn ưu tiên sắp xếp theo status
        $query->orderByRaw("CASE
            WHEN tasks.status = 'late' THEN 1
            WHEN tasks.status = 'waiting_approval' THEN 2
            WHEN tasks.status = 'in_progress' THEN 3
            WHEN tasks.status = 'pending' THEN 4
            WHEN tasks.status = 'completed' THEN 5
            ELSE 6 END {$statusDir}");

        if ($actualSort !== 'status') {
            $query->orderBy('tasks.'.$actualSort, $actualDir);
        }

        return $query
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Lay chi tiet task cho man hinh edit.
     */
    public function findForEdit(int $taskId): Task
    {
        return Task::query()
            ->with([
                'phase:id,project_id,name',
                'phase.project:id,name,type,status',
                'pic:id,name,email,department_id',
                'coPics:id,name,email,department_id',
                'dependencyTask:id,name,status',
                'approvalLogs' => function ($query): void {
                    $query
                        ->with('reviewer:id,name,email')
                        ->orderByDesc('id');
                },
                'comments' => function ($query): void {
                    $query
                        ->with(['user:id,name,email,avatar', 'user.roles:id,name'])
                        ->latest();
                },
            ])
            ->findOrFail($taskId);
    }

    /**
     * Tra ve option cho form task de Livewire co the dung truc tiep.
     *
     * @return array{
     *     task_types: list<string>,
     *     task_type_labels: array<string, string>,
     *     task_statuses: list<string>,
     *     task_status_labels: array<string, string>,
     *     task_priorities: list<string>,
     *     task_priority_labels: array<string, string>,
     *     workflow_types: list<string>,
     *     workflow_type_labels: array<string, string>,
     *     projects: Collection<int, Project>,
     *     phases: Collection<int, Phase>,
     *     pics: Collection<int, User>
     * }
     */
    public function formOptions(User $actor, ?int $projectId = null): array
    {
        $projects = $this->projectScopeForActor($actor)
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'status']);

        $phaseQuery = Phase::query()
            ->orderBy('project_id')
            ->orderBy('order_index');

        if ($projectId !== null) {
            $phaseQuery->where('project_id', $projectId);
        } else {
            $phaseQuery->whereIn('project_id', $projects->pluck('id'));
        }

        $phases = $phaseQuery->get(['id', 'project_id', 'name', 'order_index', 'status']);

        $pics = User::query()
            ->role(['pic', 'leader', 'ceo', 'super_admin'])
            ->with('department')
            ->where('status', UserStatus::Active->value)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'department_id']);

        return [
            'task_types' => TaskType::values(),
            'task_type_labels' => TaskType::options(),
            'task_statuses' => TaskStatus::values(),
            'task_status_labels' => TaskStatus::options(),
            'task_priorities' => TaskPriority::values(),
            'task_priority_labels' => TaskPriority::options(),
            'workflow_types' => TaskWorkflowType::values(),
            'workflow_type_labels' => TaskWorkflowType::options(),
            'projects' => $projects,
            'phases' => $phases,
            'pics' => $pics,
        ];
    }

    /**
     * Nap lai relation can thiet de Livewire render ngay sau khi ghi task.
     */
    public function hydrateTask(Task $task): Task
    {
        return $task->load([
            'phase:id,project_id,name',
            'phase.project:id,name,type,status',
            'phase.project.leaders:id,name,email',
            'pic:id,name,email,department_id',
            'coPics:id,name,email,department_id',
            'dependencyTask:id,name,status',
            'approvalLogs' => function ($query): void {
                $query
                    ->with('reviewer:id,name,email')
                    ->orderByDesc('id');
            },
            'comments' => function ($query): void {
                $query
                    ->with(['user:id,name,email,avatar', 'user.roles:id,name'])
                    ->latest();
            },
        ]);
    }

    /**
     * Tao query task theo quyen truy cap cua user.
     */
    public function taskScopeForActor(User $actor, ?int $projectId = null): Builder
    {
        $query = Task::query();

        // 1. Admins/CEOs see everything
        if ($actor->hasAnyRole(['super_admin', 'ceo'])) {
            return $query;
        }

        // 2. Regular users and Leaders (restricted to their projects)
        return $query->where(function (Builder $builder) use ($actor): void {
            $builder
                ->where('tasks.pic_id', $actor->id)
                ->orWhere('tasks.created_by', $actor->id)
                ->orWhereHas('coPics', function (Builder $coPicQuery) use ($actor): void {
                    $coPicQuery->where('users.id', $actor->id);
                })
                ->orWhereHas('phase.project.leaders', function (Builder $leaderQuery) use ($actor): void {
                    $leaderQuery->where('users.id', $actor->id);
                });
        });
    }

    /**
     * Tao query project theo quyen truy cap cua user de phuc vu form task.
     */
    private function projectScopeForActor(User $actor): Builder
    {
        $query = Project::query();

        if (! $actor->hasAnyRole(['ceo', 'leader', 'super_admin'])) {
            $query->where(function (Builder $builder) use ($actor): void {
                $builder
                    ->where('projects.created_by', $actor->id)
                    ->orWhereHas('projectLeaders', function (Builder $leaderQuery) use ($actor): void {
                        $leaderQuery->where('user_id', $actor->id);
                    })
                    ->orWhereHas('phases.tasks', function (Builder $taskQuery) use ($actor): void {
                        $taskQuery->where(function (Builder $participantQuery) use ($actor): void {
                            $participantQuery
                                ->where('tasks.pic_id', $actor->id)
                                ->orWhere('tasks.created_by', $actor->id)
                                ->orWhereHas('coPics', function (Builder $coPicQuery) use ($actor): void {
                                    $coPicQuery->where('users.id', $actor->id);
                                });
                        });
                    });
            });
        }

        return $query;
    }

    /**
     * Ap dung filter cho danh sach task tren man hinh index.
     */
    private function applyIndexFilters(Builder $query, array $filters): void
    {
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $status = $filters['status'] ?? null;
        if (is_string($status) && $status !== '') {
            $query->where('tasks.status', $status);
        }

        $type = $filters['type'] ?? null;
        if (is_string($type) && $type !== '') {
            $query->where('tasks.type', $type);
        }

        $priority = $filters['priority'] ?? null;
        if (is_string($priority) && $priority !== '') {
            $query->where('tasks.priority', $priority);
        }

        $projectId = $filters['project_id'] ?? null;
        if ($projectId !== null && $projectId !== '') {
            $query->whereHas('phase', function (Builder $builder) use ($projectId): void {
                $builder->where('project_id', (int) $projectId);
            });
        }

        $phaseId = $filters['phase_id'] ?? null;
        if ($phaseId !== null && $phaseId !== '') {
            $query->where('tasks.phase_id', (int) $phaseId);
        }

        $picId = $filters['pic_id'] ?? null;
        if ($picId !== null && $picId !== '') {
            $query->where('tasks.pic_id', (int) $picId);
        }

        $deadlineFrom = $filters['deadline_from'] ?? null;
        if (is_string($deadlineFrom) && $deadlineFrom !== '') {
            $query->whereDate('tasks.deadline', '>=', $deadlineFrom);
        }

        $deadlineTo = $filters['deadline_to'] ?? null;
        if (is_string($deadlineTo) && $deadlineTo !== '') {
            $query->whereDate('tasks.deadline', '<=', $deadlineTo);
        }

        $myTasks = $filters['my_tasks'] ?? false;
        if ($myTasks) {
            $user = auth()->user();
            $query->where(function (Builder $builder) use ($user): void {
                // Strictly PIC or Co-PIC: directly assigned tasks for execution
                $builder->where('tasks.pic_id', $user->id)
                    ->orWhereHas('coPics', function (Builder $q) use ($user): void {
                        $q->where('users.id', $user->id);
                    });
            });
        }
    }
}
