<?php
use App\Enums\KpiPeriodType;
use App\Exports\KpiExport;
use App\Models\Department;
use App\Models\KpiScore;
use App\Models\Task;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;

new #[Title('KPI toàn công ty')] class extends Component {
    use WithPagination;

    public string $periodType = KpiPeriodType::Monthly->value;

    public int $selectedYear;

    public int $selectedValue;

    public ?int $selectedDepartmentId = null;

    public int $perPage = 10;

    public string $taskApprovalFilter = 'all';

    public function mount(): void
    {
        Gate::forUser(auth()->user())->authorize('viewAny', KpiScore::class);

        $now = now();
        $this->selectedYear = (int) $now->year;
        $this->selectedValue = (int) $now->month;
    }

    public function updatedPeriodType(): void
    {
        if ($this->periodType === KpiPeriodType::Yearly->value) {
            $this->selectedValue = 1;
        } elseif ($this->periodType === KpiPeriodType::Quarterly->value) {
            $this->selectedValue = (int) ceil(now()->month / 3);
        } else {
            $this->selectedValue = (int) now()->month;
        }

        $this->resetPage();
    }

    public function updatedSelectedYear(): void
    {
        $this->resetPage();
    }

    public function updatedSelectedValue(): void
    {
        $this->resetPage();
    }

    public function updatedSelectedDepartmentId(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function getYearOptionsProperty(): array
    {
        $currentYear = (int) now()->year;

        return range($currentYear, $currentYear - 4);
    }

    public function getPeriodValueOptionsProperty(): array
    {
        if ($this->periodType === KpiPeriodType::Yearly->value) {
            return [1 => 'Cả năm'];
        }
        if ($this->periodType === KpiPeriodType::Quarterly->value) {
            return collect(range(1, 4))->mapWithKeys(fn($v) => [$v => 'Quý ' . $v])->all();
        }

        return collect(range(1, 12))->mapWithKeys(fn($v) => [$v => 'Tháng ' . $v])->all();
    }

    public function getDepartmentsProperty()
    {
        return Department::query()
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
    }

    public function getSummaryProperty(): array
    {
        $baseQuery = $this->getBaseKpiQuery();

        return [
            'avg_score' => round((float) (clone $baseQuery)->avg('final_score'), 2),
            'avg_on_time_rate' => round((float) (clone $baseQuery)->avg('on_time_rate'), 2),
            'avg_sla_rate' => round((float) (clone $baseQuery)->avg('sla_rate'), 2),
            'avg_star' => round((float) (clone $baseQuery)->avg('avg_star'), 2),
            'avg_actual_score' => round((float) (clone $baseQuery)->avg('actual_score'), 2),
        ];
    }

    public function getTrendsProperty(): array
    {
        // Calculate previous period for trend comparison
        $prevYear = $this->selectedYear;
        $prevValue = $this->selectedValue;

        if ($this->periodType === KpiPeriodType::Monthly->value) {
            $prevValue--;
            if ($prevValue < 1) {
                $prevValue = 12;
                $prevYear--;
            }
        } elseif ($this->periodType === KpiPeriodType::Quarterly->value) {
            $prevValue--;
            if ($prevValue < 1) {
                $prevValue = 4;
                $prevYear--;
            }
        } else {
            $prevYear--;
        }

        $prevQuery = KpiScore::query()
            ->where('period_type', $this->periodType)
            ->where('period_year', $prevYear)
            ->where('period_value', $prevValue)
            ->when($this->selectedDepartmentId, function ($query) {
                $query->whereHas('user', function ($u) {
                    $u->where('department_id', $this->selectedDepartmentId);
                });
            });

        $current = $this->summary;
        $prev = [
            'avg_score' => (float) (clone $prevQuery)->avg('final_score'),
            'avg_on_time_rate' => (float) (clone $prevQuery)->avg('on_time_rate'),
            'avg_sla_rate' => (float) (clone $prevQuery)->avg('sla_rate'),
            'avg_star' => (float) (clone $prevQuery)->avg('avg_star'),
        ];

        return [
            'avg_score' => $this->calcTrend($current['avg_score'], $prev['avg_score']),
            'avg_on_time_rate' => $this->calcTrend($current['avg_on_time_rate'], $prev['avg_on_time_rate']),
            'avg_sla_rate' => $this->calcTrend($current['avg_sla_rate'], $prev['avg_sla_rate']),
            'avg_star' => $this->calcTrend($current['avg_star'], $prev['avg_star']),
        ];
    }

    public function getApprovalOverviewProperty(): array
    {
        $baseQuery = $this->getBaseKpiQuery();

        $total = (clone $baseQuery)->count();
        $pending = (clone $baseQuery)->whereIn('status', ['pending', 'locked'])->count();
        $approved = (clone $baseQuery)->where('status', 'approved')->count();
        $rejected = (clone $baseQuery)->where('status', 'rejected')->count();

        return [
            'total' => $total,
            'pending' => $pending,
            'approved' => $approved,
            'rejected' => $rejected,
            'approval_rate' => $total > 0 ? round(($approved / $total) * 100, 1) : 0.0,
        ];
    }

    private function getFilteredTasksQuery()
    {
        [$periodStart, $periodEnd] = $this->selectedPeriodDateRange();

        $query = Task::query()
            ->where('status', 'completed')
            ->whereNotNull('completed_at')
            ->whereBetween('completed_at', [$periodStart, $periodEnd])
            ->when($this->selectedDepartmentId, function ($query): void {
                $query->whereHas('pic', function ($picQuery): void {
                    $picQuery->where('department_id', $this->selectedDepartmentId);
                });
            });

        // Apply task approval filter at DB level
        if ($this->taskApprovalFilter === 'approved') {
            $query->whereHas('approvalLogs', function ($q) {
                $q->where('id', function ($sub) {
                    $sub->selectRaw('max(id)')->from('approval_logs')->whereColumn('task_id', 'tasks.id');
                })->where('action', 'approved');
            });
        } elseif ($this->taskApprovalFilter === 'rejected') {
            $query->whereHas('approvalLogs', function ($q) {
                $q->where('id', function ($sub) {
                    $sub->selectRaw('max(id)')->from('approval_logs')->whereColumn('task_id', 'tasks.id');
                })->where('action', 'rejected');
            });
        } elseif ($this->taskApprovalFilter === 'waiting') {
            $query->where(function ($q) {
                $q->whereDoesntHave('approvalLogs')->orWhereHas('approvalLogs', function ($subq) {
                    $subq
                        ->where('id', function ($sub) {
                            $sub->selectRaw('max(id)')->from('approval_logs')->whereColumn('task_id', 'tasks.id');
                        })
                        ->where('action', 'submitted');
                });
            });
        }

        return $query;
    }

    /**
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function getTaskApprovalRowsProperty()
    {
        return $this->getFilteredTasksQuery()
            ->with(['pic:id,name,department_id', 'pic.department:id,name', 'phase:id,name,project_id', 'phase.project:id,name', 'approvalLogs:id,task_id,reviewer_id,approval_level,action,star_rating,created_at', 'approvalLogs.reviewer:id,name'])
            ->orderByDesc('completed_at')
            ->paginate($this->perPage, ['*'], 'tasksApprovPage');
    }

    public function getTaskApprovalSummaryProperty(): array
    {
        $query = $this->getFilteredTasksQuery();
        $total = $query->count();

        if ($total === 0) {
            return [
                'total' => 0,
                'approved' => 0,
                'rejected' => 0,
                'waiting' => 0,
                'sla_met' => 0,
                'avg_progress' => 0.0,
            ];
        }

        // Subquery for latest approval action
        $latestApprovalSubquery = \App\Models\ApprovalLog::query()->select('action')->whereColumn('task_id', 'tasks.id')->orderByDesc('id')->limit(1);

        $stats = (clone $query)
            ->selectRaw(
                '
                COUNT(*) as total,
                SUM(CASE WHEN sla_met = 1 THEN 1 ELSE 0 END) as sla_met,
                AVG(progress) as avg_progress
            ',
            )
            ->first();

        // Count actions using subquery in where
        $approvedCount = (clone $query)
            ->whereHas('approvalLogs', function ($q) {
                $q->where('id', function ($sub) {
                    $sub->selectRaw('max(id)')->from('approval_logs')->whereColumn('task_id', 'tasks.id');
                })->where('action', 'approved');
            })
            ->count();

        $rejectedCount = (clone $query)
            ->whereHas('approvalLogs', function ($q) {
                $q->where('id', function ($sub) {
                    $sub->selectRaw('max(id)')->from('approval_logs')->whereColumn('task_id', 'tasks.id');
                })->where('action', 'rejected');
            })
            ->count();

        $waitingCount = $total - $approvedCount - $rejectedCount;

        return [
            'total' => $total,
            'approved' => $approvedCount,
            'rejected' => $rejectedCount,
            'waiting' => $total - $approvedCount - $rejectedCount,
            'sla_met' => (int) $stats->sla_met,
            'avg_progress' => round((float) $stats->avg_progress, 1),
        ];
    }

    public function getTrendDataProperty(): array
    {
        $currentYear = $this->selectedYear;
        $prevYear = $currentYear - 1;

        $currentYearData = KpiScore::query()->selectRaw('period_value, AVG(final_score) as avg_score')->where('period_type', KpiPeriodType::Monthly->value)->where('period_year', $currentYear)->groupBy('period_value')->orderBy('period_value')->pluck('avg_score', 'period_value')->all();

        $prevYearData = KpiScore::query()->selectRaw('period_value, AVG(final_score) as avg_score')->where('period_type', KpiPeriodType::Monthly->value)->where('period_year', $prevYear)->groupBy('period_value')->orderBy('period_value')->pluck('avg_score', 'period_value')->all();

        $formatData = function ($data) {
            return collect(range(1, 12))->map(fn($m) => (float) ($data[$m] ?? 0))->values()->all();
        };

        return [
            'current' => $formatData($currentYearData),
            'previous' => $formatData($prevYearData),
        ];
    }

    public function getTaskKpiScoreMapProperty(): array
    {
        $userIds = collect($this->taskApprovalRows->items())
            ->pluck('pic_id')
            ->filter()
            ->unique()
            ->values();

        if ($userIds->isEmpty()) {
            return [];
        }

        return KpiScore::query()->where('period_type', $this->periodType)->where('period_year', $this->selectedYear)->where('period_value', $this->selectedValue)->whereIn('user_id', $userIds)->pluck('final_score', 'user_id')->map(fn(float|int|string $score): float => round((float) $score, 1))->all();
    }

    /**
     * @return array{label: string, class: string}
     */
    public function taskStatusMeta(Task $task): array
    {
        $status = $task->status;
        if ($status instanceof \App\Enums\TaskStatus) {
            return [
                'label' => $status->label(),
                'class' => $status->badgeClass(),
            ];
        }

        $statusValue = $status instanceof \BackedEnum ? (string) $status->value : (string) $status;

        return match ($statusValue) {
            'completed' => ['label' => 'Hoàn thành', 'class' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'],
            'waiting_approval' => ['label' => 'Chờ duyệt', 'class' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300'],
            'in_progress' => ['label' => 'Đang thực hiện', 'class' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300'],
            'late' => ['label' => 'Trễ hạn', 'class' => 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300'],
            default => ['label' => 'Chưa bắt đầu', 'class' => 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300'],
        };
    }

    /**
     * @return array{label: string, class: string, reviewer: string, level: string, star: ?int}
     */
    public function taskApprovalMeta(Task $task): array
    {
        $latestLog = $task->approvalLogs->sortByDesc('id')->first();
        if (!$latestLog) {
            return [
                'label' => 'Chưa gửi duyệt',
                'class' => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
                'reviewer' => '',
                'level' => '',
                'star' => null,
            ];
        }

        $level = $latestLog->approval_level ? strtoupper((string) $latestLog->approval_level) : '';
        $reviewer = (string) ($latestLog->reviewer?->name ?? '');
        $star = $latestLog->star_rating !== null ? (int) $latestLog->star_rating : null;

        return match ((string) $latestLog->action) {
            'approved' => [
                'label' => 'Đã duyệt',
                'class' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
                'reviewer' => $reviewer,
                'level' => $level,
                'star' => $star,
            ],
            'rejected' => [
                'label' => 'Từ chối',
                'class' => 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300',
                'reviewer' => $reviewer,
                'level' => $level,
                'star' => null,
            ],
            default => [
                'label' => 'Đang chờ duyệt',
                'class' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
                'reviewer' => $reviewer,
                'level' => $level,
                'star' => null,
            ],
        };
    }

    private function calcTrend($curr, $prev)
    {
        if (!$prev) {
            return ['value' => 0, 'direction' => 'neutral', 'label' => '-'];
        }
        $diff = $curr - $prev;
        $pct = ($diff / $prev) * 100;

        return [
            'value' => abs(round($pct, 1)),
            'direction' => $diff > 0 ? 'up' : ($diff < 0 ? 'down' : 'neutral'),
            'label' => ($diff > 0 ? '+' : '') . round($pct, 1) . '%',
        ];
    }

    public function getTopPerformersProperty()
    {
        return $this->getBaseKpiQuery()
            ->with(['user:id,name,department_id,avatar', 'user.department:id,name'])
            ->orderByDesc('final_score')
            ->limit(5)
            ->get();
    }

    public function getDepartmentStatsProperty()
    {
        return Department::query()
            ->with('head:id,name,job_title,avatar')
            ->withCount(['activeUsers as active_users_count'])
            ->withAvg(['kpiScores as avg_final_score' => fn($q) => $this->applyPeriodFilter($q)], 'final_score')
            ->withAvg(['kpiScores as avg_sla_rate' => fn($q) => $this->applyPeriodFilter($q)], 'sla_rate')
            ->withAvg(['kpiScores as avg_on_time_rate' => fn($q) => $this->applyPeriodFilter($q)], 'on_time_rate')
            ->withAvg(['kpiScores as avg_star' => fn($q) => $this->applyPeriodFilter($q)], 'avg_star')
            ->when($this->selectedDepartmentId, fn($q) => $q->where('id', $this->selectedDepartmentId))
            ->orderByDesc('avg_final_score')
            ->paginate($this->perPage);
    }

    private function getBaseKpiQuery()
    {
        return KpiScore::query()
            ->where('period_type', $this->periodType)
            ->where('period_year', $this->selectedYear)
            ->where('period_value', $this->selectedValue)
            ->when($this->selectedDepartmentId, function ($query) {
                $query->whereHas('user', function ($userQuery) {
                    $userQuery->where('department_id', $this->selectedDepartmentId);
                });
            });
    }

    private function applyPeriodFilter($query)
    {
        $query->where('period_type', $this->periodType)->where('period_year', $this->selectedYear)->where('period_value', $this->selectedValue);
    }

    private function filterTasksByApproval(Collection $tasks, string $filter): Collection
    {
        if ($filter === 'all') {
            return $tasks;
        }

        return $tasks
            ->filter(function (Task $task) use ($filter): bool {
                $latestLog = $task->approvalLogs->sortByDesc('id')->first();
                $action = $latestLog?->action;

                return match ($filter) {
                    'approved' => $action === 'approved',
                    'rejected' => $action === 'rejected',
                    'waiting' => $action === 'submitted' || $action === null,
                    default => true,
                };
            })
            ->values();
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function selectedPeriodDateRange(): array
    {
        if ($this->periodType === KpiPeriodType::Yearly->value) {
            $start = Carbon::create($this->selectedYear, 1, 1)->startOfDay();

            return [$start, $start->copy()->endOfYear()->endOfDay()];
        }

        if ($this->periodType === KpiPeriodType::Quarterly->value) {
            $startMonth = ($this->selectedValue - 1) * 3 + 1;
            $start = Carbon::create($this->selectedYear, $startMonth, 1)->startOfDay();

            return [$start, $start->copy()->addMonths(2)->endOfMonth()->endOfDay()];
        }

        $start = Carbon::create($this->selectedYear, $this->selectedValue, 1)->startOfDay();

        return [$start, $start->copy()->endOfMonth()->endOfDay()];
    }

    public function periodLabel(string $periodType, int $year, int $value): string
    {
        return match ($periodType) {
            KpiPeriodType::Quarterly->value => 'Quý ' . $value . '/' . $year,
            KpiPeriodType::Yearly->value => 'Năm ' . $year,
            default => 'Tháng ' . $value . '/' . $year,
        };
    }

    public function departmentStatusMeta(float $avgScore): array
    {
        if ($avgScore >= 90) {
            return ['label' => 'Xuất sắc', 'color' => 'emerald', 'bg' => 'bg-emerald-100', 'text' => 'text-emerald-800'];
        }
        if ($avgScore >= 80) {
            return ['label' => 'Giỏi', 'color' => 'blue', 'bg' => 'bg-blue-100', 'text' => 'text-blue-800'];
        }
        if ($avgScore >= 70) {
            return ['label' => 'Khá', 'color' => 'cyan', 'bg' => 'bg-cyan-100', 'text' => 'text-cyan-800'];
        }
        if ($avgScore >= 50) {
            return ['label' => 'Đạt', 'color' => 'amber', 'bg' => 'bg-amber-100', 'text' => 'text-amber-800'];
        }

        return ['label' => 'Yếu', 'color' => 'red', 'bg' => 'bg-red-100', 'text' => 'text-red-800'];
    }

    public function exportExcel(?string $format = 'xlsx'): mixed
    {
        $stats = Department::query()
            ->with('head:id,name,avatar')
            ->withCount(['activeUsers as active_users_count'])
            ->withAvg(['kpiScores as avg_final_score' => fn($q) => $this->applyPeriodFilter($q)], 'final_score')
            ->withAvg(['kpiScores as avg_sla_rate' => fn($q) => $this->applyPeriodFilter($q)], 'sla_rate')
            ->withAvg(['kpiScores as avg_on_time_rate' => fn($q) => $this->applyPeriodFilter($q)], 'on_time_rate')
            ->withAvg(['kpiScores as avg_star' => fn($q) => $this->applyPeriodFilter($q)], 'avg_star')
            ->when($this->selectedDepartmentId, fn($q) => $q->where('id', $this->selectedDepartmentId))
            ->orderByDesc('avg_final_score')
            ->get();

        $title = 'Báo cáo KPI Toàn công ty';
        $periodLabel = $this->periodLabel($this->periodType, $this->selectedYear, $this->selectedValue);
        $filename = 'kpi-toan-cong-ty-' . $this->selectedValue . '-' . $this->selectedYear . '.' . ($format === 'pdf' ? 'pdf' : 'xlsx');

        $writer = $format === 'pdf' ? \Maatwebsite\Excel\Excel::DOMPDF : \Maatwebsite\Excel\Excel::XLSX;

        $this->dispatch('toast', message: 'Bắt đầu xuất file ' . strtoupper($format), type: 'info');

        $meta = [
            'generated_at' => now()->format('d/m/Y H:i'),
            'generated_by' => auth()->user()?->name ?? 'Hệ thống',
            'formula' => 'Điểm = (% đúng hạn x 0.4) + (% SLA đạt x 0.4) + (sao x 0.2)',
        ];

        return Excel::download(new KpiExport($stats, $title, $periodLabel, 'ceo', $meta), $filename, $writer);
    }
};
?>

<style>
    @keyframes enter {
        from {
            opacity: 0;
            transform: translateY(10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .animate-enter {
        animation: enter 0.5s ease-out forwards;
        opacity: 0;
    }
</style>

<main class="max-w-8xl mx-auto p-4 sm:p-6 lg:p-8">
    <!-- Filter Bar -->
    <div class="animate-enter relative z-20 mb-8 flex flex-col justify-between gap-6 md:flex-row md:items-end"
        style="animation-delay: 0.1s">
        <div>
            <x-ui.heading title="Báo cáo hiệu suất KPI" description="Dữ liệu tổng hợp toàn công ty từ hệ thống"
                class="mb-0" />
        </div>
        <div class="flex flex-col gap-4 md:flex-row md:items-center">
            <div class="flex items-center gap-4 overflow-x-auto pb-2 md:overflow-visible md:pb-0">
                {{-- Period Filters --}}
                <div class="flex shrink-0 flex-col gap-1">
                    <label class="text-2xs ml-1 font-bold uppercase tracking-wider text-slate-400">Kỳ báo cáo</label>
                    <div class="flex items-center gap-2">
                        <x-ui.filter-select model="periodType" :value="$periodType" icon="calendar_month" :permit-all="false"
                            width="w-36" :options="[
                                'monthly' => 'Theo tháng',
                                'quarterly' => 'Theo quý',
                                'yearly' => 'Theo năm',
                            ]" />

                        @if ($periodType !== KpiPeriodType::Yearly->value)
                            <x-ui.filter-select model="selectedValue" :value="$selectedValue" icon="event_note"
                                :permit-all="false" width="w-32" :options="$this->periodValueOptions" />
                        @endif

                        <x-ui.filter-select model="selectedYear" :value="$selectedYear" icon="event" :permit-all="false"
                            width="w-32" :options="collect($this->yearOptions)->mapWithKeys(fn($y) => [$y => 'Năm ' . $y])->all()" />
                    </div>
                </div>

                {{-- Department Filter --}}
                <div class="flex shrink-0 flex-col gap-1">
                    <label class="text-2xs ml-1 font-bold uppercase tracking-wider text-slate-400">Phòng ban</label>
                    <x-ui.filter-select model="selectedDepartmentId" :value="$selectedDepartmentId" icon="apartment"
                        all-label="Tất cả phòng ban" width="w-48" :options="$this->departments->pluck('name', 'id')->all()" />
                </div>
            </div>
        </div>
    </div>

    @php
        $summary = $this->summary;
        $trends = $this->trends;
        $approvalOverview = $this->approvalOverview;
    @endphp

    @php
        $summary = $this->summary;
        $trends = $this->trends;
        $approvalOverview = $this->approvalOverview;

        // Strategic interpretation
        $overallTrend = $trends['avg_score']['direction'];
        $overallTrendLabel = $trends['avg_score']['label'];
    @endphp

    <div class="animate-enter mb-8" style="animation-delay: 0.15s">
        <x-kpi.team-summary :avg-score="$summary['avg_score']" :total-tasks="0" {{-- CEO view might focus on high level scores --}} :sla-rate="$summary['avg_sla_rate']"
            :on-time-rate="$summary['avg_on_time_rate']" :approval-rate="$approvalOverview['approval_rate']" :trend="$overallTrend" :trend-label="$overallTrendLabel" />
    </div>

    <!-- Strategic Insights & Company Spotlight -->
    <div class="animate-enter mb-8 grid grid-cols-1 gap-6 lg:grid-cols-3" style="animation-delay: 0.2s">
        <!-- Strategic Performance Radar -->
        <div
            class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm lg:col-span-2 dark:border-slate-800 dark:bg-slate-900">
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-black uppercase tracking-widest text-slate-700 dark:text-white">Ma trận hiệu
                        suất chiến lược</h3>
                    <p class="text-xs text-slate-400">Tương quan giữa các bộ chỉ số chính toàn công ty</p>
                </div>
                <div class="flex items-center gap-2 rounded-lg bg-slate-50 px-3 py-1.5 dark:bg-slate-800">
                    <span class="material-symbols-outlined text-primary text-sm">groups</span>
                    <span class="text-xs font-bold text-slate-600 dark:text-slate-300">Toàn công ty</span>
                </div>
            </div>

            <div class="flex flex-col items-center gap-8 md:flex-row">
                <div class="w-full max-w-[280px]">
                    <x-kpi.radar-chart :metrics="[
                        'Đúng hạn' => $summary['avg_on_time_rate'],
                        'SLA' => $summary['avg_sla_rate'],
                        'Chất lượng' => $summary['avg_star'] * 20,
                        'Cam kết' => $approvalOverview['approval_rate'],
                        'Ổn định' => 85,
                    ]" :final-score="$summary['avg_score']" />
                </div>
                <div class="grid flex-1 grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="rounded-xl bg-slate-50 p-4 dark:bg-slate-800/50">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Điểm chất lượng (Avg)
                        </p>
                        <p class="text-xl font-black text-slate-700 dark:text-white">
                            {{ number_format($summary['avg_star'], 1) }}/5.0</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-4 dark:bg-slate-800/50">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Độ cam kết SLA</p>
                        <p class="text-primary text-xl font-black">{{ number_format($summary['avg_sla_rate'], 1) }}%</p>
                    </div>
                    <div class="bg-primary/5 col-span-1 rounded-xl p-4 sm:col-span-2">
                        <p class="text-primary/80 text-[10px] font-bold uppercase tracking-wider">Nhận định chiến lược
                        </p>
                        <p class="mt-1 text-xs leading-relaxed text-slate-600 dark:text-slate-400">
                            Hiệu suất toàn công ty đang @if ($overallTrend === 'up')
                                <span class="font-bold italic text-emerald-500">tăng trưởng tích cực</span>
                            @else
                                <span class="font-bold italic text-amber-500">giữ mức ổn định</span>
                            @endif.
                            Tập tập trung cải thiện <span class="text-primary font-bold">tỷ lệ đúng hạn</span> vào các
                            giờ cao điểm để tối ưu hóa cam kết với khách hàng.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- At-Risk Departments -->
        <div class="flex flex-col gap-6">
            <div
                class="rounded-2xl border border-rose-100 bg-rose-50/30 p-6 dark:border-rose-900/40 dark:bg-rose-900/10">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-xs font-black uppercase tracking-widest text-rose-700 dark:text-rose-400">Khu vực
                        cần lưu ý</h3>
                    <span class="material-symbols-outlined text-rose-500">warning</span>
                </div>
                <div class="space-y-4">
                    @php
                        $lowDepartments = collect($this->departmentStats->items())
                            ->where('avg_final_score', '<', 75)
                            ->take(2);
                    @endphp
                    @forelse($lowDepartments as $dept)
                        <div
                            class="flex items-center justify-between rounded-xl bg-white p-3 shadow-sm dark:bg-slate-800">
                            <div>
                                <p class="text-xs font-bold text-slate-700 dark:text-white">{{ $dept->name }}</p>
                                <p class="text-[10px] text-rose-500">Score:
                                    {{ number_format($dept->avg_final_score, 1) }}</p>
                            </div>
                            <span class="material-symbols-outlined text-rose-300">trending_down</span>
                        </div>
                    @empty
                        <p class="text-xs italic text-slate-400">Tất cả phòng ban đều đạt trên 75 điểm.</p>
                    @endforelse
                </div>
            </div>

            <!-- Approval Status -->
            <div
                class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-xs font-black uppercase tracking-widest text-slate-500">Tiến độ phê duyệt</h3>
                    <span class="text-primary text-xs font-black">{{ $approvalOverview['approval_rate'] }}%</span>
                </div>
                <div class="h-2 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                    <div class="bg-primary h-full rounded-full transition-all duration-1000"
                        style="width: {{ $approvalOverview['approval_rate'] }}%"></div>
                </div>
                <p class="mt-4 text-[11px] text-slate-400">
                    {{ $approvalOverview['approved'] }}/{{ $approvalOverview['total'] }} bản ghi đã được chốt và duyệt
                    bởi các cấp Leader.
                </p>
            </div>
        </div>
    </div>


    <div class="grid grid-cols-4 gap-4">
        <div class="animate-enter col-span-3 mb-8 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
            style="animation-delay: 0.26s">
            <div class="flex items-center justify-between border-b border-slate-100 p-6 dark:border-slate-800">
                <div>
                    <h3 class="text-base font-black text-slate-800 dark:text-white">Bảng xếp hạng phòng ban</h3>
                    <p class="text-xs text-slate-400">Dữ liệu hiệu suất trung bình theo từng bộ phận</p>
                </div>
                <div class="flex items-center gap-3">
                    <button wire:click="exportExcel('xlsx')"
                        class="flex size-9 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 transition-all hover:bg-emerald-600 hover:text-white dark:bg-emerald-900/20">
                        <span class="material-symbols-outlined text-[20px]">table_view</span>
                    </button>
                    <button wire:click="exportExcel('pdf')"
                        class="flex size-9 items-center justify-center rounded-xl bg-rose-50 text-rose-600 transition-all hover:bg-rose-600 hover:text-white dark:bg-rose-900/20">
                        <span class="material-symbols-outlined text-[20px]">picture_as_pdf</span>
                    </button>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-left text-sm">
                    <thead>
                        <tr
                            class="bg-slate-50 text-[10px] font-black uppercase tracking-widest text-slate-500 dark:bg-slate-800/50">
                            <th class="px-6 py-4">Phòng ban</th>
                            <th class="px-6 py-4">Quản lý</th>
                            <th class="px-4 py-4 text-center">Quy mô</th>
                            <th class="px-6 py-4 text-center">Avg Score</th>
                            <th class="px-6 py-4 text-center">SLA Met</th>
                            <th class="px-6 py-4 text-right">Phân loại</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($this->departmentStats as $index => $department)
                            @php
                                $avgFinal = (float) ($department->avg_final_score ?? 0);
                                $avgSla = (float) ($department->avg_sla_rate ?? 0);
                                $statusMeta = $this->departmentStatusMeta($avgFinal);

                                $scoreColor = match (true) {
                                    $avgFinal >= 85 => 'text-emerald-600',
                                    $avgFinal >= 75 => 'text-primary',
                                    $avgFinal >= 60 => 'text-amber-600',
                                    default => 'text-rose-600',
                                };
                            @endphp
                            <tr class="group transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="flex size-10 items-center justify-center rounded-xl bg-slate-100 font-black text-slate-400 dark:bg-slate-800">
                                            {{ $department->code }}
                                        </div>
                                        <span
                                            class="font-bold text-slate-700 dark:text-white">{{ $department->name }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        @if ($department->head?->avatar)
                                            <img src="{{ $department->head->avatar }}"
                                                class="size-6 rounded-full object-cover" />
                                        @endif
                                        <span
                                            class="text-xs text-slate-600 dark:text-slate-300">{{ $department->head?->name ?? '—' }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <span
                                        class="rounded-lg bg-slate-50 px-2.5 py-1 text-xs font-bold text-slate-500 dark:bg-slate-800">
                                        {{ $department->active_users_count }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <div class="flex flex-col items-center gap-1">
                                        <span
                                            class="{{ $scoreColor }} text-lg font-black">{{ number_format($avgFinal, 1) }}</span>
                                        <div class="h-1 w-16 rounded-full bg-slate-100 dark:bg-slate-800">
                                            <div class="bg-primary h-full rounded-full"
                                                style="width: {{ $avgFinal }}%"></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span
                                        class="text-xs font-black text-slate-600 dark:text-slate-300">{{ number_format($avgSla, 1) }}%</span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <span
                                        class="{{ $statusMeta['bg'] }} {{ $statusMeta['text'] }} inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-wider">
                                        <div class="size-1.5 rounded-full bg-current opacity-50"></div>
                                        {{ $statusMeta['label'] }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center italic text-slate-400">Chưa có dữ
                                    liệu phòng ban trong kỳ này.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($this->departmentStats->hasPages())
                <div class="border-t border-slate-100 p-4 dark:border-slate-800">
                    {{ $this->departmentStats->links() }}
                </div>
            @endif
        </div>

        <div class="animate-enter col-span-1 mb-8 grid grid-cols-1 gap-8" style="animation-delay: 0.3s">
            <div
                class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h3 class="mb-6 text-lg font-bold">Cá nhân xuất sắc</h3>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-1">
                    @forelse ($this->topPerformers as $index => $score)
                        <div
                            class="flex items-center gap-4 rounded-lg border border-slate-50 p-3 transition-colors hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/50">
                            <div class="relative">
                                <div class="h-10 w-10 overflow-hidden rounded-full bg-slate-200">
                                    @if ($score->user?->avatar)
                                        <img alt="{{ $score->user->name }}" class="h-full w-full object-cover"
                                            src="{{ $score->user->avatar }}" />
                                    @else
                                        <div
                                            class="bg-primary flex h-full w-full items-center justify-center text-xs font-bold text-white">
                                            {{ substr($score->user?->name ?? 'U', 0, 2) }}
                                        </div>
                                    @endif
                                </div>
                                <div
                                    class="{{ $index === 0 ? 'bg-yellow-400' : ($index === 1 ? 'bg-slate-300' : ($index === 2 ? 'bg-amber-600' : 'bg-slate-200')) }} absolute -right-1 -top-1 flex h-4 w-4 items-center justify-center rounded-full text-[8px] font-bold text-white shadow-sm">
                                    {{ $index + 1 }}
                                </div>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-slate-600 dark:text-white">
                                    {{ $score->user?->name ?? 'N/A' }}</p>
                                <p class="text-xs text-slate-500">{{ $score->user?->department?->name ?? '—' }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-primary text-sm font-bold">
                                    {{ number_format((float) $score->final_score, 2) }}</p>
                                <p class="text-2xs text-slate-400">Score</p>
                            </div>
                        </div>
                    @empty
                        <p class="col-span-full py-4 text-center text-sm text-slate-500">Chưa có dữ liệu.</p>
                    @endforelse
                </div>

            </div>
        </div>
    </div>

    @php
        $taskApprovalRows = $this->taskApprovalRows;
        $taskApprovalSummary = $this->taskApprovalSummary;
        $taskKpiScoreMap = $this->taskKpiScoreMap;
    @endphp

    <div class="animate-enter mb-8 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
        style="animation-delay: 0.28s">
        <div
            class="flex flex-col justify-between gap-3 border-b border-slate-100 p-4 md:flex-row md:items-center dark:border-slate-800">
            <div>
                <h3 class="text-lg font-bold text-slate-600 dark:text-white">Task KPI - SLA - Phê duyệt</h3>
                <p class="text-xs text-slate-500 dark:text-slate-300">
                    Toàn bộ công việc trong kỳ để đối soát KPI, SLA và tiến trình phê duyệt.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <div class="flex items-center gap-2">
                    <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Lọc duyệt</span>
                    <x-ui.filter-select model="taskApprovalFilter" :value="$taskApprovalFilter" icon="filter_alt"
                        :permit-all="false" width="w-36" :options="[
                            'all' => 'Tất cả',
                            'approved' => 'Đã duyệt',
                            'waiting' => 'Chờ duyệt',
                            'rejected' => 'Từ chối',
                        ]" />
                </div>
                <span
                    class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-700 dark:text-slate-200">
                    Tổng số công việc: {{ $taskApprovalSummary['total'] }}
                </span>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3 border-b border-slate-100 p-4 md:grid-cols-6 dark:border-slate-800">
            <div class="rounded-lg bg-slate-50 p-3 dark:bg-slate-800/50">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Đã duyệt</p>
                <p class="mt-1 text-xl font-black text-emerald-600">{{ $taskApprovalSummary['approved'] }}</p>
            </div>
            <div class="rounded-lg bg-slate-50 p-3 dark:bg-slate-800/50">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Từ chối</p>
                <p class="mt-1 text-xl font-black text-rose-600">{{ $taskApprovalSummary['rejected'] }}</p>
            </div>
            <div class="rounded-lg bg-slate-50 p-3 dark:bg-slate-800/50">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Đang chờ</p>
                <p class="mt-1 text-xl font-black text-amber-600">{{ $taskApprovalSummary['waiting'] }}</p>
            </div>
            <div class="rounded-lg bg-slate-50 p-3 dark:bg-slate-800/50">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">SLA đạt</p>
                <p class="mt-1 text-xl font-black text-blue-600">{{ $taskApprovalSummary['sla_met'] }}</p>
            </div>
            <div class="rounded-lg bg-slate-50 p-3 dark:bg-slate-800/50">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Avg tiến độ</p>
                <p class="mt-1 text-xl font-black text-slate-600 dark:text-white">
                    {{ number_format((float) $taskApprovalSummary['avg_progress'], 1) }}%</p>
            </div>
            <div class="rounded-lg bg-slate-50 p-3 dark:bg-slate-800/50">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">KPI Avg (PIC)</p>
                <p class="mt-1 text-xl font-black text-slate-600 dark:text-white">
                    {{ number_format((float) ($summary['avg_score'] ?? 0), 1) }}</p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead
                    class="bg-slate-50 text-[10px] font-black uppercase tracking-widest text-slate-500 dark:bg-slate-800/60">
                    <tr>
                        <th class="px-6 py-4">Công việc</th>
                        <th class="px-6 py-4">PIC / Phòng ban</th>
                        <th class="px-6 py-4 text-center">KPI</th>
                        <th class="px-6 py-4 text-center">Trạng thái</th>
                        <th class="px-6 py-4 text-center">Tiến độ</th>
                        <th class="px-6 py-4 text-center">Deadline</th>
                        <th class="px-6 py-4 text-center">SLA</th>
                        <th class="px-6 py-4 text-center">Phê duyệt</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($taskApprovalRows as $task)
                        @php
                            $taskStatusMeta = $this->taskStatusMeta($task);
                            $taskApprovalMeta = $this->taskApprovalMeta($task);
                            $picScore = $task->pic_id ? $taskKpiScoreMap[$task->pic_id] ?? null : null;
                        @endphp
                        <tr class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/40">
                            <td class="px-6 py-4">
                                <p class="font-semibold text-slate-600 dark:text-white">{{ $task->name }}</p>
                                <p class="mt-0.5 text-xs text-slate-500">{{ $task->phase?->project?->name ?? 'N/A' }}
                                    · {{ $task->phase?->name ?? 'N/A' }}</p>
                            </td>
                            <td class="px-6 py-4 text-xs text-slate-600 dark:text-slate-300">
                                <p class="font-semibold text-slate-600 dark:text-white">
                                    {{ $task->pic?->name ?? 'N/A' }}</p>
                                <p class="text-slate-500">{{ $task->pic?->department?->name ?? '—' }}</p>
                            </td>
                            <td class="px-6 py-4 text-center">
                                @if ($picScore !== null)
                                    <span
                                        class="text-primary text-sm font-bold">{{ number_format((float) $picScore, 1) }}</span>
                                @else
                                    <span class="text-xs text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span
                                    class="{{ $taskStatusMeta['class'] }} rounded-full px-2.5 py-1 text-xs font-bold">
                                    {{ $taskStatusMeta['label'] }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <div class="mx-auto w-24">
                                    <div class="mb-1 text-xs font-semibold text-slate-700 dark:text-slate-200">
                                        {{ (int) $task->progress }}%</div>
                                    <div class="h-1.5 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                                        <div class="bg-primary h-full rounded-full"
                                            style="width: {{ max(0, min(100, (int) $task->progress)) }}%"></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-center text-xs text-slate-600 dark:text-slate-300">
                                {{ $task->deadline?->format('d/m/Y') ?? '—' }}
                            </td>
                            <td class="px-6 py-4 text-center text-xs">
                                @if ($task->sla_met === true)
                                    <span
                                        class="rounded bg-emerald-100 px-2 py-0.5 font-semibold text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">Đạt</span>
                                @elseif ($task->sla_met === false)
                                    <span
                                        class="rounded bg-rose-100 px-2 py-0.5 font-semibold text-rose-700 dark:bg-rose-900/30 dark:text-rose-300">Không
                                        đạt</span>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span
                                    class="{{ $taskApprovalMeta['class'] }} inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold">
                                    {{ $taskApprovalMeta['label'] }}
                                </span>
                                @if ($taskApprovalMeta['reviewer'] !== '' || $taskApprovalMeta['star'] !== null)
                                    <p class="mt-1 text-[11px] text-slate-500">
                                        {{ $taskApprovalMeta['level'] !== '' ? $taskApprovalMeta['level'] . ':' : '' }}
                                        {{ $taskApprovalMeta['reviewer'] !== '' ? $taskApprovalMeta['reviewer'] : 'N/A' }}
                                        @if ($taskApprovalMeta['star'] !== null)
                                            · {{ $taskApprovalMeta['star'] }}★
                                        @endif
                                    </p>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-8 text-center text-sm text-slate-500">
                                Chưa có task nào trong kỳ này.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($taskApprovalRows->hasPages())
            <div class="border-t border-slate-100 p-4 dark:border-slate-800">
                {{ $taskApprovalRows->links() }}
            </div>
        @endif
    </div>




</main>
