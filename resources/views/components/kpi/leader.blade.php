<?php

use App\Enums\KpiPeriodType;
use App\Exports\KpiExport;
use App\Models\KpiScore;
use App\Models\Task;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;

new #[Title('KPI phòng ban')] class extends Component
{
    use WithPagination;

    public string $periodType = KpiPeriodType::Monthly->value;

    public int $selectedYear;

    public int $selectedValue;

    public ?int $selectedUserId = null;

    public int $perPage = 10;

    public bool $showTaskReviewModal = false;

    public ?int $reviewScoreId = null;

    public ?int $reviewUserId = null;

    public string $reviewUserName = '';

    public string $reviewApprovalFilter = 'all';

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

    public function updatedSelectedUserId(): void
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
            return collect(range(1, 4))->mapWithKeys(fn (int $value): array => [$value => 'Quý '.$value])->all();
        }

        return collect(range(1, 12))->mapWithKeys(fn (int $value): array => [$value => 'Tháng '.$value])->all();
    }

    public function getTeamUsersProperty()
    {
        $departmentId = auth()->user()?->department_id;
        if (! $departmentId) {
            return collect();
        }

        return User::query()
            ->where('department_id', $departmentId)
            ->orderBy('name')
            ->get(['id', 'name', 'job_title', 'avatar']);
    }

    public function getScoresProperty()
    {
        $departmentId = auth()->user()?->department_id;
        if (! $departmentId) {
            return KpiScore::query()->where('id', 0)->paginate($this->perPage);
        }

        return KpiScore::query()
            ->with('user:id,name,department_id,job_title,avatar')
            ->where('period_type', $this->periodType)
            ->where('period_year', $this->selectedYear)
            ->where('period_value', $this->selectedValue)
            ->whereHas('user', function ($query) use ($departmentId): void {
                $query->where('department_id', $departmentId);
            })
            ->when($this->selectedUserId, function ($query): void {
                $query->where('user_id', $this->selectedUserId);
            })
            ->orderByDesc('final_score')
            ->paginate($this->perPage);
    }

    public function getSummaryProperty(): array
    {
        $departmentId = auth()->user()?->department_id;
        if (! $departmentId) {
            return [
                'avg_score' => 0.0,
                'avg_on_time_rate' => 0.0,
                'avg_sla_rate' => 0.0,
                'avg_star' => 0.0,
                'total_tasks' => 0,
                'pending' => 0,
                'low' => 0,
            ];
        }

        $baseQuery = KpiScore::query()
            ->where('period_type', $this->periodType)
            ->where('period_year', $this->selectedYear)
            ->where('period_value', $this->selectedValue)
            ->whereHas('user', function ($query) use ($departmentId): void {
                $query->where('department_id', $departmentId);
            });

        return [
            'avg_score' => round((float) (clone $baseQuery)->avg('final_score'), 2),
            'avg_on_time_rate' => round((float) (clone $baseQuery)->avg('on_time_rate'), 2),
            'avg_sla_rate' => round((float) (clone $baseQuery)->avg('sla_rate'), 2),
            'avg_star' => round((float) (clone $baseQuery)->avg('avg_star'), 2),
            'total_tasks' => (clone $baseQuery)->sum('total_tasks'),
            'pending' => (clone $baseQuery)->where('status', 'pending')->count(),
            'low' => (clone $baseQuery)->where('final_score', '<', 60)->count(),
        ];
    }

    public function getApprovalSummaryProperty(): array
    {
        $departmentId = auth()->user()?->department_id;
        if (! $departmentId) {
            return [
                'total' => 0,
                'pending' => 0,
                'approved' => 0,
                'rejected' => 0,
                'approval_rate' => 0.0,
            ];
        }

        $baseQuery = KpiScore::query()
            ->where('period_type', $this->periodType)
            ->where('period_year', $this->selectedYear)
            ->where('period_value', $this->selectedValue)
            ->whereHas('user', function ($query) use ($departmentId): void {
                $query->where('department_id', $departmentId);
            });

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

    public function getWarningsProperty(): array
    {
        $departmentId = auth()->user()?->department_id;
        if (! $departmentId) {
            return [
                'low_sla_count' => 0,
                'most_late_user' => null,
                'top_performer' => null,
            ];
        }

        $baseQuery = KpiScore::query()
            ->with('user:id,name,avatar')
            ->where('period_type', $this->periodType)
            ->where('period_year', $this->selectedYear)
            ->where('period_value', $this->selectedValue)
            ->whereHas('user', function ($query) use ($departmentId): void {
                $query->where('department_id', $departmentId);
            });

        $lowSla = (clone $baseQuery)->where('sla_rate', '<', 75)->count();
        $late = (clone $baseQuery)->where('on_time_rate', '<', 70)->orderBy('on_time_rate', 'asc')->first();
        $top = (clone $baseQuery)->orderByDesc('final_score')->first();

        return [
            'low_sla_count' => $lowSla,
            'most_late_user' => $late,
            'top_performer' => $top,
        ];
    }

    public function canManageScore(KpiScore $score): bool
    {
        $actor = auth()->user();
        if ($actor === null) {
            return false;
        }

        if ($actor->can('kpi.manage')) {
            return true;
        }

        if (! $actor->hasRole('leader') || ! $actor->can('kpi.view')) {
            return false;
        }

        $actorDepartmentId = (int) ($actor->department_id ?? 0);
        $scoreDepartmentId = (int) ($score->user?->department_id ?? 0);

        if ($actorDepartmentId <= 0 || $scoreDepartmentId <= 0) {
            return false;
        }

        return $actorDepartmentId === $scoreDepartmentId;
    }

    public function canReviewScore(KpiScore $score): bool
    {
        return $this->canManageScore($score) && in_array((string) $score->status, ['pending', 'locked'], true);
    }

    public function canToggleLock(KpiScore $score): bool
    {
        $actor = auth()->user();

        return $actor !== null && $actor->can('kpi.manage') && $this->canManageScore($score);
    }

    public function resetReviewModalPage(): void
    {
        $this->setPage(1, 'reviewTaskPage');
    }

    public function updatedReviewApprovalFilter(): void
    {
        $this->resetReviewModalPage();
    }

    public function openTaskReview(int $scoreId): void
    {
        $score = $this->findScoreForAction($scoreId);

        $this->reviewScoreId = (int) $score->id;
        $this->reviewUserId = (int) $score->user_id;
        $this->reviewUserName = (string) ($score->user?->name ?? 'Nhân sự');
        $this->resetReviewModalPage();
        $this->showTaskReviewModal = true;
    }

    public function closeTaskReviewModal(): void
    {
        $this->showTaskReviewModal = false;
        $this->reviewScoreId = null;
        $this->reviewUserId = null;
        $this->reviewUserName = '';
    }

    public function getReviewScoreProperty(): ?KpiScore
    {
        if ($this->reviewScoreId === null) {
            return null;
        }

        return KpiScore::query()->with('user:id,name,department_id,job_title,avatar')->find($this->reviewScoreId);
    }

    private function getReviewTasksQuery(): Builder
    {
        [$periodStart, $periodEnd] = $this->selectedPeriodDateRange();

        $query = Task::query()
            ->where('tasks.pic_id', $this->reviewUserId)
            ->where('tasks.status', 'completed')
            ->whereNotNull('completed_at')
            ->whereBetween('completed_at', [$periodStart, $periodEnd]);

        if ($this->reviewApprovalFilter === 'approved') {
            $query->whereHas('approvalLogs', function ($q) {
                $q->where('id', function ($sub) {
                    $sub->selectRaw('max(id)')->from('approval_logs')->whereColumn('task_id', 'tasks.id');
                })->where('action', 'approved');
            });
        } elseif ($this->reviewApprovalFilter === 'rejected') {
            $query->whereHas('approvalLogs', function ($q) {
                $q->where('id', function ($sub) {
                    $sub->selectRaw('max(id)')->from('approval_logs')->whereColumn('task_id', 'tasks.id');
                })->where('action', 'rejected');
            });
        } elseif ($this->reviewApprovalFilter === 'waiting') {
            $query->where(function ($q) {
                $q->whereDoesntHave('approvalLogs')
                    ->orWhereHas('approvalLogs', function ($subq) {
                        $subq->where('id', function ($sub) {
                            $sub->selectRaw('max(id)')->from('approval_logs')->whereColumn('task_id', 'tasks.id');
                        })->where('action', 'submitted');
                    });
            });
        }

        return $query;
    }

    public function getReviewTasksProperty()
    {
        if ($this->reviewUserId === null || ! $this->showTaskReviewModal) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, 10);
        }

        return $this->getReviewTasksQuery()
            ->with(['phase:id,name,project_id', 'phase.project:id,name', 'approvalLogs:id,task_id,action,approval_level,star_rating,created_at'])
            ->orderByDesc('completed_at')
            ->paginate(10, ['*'], 'reviewTaskPage');
    }

    public function getReviewTaskSummaryProperty(): array
    {
        if ($this->reviewUserId === null || ! $this->showTaskReviewModal) {
            return [
                'total' => 0, 'completed' => 0, 'waiting_approval' => 0, 'in_progress' => 0,
                'late' => 0, 'avg_progress' => 0.0, 'sla_met' => 0, 'on_time' => 0, 'avg_star' => 0.0,
            ];
        }

        $query = $this->getReviewTasksQuery();
        $total = $query->count();
        if ($total === 0) {
            return [
                'total' => 0, 'completed' => 0, 'waiting_approval' => 0, 'in_progress' => 0,
                'late' => 0, 'avg_progress' => 0.0, 'sla_met' => 0, 'on_time' => 0, 'avg_star' => 0.0,
            ];
        }

        // We still need labels for some stats, let's use some optimized queries
        $completedCount = (clone $query)->where('status', 'completed')->count();
        $stats = (clone $query)->selectRaw('
            AVG(progress) as avg_progress,
            SUM(CASE WHEN sla_met = 1 THEN 1 ELSE 0 END) as sla_met,
            SUM(CASE WHEN deadline < completed_at THEN 1 ELSE 0 END) as late_count
        ')->first();

        // For stars, we need to look into logs
        $avgStar = \App\Models\ApprovalLog::query()
            ->whereIn('task_id', (clone $query)->select('tasks.id'))
            ->where('action', 'approved')
            ->whereNotNull('star_rating')
            ->avg('star_rating');

        $waitingCount = (clone $query)->where(function ($q) {
            $q->whereDoesntHave('approvalLogs')
                ->orWhereHas('approvalLogs', function ($sub) {
                    $sub->where('id', function ($inner) {
                        $inner->selectRaw('max(id)')->from('approval_logs')->whereColumn('task_id', 'tasks.id');
                    })->where('action', 'submitted');
                });
        })->count();

        return [
            'total' => $total,
            'completed' => $completedCount,
            'waiting_approval' => $waitingCount,
            'in_progress' => 0,
            'late' => (int) $stats->late_count,
            'avg_progress' => round((float) $stats->avg_progress, 1),
            'sla_met' => (int) $stats->sla_met,
            'on_time' => $completedCount - (int) $stats->late_count,
            'avg_star' => round((float) ($avgStar ?? 0), 1),
        ];
    }

    public function getReviewPeriodLabelProperty(): string
    {
        return $this->periodLabel($this->periodType, $this->selectedYear, $this->selectedValue);
    }

    public function approveScore(int $scoreId): void
    {
        $score = $this->findScoreForAction($scoreId);

        if (! in_array((string) $score->status, ['pending', 'locked'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Chỉ KPI đang chờ duyệt hoặc đã chốt mới được phê duyệt.',
            ]);
        }

        $score
            ->forceFill([
                'status' => 'approved',
                'approved_at' => now(),
            ])
            ->save();

        if ($this->reviewScoreId === (int) $score->id) {
            $this->closeTaskReviewModal();
        }

        $this->dispatch('toast', message: 'Đã duyệt KPI cho nhân sự.', type: 'success');
    }

    public function rejectScore(int $scoreId): void
    {
        $score = $this->findScoreForAction($scoreId);

        if (! in_array((string) $score->status, ['pending', 'locked'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Chỉ KPI đang chờ duyệt hoặc đã chốt mới được từ chối.',
            ]);
        }

        $score
            ->forceFill([
                'status' => 'rejected',
                'approved_at' => null,
            ])
            ->save();

        if ($this->reviewScoreId === (int) $score->id) {
            $this->closeTaskReviewModal();
        }

        $this->dispatch('toast', message: 'Đã từ chối KPI, vui lòng yêu cầu nhân sự bổ sung/cải thiện.', type: 'warning');
    }

    public function lockScore(int $scoreId): void
    {
        if (! auth()->user()?->can('kpi.manage')) {
            throw new AuthorizationException('Bạn không có quyền chốt KPI.');
        }

        $score = $this->findScoreForAction($scoreId);

        if ((string) $score->status === 'approved') {
            throw ValidationException::withMessages([
                'status' => 'Không thể chốt KPI đã phê duyệt.',
            ]);
        }

        $score
            ->forceFill([
                'status' => 'locked',
                'approved_at' => null,
            ])
            ->save();
    }

    public function unlockScore(int $scoreId): void
    {
        if (! auth()->user()?->can('kpi.manage')) {
            throw new AuthorizationException('Bạn không có quyền mở khóa KPI.');
        }

        $score = $this->findScoreForAction($scoreId);

        if ((string) $score->status !== 'locked') {
            throw ValidationException::withMessages([
                'status' => 'Chỉ KPI đã chốt mới được mở khóa.',
            ]);
        }

        $score
            ->forceFill([
                'status' => 'pending',
                'approved_at' => null,
            ])
            ->save();
    }

    private function findScoreForAction(int $scoreId): KpiScore
    {
        $score = KpiScore::query()->with('user:id,department_id')->findOrFail($scoreId);

        if (! $this->canManageScore($score)) {
            throw new AuthorizationException('Bạn không có quyền thao tác KPI này.');
        }

        return $score;
    }

    public function exportExcel(?string $format = 'xlsx'): mixed
    {
        $departmentId = auth()->user()?->department_id;
        if (! $departmentId) {
            return null;
        }

        $scores = KpiScore::query()
            ->with('user:id,name,job_title')
            ->where('period_type', $this->periodType)
            ->where('period_year', $this->selectedYear)
            ->where('period_value', $this->selectedValue)
            ->whereHas('user', function ($query) use ($departmentId): void {
                $query->where('department_id', $departmentId);
            })
            ->when($this->selectedUserId, function ($query): void {
                $query->where('user_id', $this->selectedUserId);
            })
            ->orderByDesc('final_score')
            ->get();

        $title = 'Báo cáo KPI Phòng ban';
        $periodLabel = $this->periodLabel($this->periodType, $this->selectedYear, $this->selectedValue);
        $filename = 'kpi-team-'.$this->selectedValue.'-'.$this->selectedYear.'.'.($format === 'pdf' ? 'pdf' : 'xlsx');
        $writer = $format === 'pdf' ? \Maatwebsite\Excel\Excel::DOMPDF : \Maatwebsite\Excel\Excel::XLSX;

        $this->dispatch('toast', message: 'Bắt đầu xuất file '.strtoupper($format), type: 'info');

        $meta = [
            'generated_at' => now()->format('d/m/Y H:i'),
            'generated_by' => auth()->user()?->name ?? 'Hệ thống',
            'formula' => 'Điểm = (% đúng hạn x 0.4) + (% SLA đạt x 0.4) + (sao x 0.2)',
        ];

        return Excel::download(new KpiExport($scores, $title, $periodLabel, 'leader', $meta), $filename, $writer);
    }

    public function periodLabel(string $periodType, int $year, int $value): string
    {
        return match ($periodType) {
            KpiPeriodType::Quarterly->value => 'Quý '.$value.'/'.$year,
            KpiPeriodType::Yearly->value => 'Năm '.$year,
            default => 'Tháng '.$value.'/'.$year,
        };
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

<main class="w-full flex-1 p-4 md:p-8">
    <!-- Page Header & Filters -->
    <div class="animate-enter relative z-20 mb-8 flex flex-col justify-between gap-6 md:flex-row md:items-end"
        style="animation-delay: 0.1s">
        <x-ui.heading title="Báo cáo hiệu suất KPI" description="Dữ liệu tổng hợp của phòng ban" class="mb-0" />
        <div class="flex flex-col gap-4 md:flex-row md:items-center">
            <div class="flex items-center gap-2 overflow-x-auto pb-2 md:overflow-visible md:pb-0">
                <div class="flex shrink-0 flex-col gap-1">
                    <label class="ml-1 text-[10px] font-bold uppercase tracking-wider text-slate-400">Kỳ báo cáo</label>
                    <div class="flex items-center gap-2">
                        <x-ui.filter-select model="periodType" :value="$periodType" icon="calendar_month" :permit-all="false"
                            width="w-32" :options="[
                                'monthly' => 'Tháng',
                                'quarterly' => 'Quý',
                                'yearly' => 'Năm',
                            ]" />

                        @if ($periodType !== 'yearly')
                            <x-ui.filter-select model="selectedValue" :value="$selectedValue" icon="event_note"
                                :permit-all="false" width="w-32" :options="$this->periodValueOptions" />
                        @endif

                        <x-ui.filter-select model="selectedYear" :value="$selectedYear" icon="event" :permit-all="false"
                            width="w-32" :options="collect($this->yearOptions)->mapWithKeys(fn($y) => [$y => $y])->all()" />
                    </div>
                </div>

                <div class="flex shrink-0 flex-col gap-1">
                    <label class="ml-1 text-[10px] font-bold uppercase tracking-wider text-slate-400">Lọc nhân
                        sự</label>
                    <x-ui.filter-select model="selectedUserId" :value="$selectedUserId" label="Tình nhân viên" icon="person"
                        all-label="Tất cả nhân sự" width="w-56" :options="collect($this->teamUsers)->mapWithKeys(fn($u) => [$u->id => $u->name])->all()" />
                </div>

                <div class="mb-0.5 flex shrink-0 flex-col gap-1 self-end">
                    <div class="flex items-center gap-2">
                        <button wire:click="exportExcel('xlsx')"
                            class="flex h-[38px] items-center rounded-xl border border-emerald-200 bg-emerald-50 px-3 text-emerald-600 shadow-sm transition-all hover:bg-emerald-600 hover:text-white"
                            title="Xuất Excel">
                            <span class="material-symbols-outlined text-[20px]">table_view</span>
                        </button>
                        <button wire:click="exportExcel('pdf')"
                            class="flex h-[38px] items-center rounded-xl border border-rose-200 bg-rose-50 px-3 text-rose-600 shadow-sm transition-all hover:bg-rose-600 hover:text-white"
                            title="Xuất PDF">
                            <span class="material-symbols-outlined text-[20px]">picture_as_pdf</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @php
        $warnings = $this->warnings;
        $summary = $this->summary;
        $approvalSummary = $this->approvalSummary;

        // Mock trend for now, could be calculated from previous period in a real scenario
        $trend = $summary['avg_score'] > 75 ? 'up' : 'neutral';
        $trendLabel = $trend === 'up' ? '+2.4%' : '';
    @endphp

    <div class="animate-enter mb-8" style="animation-delay: 0.15s">
        <x-kpi.team-summary
            :avg-score="$summary['avg_score']"
            :total-tasks="$summary['total_tasks']"
            :sla-rate="$summary['avg_sla_rate']"
            :on-time-rate="$summary['avg_on_time_rate']"
            :approval-rate="$approvalSummary['approval_rate']"
            :trend="$trend"
            :trend-label="$trendLabel"
        />
    </div>

    <!-- Quick Insights & Leaderboard Preview -->
    <div class="animate-enter mb-8 grid grid-cols-1 gap-6 lg:grid-cols-3" style="animation-delay: 0.2s">
        <!-- Spotlight / Warnings -->
        <div class="flex flex-col gap-4 lg:col-span-2">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <!-- Top Performer Spotlight -->
                @if ($warnings['top_performer'])
                    <div class="group relative overflow-hidden rounded-2xl border border-emerald-100 bg-white p-5 dark:border-emerald-900/30 dark:bg-slate-800">
                        <div class="absolute -right-4 -top-4 size-16 rounded-full bg-emerald-50 dark:bg-emerald-900/20"></div>
                        <div class="relative z-10 flex items-center gap-4">
                            <div class="relative">
                                @if($warnings['top_performer']->user->avatar)
                                    <img src="{{ $warnings['top_performer']->user->avatar }}" class="size-12 rounded-xl object-cover" />
                                @else
                                    <div class="flex size-12 items-center justify-center rounded-xl bg-emerald-500 text-lg font-bold text-white uppercase">
                                        {{ substr($warnings['top_performer']->user->name, 0, 1) }}
                                    </div>
                                @endif
                                <div class="absolute -bottom-1 -right-1 flex size-5 items-center justify-center rounded-full bg-amber-400 text-[10px] text-white shadow-sm">
                                    <span class="material-symbols-outlined text-[12px]">workspace_premium</span>
                                </div>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Ngôi sao sáng</p>
                                <h4 class="font-bold text-slate-700 dark:text-white">{{ $warnings['top_performer']->user->name }}</h4>
                                <p class="text-xs text-emerald-600 font-bold">{{ $warnings['top_performer']->final_score }} điểm</p>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Critical Attention -->
                <div class="group relative overflow-hidden rounded-2xl border border-rose-100 bg-white p-5 dark:border-rose-900/30 dark:bg-slate-800">
                    <div class="absolute -right-4 -top-4 size-16 rounded-full bg-rose-50 dark:bg-rose-900/20"></div>
                    <div class="relative z-10 flex items-center gap-4">
                        <div class="flex size-12 items-center justify-center rounded-xl bg-rose-100 text-rose-500 dark:bg-rose-900/40">
                            <span class="material-symbols-outlined">report</span>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Trường hợp cần hỗ trợ</p>
                            @if ($warnings['low_sla_count'] > 0 || $warnings['most_late_user'])
                                <h4 class="font-bold text-slate-700 dark:text-white">
                                    {{ $warnings['low_sla_count'] }} nhân sự SLA thấp
                                </h4>
                                <p class="text-xs text-rose-500 font-bold">Cần rà soát ngay</p>
                            @else
                                <h4 class="font-bold text-emerald-600">Mọi thứ đều ổn định</h4>
                                <p class="text-xs text-slate-400">Không có cảnh báo đỏ</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Summary Text -->
            <div class="rounded-2xl bg-slate-50 p-6 dark:bg-slate-900/50">
                <div class="flex items-start gap-4">
                    <div class="rounded-full bg-white p-2 text-primary shadow-sm dark:bg-slate-800">
                        <span class="material-symbols-outlined">analytics</span>
                    </div>
                    <div>
                        <h4 class="text-sm font-bold text-slate-700 dark:text-white">Đánh giá chung kỳ này</h4>
                        <p class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                            Hiệu suất trung bình Team đạt {{ $summary['avg_score'] }} điểm.
                            @if($approvalSummary['pending'] > 0)
                                Có <span class="font-bold text-primary">{{ $approvalSummary['pending'] }} bản ghi đang chờ bạn phê duyệt</span>.
                            @else
                                Tất cả KPI đã được duyệt và chốt dữ liệu thành công.
                            @endif
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mini Leaderboard -->
        <div class="rounded-2xl border border-slate-100 bg-white p-6 dark:border-slate-700 dark:bg-slate-800">
            <h3 class="mb-4 text-xs font-bold uppercase tracking-wider text-slate-500">Top Hiệu Suất</h3>
            <div class="space-y-4">
                @foreach ($this->scores->take(3) as $index => $topScore)
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <span class="text-xs font-black {{ $index == 0 ? 'text-amber-500' : 'text-slate-300' }}">#{{ $index + 1 }}</span>
                            @if($topScore->user->avatar)
                                <img src="{{ $topScore->user->avatar }}" class="size-8 rounded-lg object-cover" />
                            @else
                                <div class="flex size-8 items-center justify-center rounded-lg bg-slate-100 text-[10px] font-bold dark:bg-slate-700">
                                    {{ substr($topScore->user->name, 0, 1) }}
                                </div>
                            @endif
                            <div class="flex flex-col">
                                <span class="text-xs font-bold text-slate-700 dark:text-white">{{ $topScore->user->name }}</span>
                                <span class="text-[10px] text-slate-400">{{ $topScore->user->job_title }}</span>
                            </div>
                        </div>
                        <span class="text-xs font-black text-primary">{{ number_format($topScore->final_score, 1) }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>


    <!-- KPI Table -->
    <div class="animate-enter mb-8 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
        style="animation-delay: 0.3s">
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-left">
                <thead>
                    <tr
                        class="bg-slate-50 text-xs font-bold uppercase tracking-wider text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                        <th class="px-6 py-4">Nhân viên</th>
                        <th class="px-4 py-4 text-center">Tổng Task</th>
                        <th class="px-4 py-4 text-center">Đúng hạn</th>
                        <th class="px-4 py-4 text-center">Đạt SLA</th>
                        <th class="px-4 py-4 text-center">⭐ Avg Star</th>
                        <th class="px-6 py-4 text-right">Final Score</th>
                        <th class="px-4 py-4 text-center">Trạng thái</th>
                        <th class="px-4 py-4 text-right">Hành động</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($this->scores as $score)
                        @php
                            $user = $score->user;
                            $finalScore = (float) $score->final_score;
                            $slaColor =
                                $score->sla_rate >= 80
                                    ? 'bg-blue-500'
                                    : ($score->sla_rate >= 60
                                        ? 'bg-amber-500'
                                        : 'bg-red-500');
                            $onTimeColor =
                                $score->on_time_rate >= 80
                                    ? 'text-emerald-600'
                                    : ($score->on_time_rate >= 60
                                        ? 'text-amber-600'
                                        : 'text-red-600');
                        @endphp
                        <tr class="transition-colors hover:bg-slate-50/50 dark:hover:bg-slate-800/30"
                            wire:key="row-{{ $score->id }}">
                            <td class="px-6 py-5">
                                <div class="flex items-center gap-3">
                                    <div class="h-10 w-10 overflow-hidden rounded-full bg-slate-200">
                                        @if ($user?->avatar)
                                            <img alt="{{ $user->name }}" class="h-full w-full object-cover"
                                                src="{{ $user->avatar }}" />
                                        @else
                                            <div
                                                class="flex h-full w-full items-center justify-center bg-slate-300 font-bold text-slate-500">
                                                {{ substr($user?->name ?? 'U', 0, 1) }}
                                            </div>
                                        @endif
                                    </div>
                                    <div>
                                        <p class="font-bold text-slate-600 dark:text-white">
                                            {{ $user?->name ?? 'Unknown' }}</p>
                                        <p class="text-xs text-slate-500">{{ $user?->job_title ?? 'N/A' }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-5 text-center">
                                <p class="font-bold text-slate-600 dark:text-white">{{ $score->total_tasks }}</p>
                                <p class="text-[10px] text-slate-400">Tasks</p>
                            </td>
                            <td class="px-4 py-5 text-center">
                                <p class="{{ $onTimeColor }} font-bold">{{ $score->on_time_tasks }}</p>
                                <div class="flex items-center justify-center gap-1">
                                    <span
                                        class="{{ $onTimeColor }} text-xs font-semibold">{{ number_format($score->on_time_rate, 1) }}%</span>
                                </div>
                            </td>
                            <td class="px-4 py-5 text-center">
                                <p class="font-bold text-blue-600">{{ $score->sla_met_tasks }}</p>
                                <div
                                    class="mx-auto mt-1 h-1 w-16 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-700">
                                    <div class="{{ $slaColor }} h-full" style="width: {{ $score->sla_rate }}%">
                                    </div>
                                </div>
                                <p class="mt-0.5 text-[10px] text-slate-400">{{ number_format($score->sla_rate, 1) }}%
                                    SLA</p>
                            </td>
                            <td class="px-4 py-5 text-center">
                                <div class="flex items-center justify-center gap-1">
                                    <span
                                        class="font-bold text-slate-600 dark:text-white">{{ number_format($score->avg_star, 1) }}</span>
                                    <span class="material-symbols-outlined text-primary fill-[1] text-sm">star</span>
                                </div>
                            </td>
                            <td class="px-6 py-5 text-right">
                                <div class="items-center justify-center text-sm font-black">
                                    {{ number_format($finalScore, 1) }}
                                </div>
                            </td>
                            <td class="px-4 py-5 text-center">
                                @php
                                    $statusBadge = match ($score->status) {
                                        'approved' => [
                                            'label' => 'Đã duyệt',
                                            'class' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
                                        ],
                                        'rejected' => [
                                            'label' => 'Từ chối',
                                            'class' => 'bg-red-50 text-red-700 ring-red-600/20',
                                        ],
                                        'locked' => [
                                            'label' => 'Đã chốt',
                                            'class' => 'bg-amber-50 text-amber-700 ring-amber-600/20',
                                        ],
                                        default => [
                                            'label' => 'Chờ duyệt',
                                            'class' => 'bg-blue-50 text-blue-700 ring-blue-700/10',
                                        ],
                                    };
                                @endphp
                                <span
                                    class="{{ $statusBadge['class'] }} inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset">
                                    {{ $statusBadge['label'] }}
                                </span>
                            </td>
                            <td class="px-4 py-5 text-right">
                                @if ($this->canManageScore($score))
                                    <div class="flex items-center justify-end gap-2">
                                        <button wire:click="openTaskReview({{ $score->id }})"
                                            class="hover:text-primary text-slate-400 transition-colors"
                                            title="Xem chi tiết task theo kỳ trước khi duyệt">
                                            <span class="material-symbols-outlined">visibility</span>
                                        </button>

                                        @if ($this->canToggleLock($score))
                                            @if ($score->status === 'locked')
                                                <button wire:click="unlockScore({{ $score->id }})"
                                                    class="text-slate-400 transition-colors hover:text-amber-600"
                                                    title="Mở khóa">
                                                    <span class="material-symbols-outlined">lock_open</span>
                                                </button>
                                            @else
                                                <button wire:click="lockScore({{ $score->id }})"
                                                    class="text-slate-400 transition-colors hover:text-emerald-600"
                                                    title="Chốt KPI">
                                                    <span class="material-symbols-outlined">lock</span>
                                                </button>
                                            @endif
                                        @endif
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center text-slate-500">
                                <div class="flex flex-col items-center justify-center">
                                    <span class="material-symbols-outlined mb-2 text-4xl text-slate-300">inbox</span>
                                    <p>Không có dữ liệu KPI cho bộ lọc này.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-100 p-4 dark:border-slate-800">
            {{ $this->scores->links() }}
        </div>
    </div>

    <!-- Team Insights & Metrics Summary -->
    <div class="animate-enter grid grid-cols-1 gap-6 lg:grid-cols-4" style="animation-delay: 0.4s">
        <div
            class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <p class="mb-1 text-sm font-medium text-slate-500">Hiệu suất trung bình phòng ban</p>
            <div class="flex items-end gap-2">
                <span class="text-primary text-4xl font-black">{{ number_format($summary['avg_score'], 1) }}</span>
            </div>
            <div class="mt-4 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                <div class="bg-primary h-full" style="width: {{ $summary['avg_score'] }}%"></div>
            </div>
        </div>
        <div
            class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <p class="mb-1 text-sm font-medium text-slate-500">Tỷ lệ đúng hạn tổng</p>
            <div class="flex items-end gap-2">
                <span
                    class="text-4xl font-black text-slate-600 dark:text-white">{{ number_format($summary['avg_on_time_rate'], 1) }}%</span>
            </div>
            <div class="mt-4 flex gap-1">
                @php $onTimeBlocks = floor($summary['avg_on_time_rate'] / 25); @endphp
                @for ($i = 0; $i < 4; $i++)
                    <div
                        class="{{ $i < $onTimeBlocks ? 'bg-emerald-500' : 'bg-slate-200 dark:bg-slate-800' }} h-1.5 flex-1 rounded-full">
                    </div>
                @endfor
            </div>
        </div>
        <div
            class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <p class="mb-1 text-sm font-medium text-slate-500">Tổng số công việc hoàn thành</p>
            <div class="flex items-end gap-2">
                <span
                    class="text-4xl font-black text-slate-600 dark:text-white">{{ number_format($summary['total_tasks']) }}</span>
            </div>
            <p class="mt-4 text-xs italic text-slate-400">Trong kỳ báo cáo này</p>
        </div>
        <div
            class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <p class="mb-1 text-sm font-medium text-slate-500">Đánh giá sao trung bình</p>
            <div class="flex items-center gap-2">
                <span
                    class="text-4xl font-black text-slate-600 dark:text-white">{{ number_format($summary['avg_star'], 1) }}</span>
                <div class="text-primary flex">
                    @for ($i = 1; $i <= 5; $i++)
                        @if ($i <= round($summary['avg_star']))
                            <span class="material-symbols-outlined fill-[1] text-sm">star</span>
                        @else
                            <span class="material-symbols-outlined text-sm">star_border</span>
                        @endif
                    @endfor
                </div>
            </div>
            <p class="mt-4 text-xs text-slate-400">Dựa trên kết quả đánh giá</p>
        </div>
    </div>

    <x-ui.modal wire:model="showTaskReviewModal" maxWidth="6xl"
        wire:key="kpi-task-review-modal-{{ $reviewScoreId ?? 'none' }}">
        <x-slot name="header">
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-xl bg-primary/10 text-primary">
                    <span class="material-symbols-outlined text-[24px]">fact_check</span>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-slate-800 dark:text-white">Đối soát & Phê duyệt KPI</h3>
                    <p class="text-xs text-slate-500">Kiểm tra chi tiết công việc trước khi xác nhận hiệu suất.</p>
                </div>
            </div>
        </x-slot>

        @php
            $reviewScore = $this->reviewScore;
            $reviewTasks = $this->reviewTasks;
            $reviewSummary = $this->reviewTaskSummary;
        @endphp

        @if ($reviewScore)
            <div class="space-y-6">
                <!-- User & Period Info -->
                <div class="flex flex-col justify-between gap-6 rounded-2xl bg-slate-50 p-6 md:flex-row md:items-center dark:bg-slate-900/50">
                    <div class="flex items-center gap-4">
                        @if($reviewScore->user?->avatar)
                            <img src="{{ $reviewScore->user->avatar }}" class="size-14 rounded-2xl object-cover shadow-sm" />
                        @else
                            <div class="flex size-14 items-center justify-center rounded-2xl bg-white text-xl font-black text-primary shadow-sm dark:bg-slate-800">
                                {{ substr($reviewScore->user?->name ?? '?', 0, 1) }}
                            </div>
                        @endif
                        <div>
                            <h4 class="text-xl font-black text-slate-800 dark:text-white">{{ $reviewScore->user?->name }}</h4>
                            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">{{ $reviewScore->user?->job_title }}</p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-6">
                        <div class="text-right">
                            <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Kỳ đánh giá</p>
                            <p class="text-sm font-black text-slate-700 dark:text-slate-200">{{ $this->reviewPeriodLabel }}</p>
                        </div>
                        <div class="h-8 w-px bg-slate-200 dark:bg-slate-700"></div>
                        <div class="text-right">
                            <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Điểm hiện tại</p>
                            <p class="text-xl font-black text-primary">{{ number_format((float) $reviewScore->final_score, 1) }}</p>
                        </div>
                    </div>
                </div>

                <!-- Stats Summary -->
                <div class="grid grid-cols-2 gap-4 md:grid-cols-5">
                    <div class="rounded-2xl border border-slate-100 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Tổng Task</p>
                        <p class="mt-1 text-2xl font-black text-slate-700 dark:text-white">{{ $reviewSummary['total'] }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-100 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-emerald-500">Hoàn thành</p>
                        <p class="mt-1 text-2xl font-black text-emerald-600">{{ $reviewSummary['completed'] }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-100 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-blue-500">Đạt SLA</p>
                        <p class="mt-1 text-2xl font-black text-blue-600">{{ $reviewSummary['sla_met'] }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-100 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-rose-500">Trễ hạn</p>
                        <p class="mt-1 text-2xl font-black text-rose-600">{{ $reviewSummary['late'] }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-100 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-amber-500">Avg Sao</p>
                        <div class="mt-1 flex items-center gap-1">
                            <p class="text-2xl font-black text-amber-600">{{ number_format((float) $reviewSummary['avg_star'], 1) }}</p>
                            <span class="material-symbols-outlined text-[20px] text-amber-400 fill-[1]">star</span>
                        </div>
                    </div>
                </div>

                <!-- Filter & Table -->
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <h4 class="text-sm font-bold text-slate-700 dark:text-slate-300 uppercase tracking-widest">Danh sách công việc đối soát</h4>
                        <div class="flex items-center gap-2">
                            <span class="text-[11px] font-bold text-slate-400 uppercase">Lọc theo duyệt:</span>
                            <x-ui.filter-select model="reviewApprovalFilter" :value="$reviewApprovalFilter" icon="filter_alt"
                                :permit-all="false" width="w-36" :options="[
                                    'all' => 'Tất cả',
                                    'approved' => 'Đã duyệt',
                                    'waiting' => 'Chờ duyệt',
                                    'rejected' => 'Từ chối',
                                ]" />
                        </div>
                    </div>

                    <div class="overflow-hidden rounded-2xl border border-slate-100 dark:border-slate-800">
                        <table class="w-full border-collapse text-left text-sm">
                            <thead>
                                <tr class="bg-slate-50 text-[10px] font-black uppercase tracking-widest text-slate-500 dark:bg-slate-800/50">
                                    <th class="px-6 py-4">Công việc</th>
                                    <th class="px-6 py-4 text-center">Tiền độ</th>
                                    <th class="px-6 py-4 text-center">Deadline</th>
                                    <th class="px-6 py-4 text-center">Hoàn thành</th>
                                    <th class="px-6 py-4 text-center">SLA</th>
                                    <th class="px-6 py-4 text-center">Đánh giá</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                @forelse ($reviewTasks as $task)
                                    @php
                                        $latestApprovedLog = $task->approvalLogs
                                            ->where('action', 'approved')
                                            ->whereNotNull('star_rating')
                                            ->sortByDesc('id')
                                            ->first();
                                    @endphp
                                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30">
                                        <td class="px-6 py-4">
                                            <p class="font-bold text-slate-700 dark:text-white">{{ $task->name }}</p>
                                            <p class="text-[10px] text-slate-400">{{ $task->phase?->project?->name }} / {{ $task->phase?->name }}</p>
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <div class="flex flex-col items-center gap-1">
                                                <span class="text-[11px] font-black text-slate-600 dark:text-slate-300">{{ (int) $task->progress }}%</span>
                                                <div class="h-1 w-12 rounded-full bg-slate-100 dark:bg-slate-800">
                                                    <div class="bg-primary h-full rounded-full" style="width: {{ $task->progress }}%"></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-center text-xs text-slate-500">
                                            {{ $task->deadline?->format('d/m/Y') ?? '—' }}
                                        </td>
                                        <td class="px-6 py-4 text-center text-xs text-slate-600 dark:text-slate-300">
                                            {{ $task->completed_at?->format('d/m/Y') ?? '—' }}
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            @if ($task->sla_met === true)
                                                <span class="material-symbols-outlined text-emerald-500 text-[20px]">check_circle</span>
                                            @elseif($task->sla_met === false)
                                                <span class="material-symbols-outlined text-rose-500 text-[20px]">cancel</span>
                                            @else
                                                <span class="text-slate-300">—</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            @if ($latestApprovedLog)
                                                <div class="flex items-center justify-center gap-0.5 text-amber-500">
                                                    <span class="text-xs font-black">{{ $latestApprovedLog->star_rating }}</span>
                                                    <span class="material-symbols-outlined text-[14px] fill-[1]">star</span>
                                                </div>
                                            @else
                                                <span class="text-slate-300">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-6 py-12 text-center text-slate-400 italic">Không có dữ liệu task phù hợp.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @if ($reviewTasks->hasPages())
                <div class="mt-4">
                    {{ $reviewTasks->links() }}
                </div>
            @endif
        @else
            <div class="py-12 text-center">
                <span class="material-symbols-outlined text-4xl text-slate-300">search_off</span>
                <p class="mt-2 text-slate-500 font-medium">Không tìm thấy dữ liệu KPI để xem chi tiết.</p>
            </div>
        @endif

        <x-slot name="footer">
            <div class="flex w-full items-center justify-between">
                <x-ui.button variant="secondary" wire:click="closeTaskReviewModal">Đóng</x-ui.button>

                @if ($reviewScore && $this->canReviewScore($reviewScore))
                    <div class="flex items-center gap-3">
                        <x-ui.button variant="danger" outline wire:click="rejectScore({{ $reviewScore->id }})"
                            loading="rejectScore({{ $reviewScore->id }})">
                            Từ chối KPI
                        </x-ui.button>
                        <x-ui.button variant="primary" wire:click="approveScore({{ $reviewScore->id }})"
                            loading="approveScore({{ $reviewScore->id }})">
                            Duyệt & Chốt điểm
                        </x-ui.button>
                    </div>
                @endif
            </div>
        </x-slot>
    </x-ui.modal>

</main>
