<?php
use App\Enums\KpiPeriodType;
use App\Exports\KpiExport;
use App\Models\Department;
use App\Models\KpiScore;
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
        if ($avgScore >= 9.0) {
            return ['label' => 'Xuất sắc', 'color' => 'emerald', 'bg' => 'bg-emerald-100', 'text' => 'text-emerald-800'];
        }
        if ($avgScore >= 8.0) {
            return ['label' => 'Giỏi', 'color' => 'blue', 'bg' => 'bg-blue-100', 'text' => 'text-blue-800'];
        }
        if ($avgScore >= 7.0) {
            return ['label' => 'Khá', 'color' => 'cyan', 'bg' => 'bg-cyan-100', 'text' => 'text-cyan-800'];
        }
        if ($avgScore >= 5.0) {
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
        $filename = 'kpi-toan-cong-ty-' . ($this->selectedValue) . '-' . $this->selectedYear . '.' . ($format === 'pdf' ? 'pdf' : 'xlsx');

        $writer = $format === 'pdf' ? \Maatwebsite\Excel\Excel::DOMPDF : \Maatwebsite\Excel\Excel::XLSX;

        $this->dispatch('toast', message: 'Bắt đầu xuất file ' . strtoupper($format), type: 'info');

        return Excel::download(new KpiExport($stats, $title, $periodLabel, 'ceo'), $filename, $writer);
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

<main class="mx-auto max-w-[1440px] p-4 sm:p-6 lg:p-8">
    <!-- Filter Bar -->
    <div class="animate-enter relative z-20 mb-8 flex flex-col justify-between gap-6 md:flex-row md:items-end"
        style="animation-delay: 0.1s">
        <div>
            <h2 class="text-3xl font-black tracking-tight text-slate-900 dark:text-white">Báo cáo hiệu suất KPI</h2>
            <p class="mt-1 text-slate-500 dark:text-slate-400">Dữ liệu tổng hợp toàn công ty từ hệ thống</p>
        </div>
        <div class="flex flex-col gap-4 md:flex-row md:items-center">
            <div class="flex items-center gap-4 overflow-x-auto pb-2 md:overflow-visible md:pb-0">
                {{-- Period Filters --}}
                <div class="flex shrink-0 flex-col gap-1">
                    <label class="ml-1 text-[10px] font-bold uppercase tracking-wider text-slate-400">Kỳ báo cáo</label>
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
                    <label class="ml-1 text-[10px] font-bold uppercase tracking-wider text-slate-400">Phòng ban</label>
                    <x-ui.filter-select model="selectedDepartmentId" :value="$selectedDepartmentId" icon="apartment"
                        all-label="Tất cả phòng ban" width="w-48" :options="$this->departments->pluck('name', 'id')->all()" />
                </div>
            </div>
        </div>
    </div>

    @php
        $summary = $this->summary;
        $trends = $this->trends;
    @endphp

    <!-- Metric Cards -->
    <div class="animate-enter mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4" style="animation-delay: 0.2s">
        <!-- Final Score Card -->
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
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
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
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
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
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
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
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

    <div class="animate-enter grid grid-cols-1 gap-8 lg:grid-cols-3" style="animation-delay: 0.3s">
        <!-- Trend Chart Placeholder -->
        <div
            class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-2 dark:border-slate-800 dark:bg-slate-900">
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
                <div class="absolute bottom-0 left-0 flex w-full justify-between px-2 pt-4 text-[10px] text-slate-400">
                    <span>T1</span><span>T2</span><span>T3</span><span>T4</span><span>T5</span><span>T6</span><span>T7</span><span>T8</span><span>T9</span><span>T10</span><span>T11</span><span>T12</span>
                </div>
            </div>
        </div>

        <!-- Rankings -->
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
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
                            <p class="text-[10px] text-slate-400">Score</p>
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
        <div class="flex items-center justify-between border-b border-slate-100 p-6 dark:border-slate-800">
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
                                <p class="text-[10px] font-normal text-slate-400">{{ $department->code }}</p>
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
