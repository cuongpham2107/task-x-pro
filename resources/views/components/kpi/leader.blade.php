<?php

use App\Enums\KpiPeriodType;
use App\Exports\KpiExport;
use App\Models\KpiScore;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('KPI phòng ban')] class extends Component {
    use WithPagination;

    public string $periodType = KpiPeriodType::Monthly->value;
    public int $selectedYear;
    public int $selectedValue;
    public ?int $selectedUserId = null;
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
            return collect(range(1, 4))->mapWithKeys(fn(int $value): array => [$value => 'Quý ' . $value])->all();
        }

        return collect(range(1, 12))->mapWithKeys(fn(int $value): array => [$value => 'Tháng ' . $value])->all();
    }

    public function getTeamUsersProperty()
    {
        $departmentId = auth()->user()?->department_id;
        if (!$departmentId) {
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
        if (!$departmentId) {
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
        if (!$departmentId) {
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
        if (!$departmentId) {
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
        return $this->canManageScore($score)
            && in_array((string) $score->status, ['pending', 'locked'], true);
    }

    public function canToggleLock(KpiScore $score): bool
    {
        $actor = auth()->user();

        return $actor !== null
            && $actor->can('kpi.manage')
            && $this->canManageScore($score);
    }

    public function approveScore(int $scoreId): void
    {
        $score = $this->findScoreForAction($scoreId);

        if (! in_array((string) $score->status, ['pending', 'locked'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Chỉ KPI đang chờ duyệt hoặc đã chốt mới được phê duyệt.',
            ]);
        }

        $score->forceFill([
            'status' => 'approved',
            'approved_at' => now(),
        ])->save();
    }

    public function rejectScore(int $scoreId): void
    {
        $score = $this->findScoreForAction($scoreId);

        if (! in_array((string) $score->status, ['pending', 'locked'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Chỉ KPI đang chờ duyệt hoặc đã chốt mới được từ chối.',
            ]);
        }

        $score->forceFill([
            'status' => 'rejected',
            'approved_at' => null,
        ])->save();
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

        $score->forceFill([
            'status' => 'locked',
            'approved_at' => null,
        ])->save();
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

        $score->forceFill([
            'status' => 'pending',
            'approved_at' => null,
        ])->save();
    }

    private function findScoreForAction(int $scoreId): KpiScore
    {
        $score = KpiScore::query()
            ->with('user:id,department_id')
            ->findOrFail($scoreId);

        if (! $this->canManageScore($score)) {
            throw new AuthorizationException('Bạn không có quyền thao tác KPI này.');
        }

        return $score;
    }

    public function exportExcel(?string $format = 'xlsx'): mixed
    {
        $departmentId = auth()->user()?->department_id;
        if (!$departmentId) {
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
        $filename = 'kpi-team-' . $this->selectedValue . '-' . $this->selectedYear . '.' . ($format === 'pdf' ? 'pdf' : 'xlsx');
        $writer = $format === 'pdf' ? \Maatwebsite\Excel\Excel::DOMPDF : \Maatwebsite\Excel\Excel::XLSX;

        $this->dispatch('toast', message: 'Bắt đầu xuất file ' . strtoupper($format), type: 'info');

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
            KpiPeriodType::Quarterly->value => 'Quý ' . $value . '/' . $year,
            KpiPeriodType::Yearly->value => 'Năm ' . $year,
            default => 'Tháng ' . $value . '/' . $year,
        };
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
        <x-ui.heading title="Báo cáo hiệu suất KPI" description="Dữ liệu tổng hợp của Team" class="mb-0" />
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
    @endphp

    <div class="animate-enter mb-6 rounded-xl border border-sky-100 bg-sky-50/70 p-4 dark:border-sky-900/40 dark:bg-sky-900/10"
        style="animation-delay: 0.15s">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h3 class="text-sm font-bold text-sky-900 dark:text-sky-300">Mục tiêu phê duyệt KPI của Leader</h3>
            <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-sky-700 dark:bg-slate-900 dark:text-sky-300">
                Tỷ lệ duyệt: {{ number_format((float) $approvalSummary['approval_rate'], 1) }}%
            </span>
        </div>
        <p class="mb-3 text-xs text-slate-600 dark:text-slate-300">
            Phê duyệt KPI là bước xác nhận dữ liệu task hoàn thành của nhân sự đã phản ánh đúng deadline, SLA và chất lượng đầu ra trước khi chốt báo cáo tháng.
        </p>
        <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
            <div class="rounded-lg bg-white p-3 shadow-sm dark:bg-slate-900/80">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Tổng bản ghi</p>
                <p class="mt-1 text-2xl font-black text-slate-900 dark:text-white">{{ $approvalSummary['total'] }}</p>
            </div>
            <div class="rounded-lg bg-white p-3 shadow-sm dark:bg-slate-900/80">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-amber-600">Chờ duyệt</p>
                <p class="mt-1 text-2xl font-black text-amber-600">{{ $approvalSummary['pending'] }}</p>
            </div>
            <div class="rounded-lg bg-white p-3 shadow-sm dark:bg-slate-900/80">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-emerald-600">Đã duyệt</p>
                <p class="mt-1 text-2xl font-black text-emerald-600">{{ $approvalSummary['approved'] }}</p>
            </div>
            <div class="rounded-lg bg-white p-3 shadow-sm dark:bg-slate-900/80">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-rose-600">Từ chối</p>
                <p class="mt-1 text-2xl font-black text-rose-600">{{ $approvalSummary['rejected'] }}</p>
            </div>
        </div>
    </div>

    <!-- Warning Cards for low performance -->
    <div class="animate-enter mb-8 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3" style="animation-delay: 0.2s">
        <!-- Low SLA Warning -->
        <div
            class="flex items-start gap-4 rounded-xl border border-red-100 bg-red-50 p-4 dark:border-red-900/30 dark:bg-red-900/10">
            <div class="rounded-lg bg-red-500 p-2 text-white">
                <span class="material-symbols-outlined block">warning</span>
            </div>
            <div>
                <h4 class="font-bold text-red-800 dark:text-red-400">Cảnh báo SLA thấp</h4>
                <p class="text-sm text-red-700 dark:text-red-500/80">
                    @if ($warnings['low_sla_count'] > 0)
                        {{ $warnings['low_sla_count'] }} nhân sự có tỷ lệ đạt SLA dưới 75%.
                    @else
                        Không có nhân sự nào dưới 75% SLA.
                    @endif
                </p>
            </div>
        </div>

        <!-- Late Warning -->
        <div
            class="flex items-start gap-4 rounded-xl border border-orange-100 bg-orange-50 p-4 dark:border-orange-900/30 dark:bg-orange-900/10">
            <div class="rounded-lg bg-orange-500 p-2 text-white">
                <span class="material-symbols-outlined block">priority_high</span>
            </div>
            <div>
                <h4 class="font-bold text-orange-800 dark:text-orange-400">Trễ hạn cao</h4>
                <p class="text-sm text-orange-700 dark:text-orange-500/80">
                    @if ($warnings['most_late_user'])
                        {{ $warnings['most_late_user']->user->name }} có tỷ lệ trễ hạn
                        {{ 100 - $warnings['most_late_user']->on_time_rate }}%.
                    @else
                        Tất cả nhân sự đều đảm bảo tiến độ tốt.
                    @endif
                </p>
            </div>
        </div>

        <!-- Top Performer -->
        <div
            class="flex items-start gap-4 rounded-xl border border-emerald-100 bg-emerald-50 p-4 dark:border-emerald-900/30 dark:bg-emerald-900/10">
            <div class="rounded-lg bg-emerald-500 p-2 text-white">
                <span class="material-symbols-outlined block">verified</span>
            </div>
            <div>
                <h4 class="font-bold text-emerald-800 dark:text-emerald-400">Nhân sự xuất sắc</h4>
                <p class="text-sm text-emerald-700 dark:text-emerald-500/80">
                    @if ($warnings['top_performer'])
                        {{ $warnings['top_performer']->user->name }} đạt
                        {{ $warnings['top_performer']->final_score }}/100 điểm.
                    @else
                        Chưa có dữ liệu đánh giá.
                    @endif
                </p>
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
                            $scoreTone =
                                $finalScore >= 85
                                    ? 'bg-emerald-500 shadow-emerald-500/20'
                                    : ($finalScore >= 70
                                        ? 'bg-blue-500 shadow-blue-500/20'
                                        : ($finalScore >= 60
                                            ? 'bg-amber-500 shadow-amber-500/20'
                                            : 'bg-red-600 shadow-red-600/20'));
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
                                        <p class="font-bold text-slate-900 dark:text-white">
                                            {{ $user?->name ?? 'Unknown' }}</p>
                                        <p class="text-xs text-slate-500">{{ $user?->job_title ?? 'N/A' }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-5 text-center">
                                <p class="font-bold text-slate-900 dark:text-white">{{ $score->total_tasks }}</p>
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
                                        class="font-bold text-slate-900 dark:text-white">{{ number_format($score->avg_star, 1) }}</span>
                                    <span class="material-symbols-outlined text-primary fill-[1] text-sm">star</span>
                                </div>
                            </td>
                            <td class="px-6 py-5 text-right">
                                <div
                                    class="{{ $scoreTone }} inline-flex h-12 w-12 items-center justify-center rounded-xl text-lg font-black text-white shadow-lg">
                                    {{ number_format($finalScore, 1) }}
                                </div>
                            </td>
                            <td class="px-4 py-5 text-center">
                                @php
                                    $statusBadge = match($score->status) {
                                        'approved' => ['label' => 'Đã duyệt', 'class' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20'],
                                        'rejected' => ['label' => 'Từ chối', 'class' => 'bg-red-50 text-red-700 ring-red-600/20'],
                                        'locked'   => ['label' => 'Đã chốt', 'class' => 'bg-amber-50 text-amber-700 ring-amber-600/20'],
                                        default    => ['label' => 'Chờ duyệt', 'class' => 'bg-blue-50 text-blue-700 ring-blue-700/10'],
                                    };
                                @endphp
                                <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset {{ $statusBadge['class'] }}">
                                    {{ $statusBadge['label'] }}
                                </span>
                            </td>
                            <td class="px-4 py-5 text-right">
                                @if ($this->canManageScore($score))
                                    <div class="flex items-center justify-end gap-2">
                                        @if ($this->canReviewScore($score))
                                            <button wire:click="approveScore({{ $score->id }})"
                                                class="text-slate-400 transition-colors hover:text-emerald-600"
                                                title="Phê duyệt KPI">
                                                <span class="material-symbols-outlined">check_circle</span>
                                            </button>
                                            <button wire:click="rejectScore({{ $score->id }})"
                                                class="text-slate-400 transition-colors hover:text-rose-600"
                                                title="Từ chối KPI">
                                                <span class="material-symbols-outlined">cancel</span>
                                            </button>
                                        @endif

                                        @if ($this->canToggleLock($score))
                                            @if ($score->status === 'locked')
                                                <button wire:click="unlockScore({{ $score->id }})"
                                                    class="text-slate-400 transition-colors hover:text-amber-600" title="Mở khóa">
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
            <p class="mb-1 text-sm font-medium text-slate-500">Hiệu suất trung bình Team</p>
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
                    class="text-4xl font-black text-slate-900 dark:text-white">{{ number_format($summary['avg_on_time_rate'], 1) }}%</span>
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
            <p class="mb-1 text-sm font-medium text-slate-500">Tổng task hoàn thành</p>
            <div class="flex items-end gap-2">
                <span
                    class="text-4xl font-black text-slate-900 dark:text-white">{{ number_format($summary['total_tasks']) }}</span>
            </div>
            <p class="mt-4 text-xs italic text-slate-400">Trong kỳ báo cáo này</p>
        </div>
        <div
            class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <p class="mb-1 text-sm font-medium text-slate-500">Đánh giá sao trung bình</p>
            <div class="flex items-center gap-2">
                <span
                    class="text-4xl font-black text-slate-900 dark:text-white">{{ number_format($summary['avg_star'], 1) }}</span>
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
</main>
