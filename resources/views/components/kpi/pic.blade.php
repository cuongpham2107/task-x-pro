<?php
use App\Enums\KpiPeriodType;
use App\Exports\KpiExport;
use App\Models\KpiScore;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Facades\Excel;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public string $periodType = KpiPeriodType::Monthly->value;
    public int $selectedYear;
    public int $selectedValue;
    public string $historyPeriodType = KpiPeriodType::Monthly->value;
    public int $historyYear;
    public int $perPage = 10;

    public function mount(): void
    {
        Gate::forUser(auth()->user())->authorize('viewAny', KpiScore::class);

        $now = now();
        $this->selectedYear = (int) $now->year;
        $this->selectedValue = (int) $now->month;
        $this->historyYear = (int) $now->year;
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
    }

    public function getScoresProperty()
    {
        return KpiScore::query()
            ->where('user_id', auth()->id())
            ->where('period_type', $this->historyPeriodType)
            ->where('period_year', $this->historyYear)
            ->orderByDesc('period_year')
            ->orderByDesc('period_value')
            ->paginate($this->perPage);
    }

    public function getCurrentScoreProperty(): ?KpiScore
    {
        return KpiScore::query()
            ->where('user_id', auth()->id())
            ->where('period_type', $this->periodType)
            ->where('period_year', $this->selectedYear)
            ->where('period_value', $this->selectedValue)
            ->first();
    }

    public function exportExcel(?string $format = 'xlsx'): mixed
    {
        $scores = KpiScore::query()
            ->where('user_id', auth()->id())
            ->where('period_type', $this->historyPeriodType)
            ->where('period_year', $this->historyYear)
            ->orderByDesc('period_year')
            ->orderByDesc('period_value')
            ->get();

        $title = 'Báo cáo KPI Cá nhân';
        $periodLabel = 'Lịch sử ' . $this->historyYear;
        $filename = 'kpi-ca-nhan-' . auth()->user()->name . '-' . $this->historyYear . '.' . ($format === 'pdf' ? 'pdf' : 'xlsx');
        $writer = $format === 'pdf' ? \Maatwebsite\Excel\Excel::DOMPDF : \Maatwebsite\Excel\Excel::XLSX;

        $this->dispatch('toast', message: 'Bắt đầu xuất file ' . strtoupper($format), type: 'info');

        return Excel::download(new KpiExport($scores, $title, $periodLabel, 'pic'), $filename, $writer);
    }

    public function getTeamAverageProperty(): float
    {
        $user = auth()->user();
        if (!$user || !$user->department_id) {
            return 0;
        }

        return (float) KpiScore::query()
            ->where('period_type', $this->periodType)
            ->where('period_year', $this->selectedYear)
            ->where('period_value', $this->selectedValue)
            ->whereHas('user', function ($query) use ($user): void {
                $query->where('department_id', $user->department_id);
            })
            ->avg('final_score');
    }

    public function getPeriodValueOptionsProperty(): array
    {
        if ($this->periodType === KpiPeriodType::Monthly->value) {
            return collect(range(1, 12))->mapWithKeys(fn($m) => [$m => "Tháng $m"])->all();
        }

        if ($this->periodType === KpiPeriodType::Quarterly->value) {
            return collect(range(1, 4))->mapWithKeys(fn($q) => [$q => "Quý $q"])->all();
        }

        return [1 => 'Cả năm'];
    }

    public function getYearOptionsProperty(): array
    {
        return collect(range(now()->year - 2, now()->year))
            ->mapWithKeys(fn($y) => [$y => "Năm $y"])
            ->all();
    }

    public function getHistoryYearOptionsProperty(): array
    {
        return collect(range(now()->year - 3, now()->year))
            ->mapWithKeys(fn($y) => [$y => "Năm $y"])
            ->all();
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

<div>
    <main class="w-fulls mx-auto flex-1 space-y-8">
        <!-- Header Section -->
        <div class="animate-enter relative z-20 flex flex-col justify-between gap-6 md:flex-row md:items-end"
            style="animation-delay: 0.1s">
            <x-ui.heading title="Báo cáo KPI Cá nhân" description="Phân tích hiệu suất định kỳ và đánh giá mục tiêu" />
            <div class="flex flex-col gap-4 md:flex-row md:items-center">
                <div class="flex items-center gap-2 overflow-x-auto pb-2 md:overflow-visible md:pb-0">
                    <div class="flex shrink-0 flex-col gap-1">
                        <label class="text-2xs ml-1 font-bold uppercase tracking-wider text-slate-400">Kỳ báo
                            cáo</label>
                        <div class="flex items-center gap-2">
                            <x-ui.filter-select model="periodType" :value="$periodType" icon="calendar_month"
                                :permit-all="false" width="w-36" :options="[
                                    'monthly' => 'Theo tháng',
                                    'quarterly' => 'Theo quý',
                                    'yearly' => 'Theo năm',
                                ]" />

                            @if ($periodType !== KpiPeriodType::Yearly->value)
                                <x-ui.filter-select model="selectedValue" :value="$selectedValue" icon="event_note"
                                    :permit-all="false" width="w-32" :options="$this->periodValueOptions" />
                            @endif

                            <x-ui.filter-select model="selectedYear" :value="$selectedYear" icon="event" :permit-all="false"
                                width="w-32" :options="$this->yearOptions" />
                        </div>
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
            $score = $this->currentScore;
            $finalScore = (float) ($score?->final_score ?? 0);
            $actualScore = (float) ($score?->actual_score ?? $finalScore);
            $targetScore = (float) ($score?->target_score ?? 100);
            $onTimeRate = (float) ($score?->on_time_rate ?? 0);
            $slaRate = (float) ($score?->sla_rate ?? 0);
            $avgStar = (float) ($score?->avg_star ?? 0);
            $starScore = round(($avgStar / 5) * 100, 2);
            $onTimeWeighted = round($onTimeRate * 0.4, 2);
            $slaWeighted = round($slaRate * 0.4, 2);
            $starWeighted = round($starScore * 0.2, 2);
            $gradeLabel =
                $finalScore >= 80 ? 'A - Xuất sắc' : ($finalScore >= 60 ? 'B - Đạt yêu cầu' : 'C - Cần cải thiện');
            $gradeClass =
                $finalScore >= 80
                    ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                    : ($finalScore >= 60
                        ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'
                        : 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400');
            $status = $score?->status ?? 'pending';
            $statusLabel =
                $status === 'approved'
                    ? 'Đã duyệt'
                    : ($status === 'rejected'
                        ? 'Từ chối'
                        : ($status === 'locked'
                            ? 'Đã khóa'
                            : 'Chờ duyệt'));
            $statusClass =
                $status === 'approved'
                    ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                    : ($status === 'rejected'
                        ? 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400'
                        : ($status === 'locked'
                            ? 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-200'
                            : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'));
            $teamAvg = $this->teamAverage;
            $delta = $teamAvg > 0 ? round((($finalScore - $teamAvg) / $teamAvg) * 100, 1) : 0;
            $deltaLabel = $delta >= 0 ? '+' . $delta . '%' : $delta . '%';
            $deltaClass = $delta >= 0 ? 'text-green-600' : 'text-rose-600';
            $deltaIcon = $delta >= 0 ? 'trending_up' : 'trending_down';
            $progressWidth = min(100, $actualScore);
            $hintText =
                $starScore < 70
                    ? 'Chỉ số Đánh giá sao đang thấp, nên tăng chất lượng phản hồi.'
                    : ($onTimeRate < 70
                        ? 'Chỉ số Đúng hạn đang thấp, cần ưu tiên kế hoạch thời gian.'
                        : ($slaRate < 70
                            ? 'Chỉ số SLA đang thấp, cần cải thiện tốc độ xử lý.'
                            : 'Các chỉ số ổn định, tiếp tục duy trì hiệu suất.'));
        @endphp
        <!-- Score Cards Grid -->
        <div class="animate-enter grid grid-cols-1 gap-6 md:grid-cols-3" style="animation-delay: 0.2s">
            <!-- Main Score Card -->
            <div
                class="relative overflow-hidden rounded-2xl border border-slate-100 bg-white p-6 shadow-xl shadow-slate-200/50 md:col-span-2 dark:border-slate-700 dark:bg-slate-800 dark:shadow-none">
                <div class="bg-primary/5 absolute right-0 top-0 h-32 w-32 rounded-bl-full"></div>
                <div class="relative z-10 flex h-full flex-col justify-between">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-widest text-slate-500 dark:text-slate-400">
                                Điểm thực tế kỳ này</p>
                            <h3 class="mt-2 text-6xl font-black text-slate-900 dark:text-white">
                                {{ number_format($actualScore, 1) }}
                                <span class="text-2xl text-slate-400">/{{ number_format($targetScore, 0) }}</span>
                            </h3>
                        </div>
                        <div class="flex flex-col items-end gap-2">
                            <span class="{{ $statusClass }} rounded-full px-3 py-1.5 text-xs font-bold uppercase">
                                {{ $statusLabel }}
                            </span>
                            @if ($score?->approved_at)
                                <span class="text-[10px] text-slate-400">
                                    Duyệt: {{ $score->approved_at->format('d/m/Y') }}
                                </span>
                            @endif
                        </div>
                    </div>
                    <div class="mt-8">
                        <div class="h-3 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-700">
                            <div class="bg-primary h-full rounded-full" style="width: {{ $progressWidth }}%"></div>
                        </div>
                        <div class="mt-2 flex justify-between text-[10px] font-bold uppercase text-slate-400">
                            <span>Cần cố gắng (0-60)</span>
                            <span>Đạt yêu cầu (60-80)</span>
                            <span class="text-primary">Xuất sắc (80-100)</span>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Secondary Stats -->
            <div class="space-y-4">
                <div class="bg-primary shadow-primary/20 rounded-2xl p-6 text-white shadow-xl">
                    <p class="text-primary-foreground/70 text-xs font-bold uppercase tracking-widest opacity-80">Xếp
                        loại dự kiến</p>
                    <h3 class="mt-1 text-3xl font-black uppercase">{{ $gradeLabel }}</h3>
                    <p class="mt-4 text-sm italic leading-relaxed opacity-90">
                        {{ $finalScore >= 80 ? 'Hiệu suất rất tốt. Hãy tiếp tục giữ vững các chỉ số.' : ($finalScore >= 60 ? 'Hiệu suất ổn định, có thể cải thiện thêm.' : 'Cần ưu tiên cải thiện các chỉ số trọng yếu.') }}
                    </p>
                </div>
                <div
                    class="flex items-center justify-between rounded-2xl border border-slate-100 bg-white p-6 dark:border-slate-700 dark:bg-slate-800">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-widest text-slate-500 dark:text-slate-400">So với
                            trung bình Team</p>
                        <p class="{{ $deltaClass }} mt-1 text-2xl font-bold">{{ $deltaLabel }}</p>
                    </div>
                    <div class="bg-primary/10 text-primary flex size-12 items-center justify-center rounded-xl">
                        <span class="material-symbols-outlined">groups</span>
                    </div>
                </div>
            </div>
        </div>
        <!-- Formula Breakdown Section -->
        <div class="animate-enter grid grid-cols-1 gap-8 lg:grid-cols-2" style="animation-delay: 0.3s">
            <div class="rounded-2xl border border-slate-100 bg-white p-8 dark:border-slate-700 dark:bg-slate-800">
                <div class="mb-8 flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white">Chi tiết chỉ số</h3>
                        <p class="text-sm text-slate-500">Phân rã các chỉ số thành phần cấu thành điểm số</p>
                    </div>
                    <span class="material-symbols-outlined text-4xl text-slate-300 dark:text-slate-600">info</span>
                </div>
                <div class="space-y-6">
                    <!-- Weight 1: On-time -->
                    <div>
                        <div class="mb-2 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="bg-primary size-2 rounded-full"></span>
                                <span class="font-bold text-slate-700 dark:text-slate-300">Đúng hạn (40%)</span>
                            </div>
                            <span
                                class="font-black text-slate-900 dark:text-white">{{ number_format($onTimeRate, 1) }}/100</span>
                        </div>
                        <div class="h-2 w-full rounded-full bg-slate-100 dark:bg-slate-700">
                            <div class="bg-primary h-full rounded-full" style="width: {{ $onTimeRate }}%"></div>
                        </div>
                    </div>
                    <!-- Weight 2: SLA -->
                    <div>
                        <div class="mb-2 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="size-2 rounded-full bg-blue-500"></span>
                                <span class="font-bold text-slate-700 dark:text-slate-300">SLA Xử lý (40%)</span>
                            </div>
                            <span
                                class="font-black text-slate-900 dark:text-white">{{ number_format($slaRate, 1) }}/100</span>
                        </div>
                        <div class="h-2 w-full rounded-full bg-slate-100 dark:bg-slate-700">
                            <div class="h-full rounded-full bg-blue-500" style="width: {{ $slaRate }}%"></div>
                        </div>
                    </div>
                    <!-- Weight 3: Stars -->
                    <div>
                        <div class="mb-2 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="size-2 rounded-full bg-amber-400"></span>
                                <span class="font-bold text-slate-700 dark:text-slate-300">Đánh giá sao (20%)</span>
                            </div>
                            <span
                                class="font-black text-slate-900 dark:text-white">{{ number_format($starScore, 1) }}/100</span>
                        </div>
                        <div class="h-2 w-full rounded-full bg-slate-100 dark:bg-slate-700">
                            <div class="h-full rounded-full bg-amber-400" style="width: {{ $starScore }}%"></div>
                        </div>
                    </div>
                </div>
                <div class="mt-8 border-t border-slate-100 pt-8 dark:border-slate-700">
                    <div class="flex items-center gap-4 rounded-xl bg-slate-50 p-4 dark:bg-slate-900/50">
                        <span class="material-symbols-outlined text-primary">psychology</span>
                        <p class="text-sm text-slate-600 dark:text-slate-400">
                            <strong>Gợi ý:</strong> {{ $hintText }}
                        </p>
                    </div>
                </div>
            </div>
            <!-- Radar Representation Placeholder -->
            <div
                class="flex flex-col items-center justify-center rounded-2xl border border-slate-100 bg-white p-8 text-center dark:border-slate-700 dark:bg-slate-800">
                <h3 class="mb-6 self-start text-lg font-bold text-slate-900 dark:text-white">Biểu đồ chỉ số năng lực
                </h3>
                <div class="relative flex size-64 items-center justify-center">
                    <div class="border-16 absolute inset-0 rounded-full border-slate-100 dark:border-slate-700">
                    </div>
                    <!-- Abstract radar/chart representation using SVG -->
                    <svg class="size-full overflow-visible" viewbox="0 0 100 100">
                        <circle class="text-slate-200 dark:text-slate-700" cx="50" cy="50"
                            fill="none" r="40" stroke="currentColor" stroke-width="0.5"></circle>
                        <circle class="text-slate-200 dark:text-slate-700" cx="50" cy="50"
                            fill="none" r="30" stroke="currentColor" stroke-width="0.5"></circle>
                        <circle class="text-slate-200 dark:text-slate-700" cx="50" cy="50"
                            fill="none" r="20" stroke="currentColor" stroke-width="0.5"></circle>
                        <circle class="text-slate-200 dark:text-slate-700" cx="50" cy="50"
                            fill="none" r="10" stroke="currentColor" stroke-width="0.5"></circle>
                        <path class="text-slate-200 dark:text-slate-700" d="M50 10 L50 90 M10 50 L90 50"
                            stroke="currentColor" stroke-width="0.5"></path>
                        <!-- The Score Shape -->
                        <path d="M50 15 L80 40 L65 80 L25 70 Z" fill="rgba(236, 91, 19, 0.2)" stroke="#ec5b13"
                            stroke-width="2"></path>
                        <!-- Points -->
                        <circle cx="50" cy="15" fill="#ec5b13" r="3"></circle>
                        <circle cx="80" cy="40" fill="#ec5b13" r="3"></circle>
                        <circle cx="65" cy="80" fill="#ec5b13" r="3"></circle>
                        <circle cx="25" cy="70" fill="#ec5b13" r="3"></circle>
                    </svg>
                </div>
                <div class="mt-8 grid w-full max-w-sm grid-cols-2 gap-4">
                    <div class="flex items-center gap-2">
                        <span class="bg-primary size-3 rounded-full"></span>
                        <span class="text-xs font-medium text-slate-600 dark:text-slate-400">Đúng hạn
                            {{ number_format($onTimeRate, 1) }}%</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="bg-primary/60 size-3 rounded-full"></span>
                        <span class="text-xs font-medium text-slate-600 dark:text-slate-400">SLA
                            {{ number_format($slaRate, 1) }}%</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="bg-primary/30 size-3 rounded-full"></span>
                        <span class="text-xs font-medium text-slate-600 dark:text-slate-400">Đánh giá
                            {{ number_format($starScore, 1) }}%</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="bg-primary/10 size-3 rounded-full"></span>
                        <span class="text-xs font-medium text-slate-600 dark:text-slate-400">Điểm tổng
                            {{ number_format($finalScore, 1) }}</span>
                    </div>
                </div>
            </div>
        </div>
        <!-- History Table -->
        <div class="animate-enter overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800"
            style="animation-delay: 0.4s">
            <div
                class="flex flex-col justify-between gap-4 border-b border-slate-100 px-8 py-6 md:flex-row md:items-center dark:border-slate-700">
                <h3 class="text-xl font-bold text-slate-900 dark:text-white">Lịch sử KPI</h3>
                <div class="flex items-center gap-2 overflow-x-auto pb-2 md:w-auto md:overflow-visible md:pb-0">
                    <div class="flex shrink-0 flex-col gap-1">
                        <label class="ml-1 text-[10px] font-bold uppercase tracking-wider text-slate-400">Kỳ báo
                            cáo</label>
                        <div class="flex items-center gap-2">
                            <x-ui.filter-select model="historyPeriodType" :value="$historyPeriodType" icon="calendar_month"
                                :permit-all="false" width="w-36" :options="[
                                    'monthly' => 'Theo tháng',
                                    'quarterly' => 'Theo quý',
                                    'yearly' => 'Theo năm',
                                ]" />

                            <x-ui.filter-select model="historyYear" :value="$historyYear" icon="event"
                                :permit-all="false" width="w-32" :options="$this->historyYearOptions" />
                        </div>
                    </div>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead
                        class="bg-slate-50 text-[10px] font-black uppercase tracking-widest text-slate-500 dark:bg-slate-900/50">
                        <tr>
                            <th class="px-8 py-4">Kỳ báo cáo</th>
                            <th class="px-8 py-4">Điểm thực tế</th>
                            <th class="px-8 py-4">Chỉ tiêu</th>
                            <th class="px-8 py-4">Trạng thái</th>
                            <th class="px-8 py-4">Phê duyệt</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                        @forelse($this->scores as $row)
                            @php
                                $periodLabel =
                                    $row->period_type === 'monthly'
                                        ? 'Tháng ' . $row->period_value . '/' . $row->period_year
                                        : ($row->period_type === 'quarterly'
                                            ? 'Quý ' . $row->period_value . '/' . $row->period_year
                                            : 'Năm ' . $row->period_year);
                                $rowStatus = $row->status ?? 'pending';
                                $rowStatusLabel =
                                    $rowStatus === 'approved'
                                        ? 'Đã duyệt'
                                        : ($rowStatus === 'rejected'
                                            ? 'Từ chối'
                                            : ($rowStatus === 'locked'
                                                ? 'Đã khóa'
                                                : 'Chờ duyệt'));
                                $rowStatusClass =
                                    $rowStatus === 'approved'
                                        ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                        : ($rowStatus === 'rejected'
                                            ? 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400'
                                            : ($rowStatus === 'locked'
                                                ? 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-200'
                                                : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'));
                            @endphp
                            <tr class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-900/30">
                                <td class="px-8 py-4 font-bold text-slate-700 dark:text-slate-300">{{ $periodLabel }}
                                </td>
                                <td class="px-8 py-4">
                                    <span
                                        class="text-lg font-bold text-slate-900 dark:text-white">{{ number_format($row->actual_score ?? $row->final_score, 1) }}</span>
                                </td>
                                <td class="px-8 py-4">
                                    <span
                                        class="text-sm font-bold text-slate-600 dark:text-slate-300">{{ number_format($row->target_score ?? 100, 0) }}</span>
                                </td>
                                <td class="px-8 py-4">
                                    <span
                                        class="{{ $rowStatusClass }} rounded-full px-3 py-1 text-xs font-bold uppercase">{{ $rowStatusLabel }}</span>
                                </td>
                                <td class="px-8 py-4">
                                    <span
                                        class="text-sm text-slate-500">{{ $row->approved_at?->format('d/m/Y') ?? 'Chưa duyệt' }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-8 py-8 text-center text-sm text-slate-500">Chưa có dữ
                                    liệu KPI.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="bg-slate-50 p-6 dark:bg-slate-900/50">
                {{ $this->scores->links() }}
            </div>
        </div>
    </main>
</div>
