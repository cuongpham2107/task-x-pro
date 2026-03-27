<?php
use App\Enums\KpiPeriodType;
use App\Exports\KpiExport;
use App\Models\Department;
use App\Models\KpiScore;
use App\Models\Task;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Facades\Excel;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('KPI toàn công ty')] class extends Component {
    use WithPagination;

    public string $periodType = KpiPeriodType::Monthly->value;

    public int $selectedYear;

    public int $selectedValue;

    public ?int $selectedDepartmentId = null;

    public int $perPage = 10;

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

    public function getTaskApprovalRowsProperty(): Collection
    {
        [$periodStart, $periodEnd] = $this->selectedPeriodDateRange();

        return Task::query()
            ->with([
                'pic:id,name,department_id',
                'pic.department:id,name',
                'phase:id,name,project_id',
                'phase.project:id,name',
                'approvalLogs:id,task_id,reviewer_id,approval_level,action,star_rating,created_at',
                'approvalLogs.reviewer:id,name',
            ])
            ->where(function ($query) use ($periodStart, $periodEnd): void {
                $query
                    ->whereBetween('completed_at', [$periodStart, $periodEnd])
                    ->orWhereBetween('started_at', [$periodStart, $periodEnd])
                    ->orWhereBetween('deadline', [$periodStart, $periodEnd]);
            })
            ->when($this->selectedDepartmentId, function ($query): void {
                $query->whereHas('pic', function ($picQuery): void {
                    $picQuery->where('department_id', $this->selectedDepartmentId);
                });
            })
            ->orderByRaw('COALESCE(completed_at, deadline, started_at, created_at) DESC')
            ->limit(100)
            ->get();
    }

    public function getTaskApprovalSummaryProperty(): array
    {
        $tasks = $this->taskApprovalRows;
        if ($tasks->isEmpty()) {
            return [
                'total' => 0,
                'approved' => 0,
                'rejected' => 0,
                'waiting' => 0,
                'sla_met' => 0,
                'avg_progress' => 0.0,
            ];
        }

        $latestActions = $tasks->map(function (Task $task): ?string {
            $latestLog = $task->approvalLogs->sortByDesc('id')->first();

            return $latestLog?->action;
        });

        return [
            'total' => $tasks->count(),
            'approved' => $latestActions->filter(fn(?string $action): bool => $action === 'approved')->count(),
            'rejected' => $latestActions->filter(fn(?string $action): bool => $action === 'rejected')->count(),
            'waiting' => $latestActions->filter(fn(?string $action): bool => $action === 'submitted')->count(),
            'sla_met' => $tasks->filter(fn(Task $task): bool => $task->sla_met === true)->count(),
            'avg_progress' => round((float) $tasks->avg(fn(Task $task): int => (int) $task->progress), 1),
        ];
    }

    public function getTaskKpiScoreMapProperty(): array
    {
        $userIds = $this->taskApprovalRows
            ->pluck('pic_id')
            ->filter()
            ->unique()
            ->values();

        if ($userIds->isEmpty()) {
            return [];
        }

        return KpiScore::query()
            ->where('period_type', $this->periodType)
            ->where('period_year', $this->selectedYear)
            ->where('period_value', $this->selectedValue)
            ->whereIn('user_id', $userIds)
            ->pluck('final_score', 'user_id')
            ->map(fn(float|int|string $score): float => round((float) $score, 1))
            ->all();
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
        if (! $latestLog) {
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
            $startMonth = (($this->selectedValue - 1) * 3) + 1;
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

    <!-- Metric Cards -->
    <div class="animate-enter mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4" style="animation-delay: 0.2s">
        <!-- Final Score Card -->
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="mb-4 flex items-center justify-between">
                <div class="bg-primary/10 text-primary flex h-12 w-12 items-center justify-center rounded-full">
                    <span class="material-symbols-outlined">star</span>
                </div>
                @if ($trends['avg_score']['direction'] !== 'neutral')
                    <span
                        class="{{ $trends['avg_score']['direction'] === 'up' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400' }} inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium">
                        {{ $trends['avg_score']['label'] }}
                    </span>
                @endif
            </div>
            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Điểm Final Score (Avg)</p>
            <h3 class="mt-1 text-3xl font-bold text-slate-900 dark:text-white">
                {{ number_format($summary['avg_score'], 2) }}</h3>
            <p class="mt-2 text-xs italic text-slate-400">Mã chỉ số: BR-002</p>
        </div>

        <!-- On Time Rate Card -->
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="mb-4 flex items-center justify-between">
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-full bg-teal-100 text-teal-600 dark:bg-teal-900/30 dark:text-teal-400">
                    <span class="material-symbols-outlined">schedule</span>
                </div>
                @if ($trends['avg_on_time_rate']['direction'] !== 'neutral')
                    <span
                        class="{{ $trends['avg_on_time_rate']['direction'] === 'up' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400' }} inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium">
                        {{ $trends['avg_on_time_rate']['label'] }}
                    </span>
                @endif
            </div>
            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Tỷ lệ đúng hạn</p>
            <h3 class="mt-1 text-3xl font-bold text-slate-900 dark:text-white">
                {{ number_format($summary['avg_on_time_rate'], 1) }}%</h3>
            <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                <div class="h-full bg-teal-500" style="width: {{ min($summary['avg_on_time_rate'], 100) }}%"></div>
            </div>
        </div>

        <!-- SLA Rate Card -->
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="mb-4 flex items-center justify-between">
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-full bg-orange-100 text-orange-600 dark:bg-orange-900/30 dark:text-orange-400">
                    <span class="material-symbols-outlined">verified</span>
                </div>
                @if ($trends['avg_sla_rate']['direction'] !== 'neutral')
                    <span
                        class="{{ $trends['avg_sla_rate']['direction'] === 'up' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400' }} inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium">
                        {{ $trends['avg_sla_rate']['label'] }}
                    </span>
                @endif
            </div>
            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Tỷ lệ đạt SLA</p>
            <h3 class="mt-1 text-3xl font-bold text-slate-900 dark:text-white">
                {{ number_format($summary['avg_sla_rate'], 1) }}%</h3>
            <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                <div class="h-full bg-orange-500" style="width: {{ min($summary['avg_sla_rate'], 100) }}%"></div>
            </div>
        </div>

        <!-- Star Rating Card -->
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="mb-4 flex items-center justify-between">
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-full bg-green-100 text-green-600 dark:bg-green-900/30 dark:text-green-400">
                    <span class="material-symbols-outlined">thumb_up</span>
                </div>
                @if ($trends['avg_star']['direction'] !== 'neutral')
                    <span
                        class="{{ $trends['avg_star']['direction'] === 'up' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400' }} inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium">
                        {{ $trends['avg_star']['label'] }}
                    </span>
                @endif
            </div>
            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Đánh giá sao trung bình</p>
            <div class="mt-1 flex items-baseline gap-2">
                <h3 class="text-3xl font-bold text-slate-900 dark:text-white">
                    {{ number_format($summary['avg_star'], 1) }}</h3>
                <span class="text-slate-400">/ 5.0</span>
            </div>
            <div class="mt-2 flex gap-0.5 text-amber-400">
                @for ($i = 1; $i <= 5; $i++)
                    <span class="material-symbols-outlined text-sm">
                        {{ $summary['avg_star'] >= $i ? 'star' : ($summary['avg_star'] >= $i - 0.5 ? 'star_half' : 'star_outline') }}
                    </span>
                @endfor
            </div>
        </div>
    </div>

    <div class="animate-enter mb-8 rounded-xl border border-indigo-100 bg-indigo-50/70 p-4 dark:border-indigo-900/40 dark:bg-indigo-900/10"
        style="animation-delay: 0.25s">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
            <h3 class="text-sm font-bold text-indigo-900 dark:text-indigo-300">Tình trạng phê duyệt KPI toàn công ty</h3>
            <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-indigo-700 dark:bg-slate-900 dark:text-indigo-300">
                Tỷ lệ duyệt: {{ number_format((float) $approvalOverview['approval_rate'], 1) }}%
            </span>
        </div>
        <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
            <div class="rounded-lg bg-white p-3 shadow-sm dark:bg-slate-900/80">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Tổng bản ghi</p>
                <p class="mt-1 text-2xl font-black text-slate-900 dark:text-white">{{ $approvalOverview['total'] }}</p>
            </div>
            <div class="rounded-lg bg-white p-3 shadow-sm dark:bg-slate-900/80">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-amber-600">Chờ duyệt</p>
                <p class="mt-1 text-2xl font-black text-amber-600">{{ $approvalOverview['pending'] }}</p>
            </div>
            <div class="rounded-lg bg-white p-3 shadow-sm dark:bg-slate-900/80">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-emerald-600">Đã duyệt</p>
                <p class="mt-1 text-2xl font-black text-emerald-600">{{ $approvalOverview['approved'] }}</p>
            </div>
            <div class="rounded-lg bg-white p-3 shadow-sm dark:bg-slate-900/80">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-rose-600">Từ chối</p>
                <p class="mt-1 text-2xl font-black text-rose-600">{{ $approvalOverview['rejected'] }}</p>
            </div>
        </div>
        <p class="mt-3 text-xs text-slate-600 dark:text-slate-300">
            KPI đã duyệt là dữ liệu đã được Leader xác nhận để dùng trong đánh giá hiệu suất và báo cáo quản trị.
        </p>
    </div>

    @php
        $taskApprovalRows = $this->taskApprovalRows;
        $taskApprovalSummary = $this->taskApprovalSummary;
        $taskKpiScoreMap = $this->taskKpiScoreMap;
    @endphp

    <div class="animate-enter mb-8 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
        style="animation-delay: 0.28s">
        <div class="flex flex-col justify-between gap-3 border-b border-slate-100 p-4 md:flex-row md:items-center dark:border-slate-800">
            <div>
                <h3 class="text-lg font-bold text-slate-900 dark:text-white">Task KPI - SLA - Phê duyệt</h3>
                <p class="text-xs text-slate-500 dark:text-slate-300">
                    Toàn bộ task trong kỳ để đối soát KPI, SLA và tiến trình phê duyệt.
                </p>
            </div>
            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-700 dark:text-slate-200">
                Tổng task: {{ $taskApprovalSummary['total'] }}
            </span>
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
                <p class="mt-1 text-xl font-black text-slate-900 dark:text-white">{{ number_format((float) $taskApprovalSummary['avg_progress'], 1) }}%</p>
            </div>
            <div class="rounded-lg bg-slate-50 p-3 dark:bg-slate-800/50">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">KPI Avg (PIC)</p>
                <p class="mt-1 text-xl font-black text-slate-900 dark:text-white">{{ number_format((float) ($summary['avg_score'] ?? 0), 1) }}</p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-slate-50 text-[10px] font-black uppercase tracking-widest text-slate-500 dark:bg-slate-800/60">
                    <tr>
                        <th class="px-6 py-4">Công việc</th>
                        <th class="px-6 py-4">PIC / Phòng ban</th>
                        <th class="px-6 py-4 text-center">KPI</th>
                        <th class="px-6 py-4 text-center">Trạng thái</th>
                        <th class="px-6 py-4 text-center">Tiến độ</th>
                        <th class="px-6 py-4 text-center">Deadline</th>
                        <th class="px-6 py-4 text-center">SLA</th>
                        <th class="px-6 py-4 text-center">Task Approver</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($taskApprovalRows as $task)
                        @php
                            $taskStatusMeta = $this->taskStatusMeta($task);
                            $taskApprovalMeta = $this->taskApprovalMeta($task);
                            $picScore = $task->pic_id ? ($taskKpiScoreMap[$task->pic_id] ?? null) : null;
                        @endphp
                        <tr class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/40">
                            <td class="px-6 py-4">
                                <p class="font-semibold text-slate-900 dark:text-white">{{ $task->name }}</p>
                                <p class="mt-0.5 text-xs text-slate-500">{{ $task->phase?->project?->name ?? 'N/A' }} · {{ $task->phase?->name ?? 'N/A' }}</p>
                            </td>
                            <td class="px-6 py-4 text-xs text-slate-600 dark:text-slate-300">
                                <p class="font-semibold text-slate-900 dark:text-white">{{ $task->pic?->name ?? 'N/A' }}</p>
                                <p class="text-slate-500">{{ $task->pic?->department?->name ?? '—' }}</p>
                            </td>
                            <td class="px-6 py-4 text-center">
                                @if ($picScore !== null)
                                    <span class="text-sm font-bold text-primary">{{ number_format((float) $picScore, 1) }}</span>
                                @else
                                    <span class="text-xs text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="{{ $taskStatusMeta['class'] }} rounded-full px-2.5 py-1 text-xs font-bold">
                                    {{ $taskStatusMeta['label'] }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <div class="mx-auto w-24">
                                    <div class="mb-1 text-xs font-semibold text-slate-700 dark:text-slate-200">{{ (int) $task->progress }}%</div>
                                    <div class="h-1.5 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                                        <div class="bg-primary h-full rounded-full" style="width: {{ max(0, min(100, (int) $task->progress)) }}%"></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-center text-xs text-slate-600 dark:text-slate-300">
                                {{ $task->deadline?->format('d/m/Y') ?? '—' }}
                            </td>
                            <td class="px-6 py-4 text-center text-xs">
                                @if ($task->sla_met === true)
                                    <span class="rounded bg-emerald-100 px-2 py-0.5 font-semibold text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">Đạt</span>
                                @elseif ($task->sla_met === false)
                                    <span class="rounded bg-rose-100 px-2 py-0.5 font-semibold text-rose-700 dark:bg-rose-900/30 dark:text-rose-300">Không đạt</span>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="{{ $taskApprovalMeta['class'] }} inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold">
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
    </div>

    <div class="animate-enter grid grid-cols-1 gap-8 lg:grid-cols-3" style="animation-delay: 0.3s">
        <!-- Trend Chart Placeholder -->
        <div
            class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm lg:col-span-2 dark:border-slate-800 dark:bg-slate-900">
            <div class="mb-6 flex items-center justify-between">
                <h3 class="text-lg font-bold">Xu hướng Final Score (BR-002)</h3>
                <div class="flex items-center gap-4 text-xs">
                    <div class="flex items-center gap-1.5">
                        <span class="bg-primary h-2 w-2 rounded-full"></span>
                        <span class="text-slate-500">{{ $selectedYear }}</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="h-2 w-2 rounded-full bg-slate-300"></span>
                        <span class="text-slate-500">{{ $selectedYear - 1 }}</span>
                    </div>
                </div>
            </div>
            <div
                class="chart-gradient-blue relative flex h-72 w-full items-center justify-center rounded-lg border-b border-l border-slate-100 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/20">
                <!-- Static Placeholder for now, as real dynamic SVG generation is complex without a library -->
                <svg class="absolute inset-0 h-full w-full" preserveAspectRatio="none" viewBox="0 0 1000 300">
                    <path d="M0,250 Q100,240 200,200 T400,150 T600,180 T800,100 T1000,80" fill="none"
                        stroke="#0052CC" stroke-width="3" stroke-opacity="0.8"></path>
                    <circle cx="200" cy="200" fill="#0052CC" r="4"></circle>
                    <circle cx="400" cy="150" fill="#0052CC" r="4"></circle>
                    <circle cx="600" cy="180" fill="#0052CC" r="4"></circle>
                    <circle cx="800" cy="100" fill="#0052CC" r="4"></circle>
                    <circle cx="1000" cy="80" fill="#0052CC" r="6"></circle>
                </svg>
                <div class="text-2xs absolute bottom-0 left-0 flex w-full justify-between px-2 pt-4 text-slate-400">
                    <span>T1</span><span>T2</span><span>T3</span><span>T4</span><span>T5</span><span>T6</span><span>T7</span><span>T8</span><span>T9</span><span>T10</span><span>T11</span><span>T12</span>
                </div>
            </div>
        </div>

        <!-- Rankings -->
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h3 class="mb-6 text-lg font-bold">Cá nhân xuất sắc</h3>
            <div class="space-y-4">
                @forelse ($this->topPerformers as $index => $score)
                    <div
                        class="flex items-center gap-4 rounded-lg p-3 transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/50">
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
                            <p class="truncate text-sm font-semibold text-slate-900 dark:text-white">
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
                    <p class="py-4 text-center text-sm text-slate-500">Chưa có dữ liệu.</p>
                @endforelse
            </div>
            <button
                class="mt-6 w-full rounded-lg border border-slate-200 py-2 text-xs font-semibold text-slate-600 transition-all hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                Xem tất cả bảng xếp hạng
            </button>
        </div>
    </div>

    <!-- Department Table -->
    <div class="animate-enter mt-8 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
        style="animation-delay: 0.4s">
        <div class="flex items-center justify-between border-b border-slate-100 p-4 dark:border-slate-800">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white">Hiệu suất theo phòng ban</h3>
            <div class="flex items-center gap-4">
                <button wire:click="exportExcel('xlsx')"
                    class="flex items-center gap-1.5 text-sm font-semibold text-emerald-600 transition-colors hover:text-emerald-700 dark:text-emerald-400">
                    <span class="material-symbols-outlined text-lg">table_view</span>
                    Excel
                </button>
                <div class="h-4 w-px bg-slate-200 dark:bg-slate-700"></div>
                <button wire:click="exportExcel('pdf')"
                    class="flex items-center gap-1.5 text-sm font-semibold text-rose-600 transition-colors hover:text-rose-700 dark:text-rose-400">
                    <span class="material-symbols-outlined text-lg">picture_as_pdf</span>
                    PDF
                </button>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-left">
                <thead>
                    <tr class="bg-slate-50 dark:bg-slate-800/50">
                        <th class="px-6 py-4 text-xs font-bold uppercase text-slate-500">Phòng ban</th>
                        <th class="px-6 py-4 text-xs font-bold uppercase text-slate-500">Trưởng bộ phận</th>
                        <th class="px-6 py-4 text-center text-xs font-bold uppercase text-slate-500">Nhân sự</th>
                        <th class="px-6 py-4 text-center text-xs font-bold uppercase text-slate-500">Avg Final Score
                        </th>
                        <th class="px-6 py-4 text-center text-xs font-bold uppercase text-slate-500">SLA %</th>
                        <th class="px-6 py-4 text-right text-xs font-bold uppercase text-slate-500">Trạng thái</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($this->departmentStats as $department)
                        @php
                            $avgFinal = (float) ($department->avg_final_score ?? 0);
                            $avgSla = (float) ($department->avg_sla_rate ?? 0);
                            $statusMeta = $this->departmentStatusMeta($avgFinal);
                        @endphp
                        <tr class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/30">
                            <td class="px-6 py-4 font-semibold text-slate-900 dark:text-white">
                                {{ $department->name }}
                                <p class="text-2xs font-normal text-slate-400">{{ $department->code }}</p>
                            </td>
                            <td class="px-6 py-4 text-slate-700 dark:text-slate-300">
                                {{ $department->head?->name ?? '—' }}
                            </td>
                            <td class="px-6 py-4 text-center text-slate-700 dark:text-slate-300">
                                {{ $department->active_users_count }}
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="text-primary font-bold">{{ number_format($avgFinal, 2) }}</span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span
                                    class="{{ $avgSla >= 90 ? 'text-green-600' : ($avgSla >= 75 ? 'text-blue-600' : 'text-orange-600') }}">
                                    {{ number_format($avgSla, 1) }}%
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <span
                                    class="{{ $statusMeta['bg'] }} {{ $statusMeta['text'] }} inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium">
                                    {{ $statusMeta['label'] }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-slate-500">Chưa có dữ liệu phòng ban.
                            </td>
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
</main>
