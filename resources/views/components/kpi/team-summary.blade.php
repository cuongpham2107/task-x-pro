@props([
    'avgScore' => 0,
    'totalTasks' => 0,
    'slaRate' => 0,
    'onTimeRate' => 0,
    'approvalRate' => 0,
    'trend' => 'neutral',
    'trendLabel' => '',
    'title' => 'Tổng quan hiệu suất Team'
])

<div {{ $attributes->merge(['class' => 'grid grid-cols-2 gap-4 md:grid-cols-5']) }}>
    <!-- Avg Score -->
    <div class="relative overflow-hidden rounded-2xl border border-primary/10 bg-white p-5 shadow-sm dark:border-primary/20 dark:bg-slate-800">
        <div class="absolute -right-4 -top-4 size-16 rounded-full bg-primary/5 dark:bg-primary/10"></div>
        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Điểm trung bình</p>
        <div class="mt-2 flex items-baseline gap-2">
            <h3 class="text-3xl font-black text-primary">{{ number_format($avgScore, 1) }}</h3>
            @if($trend !== 'neutral')
                <div class="flex items-center gap-0.5 {{ $trend === 'up' ? 'text-emerald-500' : 'text-rose-500' }}">
                    <span class="material-symbols-outlined text-[16px]">{{ $trend === 'up' ? 'trending_up' : 'trending_down' }}</span>
                    <span class="text-[10px] font-bold">{{ $trendLabel }}</span>
                </div>
            @endif
        </div>
        <div class="mt-4 h-1 w-full rounded-full bg-slate-100 dark:bg-slate-700">
            <div class="bg-primary h-full rounded-full" style="width: {{ min($avgScore, 100) }}%"></div>
        </div>
    </div>

    <!-- Total Tasks -->
    <div class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Tổng công việc</p>
        <h3 class="mt-2 text-3xl font-black text-slate-700 dark:text-white">{{ number_format($totalTasks) }}</h3>
        <p class="mt-1 text-[10px] text-slate-400">Đã hoàn thành trong kỳ</p>
    </div>

    <!-- SLA Rate -->
    <div class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
        <div class="flex items-center justify-between">
            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Tỷ lệ SLA</p>
            <span class="material-symbols-outlined text-[18px] text-blue-500">verified</span>
        </div>
        <h3 class="mt-2 text-3xl font-black text-blue-600 dark:text-blue-400">{{ number_format($slaRate, 1) }}%</h3>
        <div class="mt-3 flex items-center gap-1.5">
            <div class="h-1.5 flex-1 rounded-full bg-slate-100 dark:bg-slate-700">
                <div class="h-full rounded-full bg-blue-500" style="width: {{ $slaRate }}%"></div>
            </div>
        </div>
    </div>

    <!-- On-time Rate -->
    <div class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
        <div class="flex items-center justify-between">
            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Tỷ lệ Đúng hạn</p>
            <span class="material-symbols-outlined text-[18px] text-emerald-500">schedule</span>
        </div>
        <h3 class="mt-2 text-3xl font-black text-emerald-600 dark:text-emerald-400">{{ number_format($onTimeRate, 1) }}%</h3>
        <div class="mt-3 flex items-center gap-1.5">
            <div class="h-1.5 flex-1 rounded-full bg-slate-100 dark:bg-slate-700">
                <div class="h-full rounded-full bg-emerald-500" style="width: {{ $onTimeRate }}%"></div>
            </div>
        </div>
    </div>

    <!-- Approval Progress -->
    <div class="col-span-2 rounded-2xl border border-slate-100 bg-white p-5 shadow-sm md:col-span-1 dark:border-slate-700 dark:bg-slate-800">
        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Tiến độ phê duyệt</p>
        <div class="mt-2 flex items-center justify-between">
            <h3 class="text-3xl font-black text-slate-700 dark:text-white">{{ number_format($approvalRate, 1) }}%</h3>
            <div class="flex size-10 items-center justify-center rounded-xl bg-amber-50 text-amber-500 dark:bg-amber-900/20">
                <span class="material-symbols-outlined text-[24px]">task_alt</span>
            </div>
        </div>
        <p class="mt-1 text-[10px] text-slate-400">{{ $approvalRate >= 100 ? 'Hoàn tất phê duyệt tháng' : 'Cần kiểm tra dữ liệu' }}</p>
    </div>
</div>
