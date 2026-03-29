<?php

namespace App\Services\Dashboard;

use App\Enums\ApprovalAction;
use App\Enums\ApprovalLevel;
use App\Enums\KpiPeriodType;
use App\Enums\PhaseStatus;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Enums\TaskWorkflowType;
use App\Enums\UserStatus;
use App\Models\KpiScore;
use App\Models\Phase;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DashboardService
{
    /**
     * Tong hop du lieu cho trang dashboard theo quyen cua user dang nhap.
     *
     * @return array{
     *     projects: array<string, int|float>,
     *     phases: array<string, int>,
     *     tasks: array<string, int>,
     *     recent_tasks: Collection<int, Task>,
     *     approval_tasks: Collection<int, Task>,
     *     kpi: array{
     *         monthly: array<string, int|float|string|null>,
     *         quarterly: array<string, int|float|string|null>
     *     },
     *     top_performers: list<array<string, int|float|string|null>>
     * }
     */
    public function getIndexData(User $actor): array
    {
        $now = now();
        $threeDaysLater = $now->copy()->addDays(3);

        $projectScope = $this->projectScopeForActor($actor);
        $taskScope = $this->taskScopeForActor($actor);

        // Project Stats
        $projectsTotal = (clone $projectScope)->count();
        $projectsRunning = (clone $projectScope)->where('projects.status', ProjectStatus::Running->value)->count();
        $projectsPaused = (clone $projectScope)->where('projects.status', ProjectStatus::Paused->value)->count();
        $projectsCompleted = (clone $projectScope)->where('projects.status', ProjectStatus::Completed->value)->count();
        $projectsAvgProgress = round((float) ((clone $projectScope)->avg('progress') ?? 0), 2);

        // Phase Stats (KPI B)
        $projectIds = (clone $projectScope)->pluck('id');
        $phaseStats = Phase::whereIn('project_id', $projectIds)
            ->selectRaw('count(*) as total')
            ->selectRaw('count(case when status = ? then 1 end) as active', [PhaseStatus::Active->value])
            ->selectRaw('count(case when status = ? then 1 end) as completed', [PhaseStatus::Completed->value])
            ->first();

        // Task Stats
        $tasksOpen = (clone $taskScope)->where('tasks.status', '!=', TaskStatus::Completed->value)->count();
        $tasksInProgress = (clone $taskScope)->where('tasks.status', TaskStatus::InProgress->value)->count();
        $tasksWaitingApproval = (clone $taskScope)->where('tasks.status', TaskStatus::WaitingApproval->value)->count();
        $tasksLate = (clone $taskScope)
            ->where(function (Builder $builder) use ($now): void {
                $builder
                    ->where('status', TaskStatus::Late->value)
                    ->orWhere(function (Builder $deadlineQuery) use ($now): void {
                        $deadlineQuery
                            ->where('deadline', '<', $now->startOfDay())
                            ->where('status', '!=', TaskStatus::Completed->value);
                    });
            })
            ->count();

        $tasksDueSoon = (clone $taskScope)
            ->where('status', '!=', TaskStatus::Completed->value)
            ->whereBetween('deadline', [$now->startOfDay(), $threeDaysLater->endOfDay()])
            ->count();

        $approvalTasks = (clone $taskScope)
            ->where('status', TaskStatus::WaitingApproval->value)
            ->where(function (Builder $query) use ($actor): void {
                if ($actor->hasRole('super_admin')) {
                    return;
                }

                if ($actor->hasRole('ceo')) {
                    $query->where('workflow_type', TaskWorkflowType::Double->value)
                        ->whereHas('approvalLogs', function (Builder $q): void {
                            $q->where('approval_level', ApprovalLevel::Leader->value)
                                ->where('action', ApprovalAction::Approved->value);
                        })
                        ->whereDoesntHave('approvalLogs', function (Builder $q) use ($actor): void {
                            $q->where('reviewer_id', $actor->id)
                                ->where('action', ApprovalAction::Approved->value);
                        });

                    return;
                }

                if ($actor->hasRole('leader')) {
                    $query->whereDoesntHave('approvalLogs', function (Builder $q) use ($actor): void {
                        $q->where('reviewer_id', $actor->id)
                            ->where('action', ApprovalAction::Approved->value);
                    });

                    return;
                }

                $query->whereRaw('0 = 1');
            })
            ->with([
                'phase:id,project_id,name',
                'phase.project:id,name,type,status',
                'pic:id,name,email,avatar,job_title',
                'coPics:id,name,email,avatar,job_title',
            ])
            ->withCount('comments')
            ->orderBy('updated_at', 'desc')
            ->limit(10)
            ->get([
                'id',
                'phase_id',
                'name',
                'status',
                'priority',
                'progress',
                'pic_id',
                'deadline',
                'workflow_type',
                'type',
                'updated_at',
            ]);

        $recentTasks = (clone $taskScope)
            ->where('status', '!=', TaskStatus::Completed->value)
            ->with([
                'phase:id,project_id,name',
                'phase.project:id,name,type,status',
                'pic:id,name,email,avatar,job_title',
                'coPics:id,name,email,avatar,job_title',
            ])
            ->withCount('comments')
            ->orderBy('updated_at')
            ->limit(10)
            ->get([
                'id',
                'phase_id',
                'name',
                'status',
                'priority',
                'progress',
                'pic_id',
                'deadline',
                'workflow_type',
                'type',
            ]);

        return [
            'projects' => [
                'total' => $projectsTotal,
                'running' => $projectsRunning,
                'paused' => $projectsPaused,
                'completed' => $projectsCompleted,
                'avg_progress' => $projectsAvgProgress,
            ],
            'phases' => [
                'total' => $phaseStats->total ?? 0,
                'active' => $phaseStats->active ?? 0,
                'completed' => $phaseStats->completed ?? 0,
            ],
            'tasks' => [
                'open' => $tasksOpen,
                'in_progress' => $tasksInProgress,
                'waiting_approval' => $tasksWaitingApproval,
                'late' => $tasksLate,
                'due_soon' => $tasksDueSoon,
                'total' => (clone $taskScope)->count(),
            ],
            'recent_tasks' => $recentTasks,
            'approval_tasks' => $approvalTasks,
            'kpi' => [
                'monthly' => $this->kpiSummaryForUser($actor, KpiPeriodType::Monthly->value),
                'quarterly' => $this->kpiSummaryForUser($actor, KpiPeriodType::Quarterly->value),
            ],
            'top_performers' => $actor->can('kpi.manage')
                ? $this->topPerformerList()
                : [],
        ];
    }

    /**
     * Tao query project theo quyen de hien thi tren dashboard.
     */
    private function projectScopeForActor(User $actor): Builder
    {
        $query = Project::query();
        if ($actor->hasAnyRole(['ceo', 'super_admin'])) {
            return $query;
        }

        if ($actor->hasRole('leader')) {
            return $query->where(function (Builder $builder) use ($actor): void {
                $builder
                    ->where('projects.created_by', $actor->id)
                    ->orWhereHas('projectLeaders', function (Builder $leaderQuery) use ($actor): void {
                        $leaderQuery->where('user_id', $actor->id);
                    });
            });
        }

        // Default for normal users
        return $query->where(function (Builder $builder) use ($actor): void {
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

    /**
     * Tao query task theo quyen de hien thi tren dashboard.
     */
    private function taskScopeForActor(User $actor): Builder
    {
        $query = Task::query();
        if ($actor->hasAnyRole(['ceo', 'super_admin'])) {
            return $query;
        }

        if ($actor->hasRole('leader')) {
            return $query->whereHas('phase.project', function (Builder $projectQuery) use ($actor): void {
                $projectQuery->where(function (Builder $builder) use ($actor): void {
                    $builder
                        ->where('projects.created_by', $actor->id)
                        ->orWhereHas('projectLeaders', function (Builder $leaderQuery) use ($actor): void {
                            $leaderQuery->where('user_id', $actor->id);
                        });
                });
            });
        }

        // Default for normal users
        return $query->where(function (Builder $builder) use ($actor): void {
            $builder
                ->where('tasks.pic_id', $actor->id)
                ->orWhere('tasks.created_by', $actor->id)
                ->orWhereHas('coPics', function (Builder $coPicQuery) use ($actor): void {
                    $coPicQuery->where('users.id', $actor->id);
                });
        });
    }

    /**
     * Lay KPI thang/quy hien tai cua user de render card dashboard.
     *
     * @return array<string, int|float|string|null>
     */
    private function kpiSummaryForUser(User $actor, string $periodType): array
    {
        [$year, $value] = $this->currentPeriodInfo($periodType);

        if (! $actor->can('kpi.view') && ! $actor->can('kpi.manage')) {
            return [
                'period_type' => $periodType,
                'period_year' => $year,
                'period_value' => $value,
                'total_tasks' => null,
                'on_time_rate' => null,
                'sla_rate' => null,
                'avg_star' => null,
                'final_score' => null,
                'calculated_at' => null,
            ];
        }

        $kpiScore = KpiScore::query()
            ->where('user_id', $actor->id)
            ->where('period_type', $periodType)
            ->where('period_year', $year)
            ->where('period_value', $value)
            ->first();

        if (! $kpiScore instanceof KpiScore) {
            return [
                'period_type' => $periodType,
                'period_year' => $year,
                'period_value' => $value,
                'total_tasks' => 0,
                'on_time_rate' => 0.0,
                'sla_rate' => 0.0,
                'avg_star' => 0.0,
                'final_score' => 0.0,
                'calculated_at' => null,
            ];
        }

        return [
            'period_type' => $kpiScore->period_type,
            'period_year' => (int) $kpiScore->period_year,
            'period_value' => (int) $kpiScore->period_value,
            'total_tasks' => (int) $kpiScore->total_tasks,
            'on_time_rate' => (float) $kpiScore->on_time_rate,
            'sla_rate' => (float) $kpiScore->sla_rate,
            'avg_star' => (float) $kpiScore->avg_star,
            'final_score' => (float) $kpiScore->final_score,
            'calculated_at' => $kpiScore->calculated_at?->toDateTimeString(),
        ];
    }

    /**
     * Lay top KPI theo thang hien tai cho card leaderboard.
     *
     * @return list<array<string, int|float|string|null>>
     */
    public function topPerformerList(int $limit = 5, ?int $month = null, ?int $year = null): array
    {
        $currentYear = $year ?? now()->year;
        $currentMonth = $month ?? now()->month;

        return KpiScore::query()
            ->with('user:id,name,status')
            ->where('period_type', KpiPeriodType::Monthly->value)
            ->where('period_year', $currentYear)
            ->where('period_value', $currentMonth)
            ->whereHas('user', function (Builder $builder): void {
                $builder->where('status', UserStatus::Active->value);
            })
            ->orderByDesc('final_score')
            ->orderByDesc('on_time_rate')
            ->limit($limit)
            ->get()
            ->map(function (KpiScore $kpiScore): array {
                return [
                    'user_id' => (int) $kpiScore->user_id,
                    'user_name' => $kpiScore->user?->name,
                    'final_score' => (float) $kpiScore->final_score,
                    'on_time_rate' => (float) $kpiScore->on_time_rate,
                    'sla_rate' => (float) $kpiScore->sla_rate,
                    'avg_star' => (float) $kpiScore->avg_star,
                ];
            })
            ->all();
    }

    /**
     * Xac dinh gia tri ky KPI hien tai theo thang hoac quy.
     *
     * @return array{0: int, 1: int}
     */
    private function currentPeriodInfo(string $periodType): array
    {
        $now = now();

        if ($periodType === KpiPeriodType::Yearly->value) {
            return [
                (int) $now->year,
                1,
            ];
        }

        if ($periodType === KpiPeriodType::Quarterly->value) {
            return [
                (int) $now->year,
                (int) ceil($now->month / 3),
            ];
        }

        return [
            (int) $now->year,
            (int) $now->month,
        ];
    }
}
