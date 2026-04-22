@props([
    'onTimeRate' => 0,
    'slaRate' => 0,
    'starScore' => 0,
    'finalScore' => 0
])

<div {{ $attributes->merge(['class' => 'relative overflow-hidden rounded-2xl border border-slate-100 bg-white p-6 dark:border-slate-700 dark:bg-slate-800']) }}>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h3 class="text-sm font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Công thức tính điểm</h3>
            <p class="text-2xs text-slate-400 dark:text-slate-500">Mã quy tắc: BR-002 (Trọng số thành phần)</p>
        </div>
        <div class="flex size-10 items-center justify-center rounded-full bg-slate-50 text-slate-400 dark:bg-slate-900/50">
            <span class="material-symbols-outlined text-[20px]">calculate</span>
        </div>
    </div>

    <!-- Flow Diagram -->
    <div class="relative flex flex-col items-center gap-6 py-4 lg:flex-row lg:justify-between lg:gap-0">
        <!-- Metric 1: On-time -->
        <div class="group relative z-10 flex w-full flex-col items-center lg:w-1/4">
            <div class="relative mb-2 flex size-14 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600 transition-transform group-hover:scale-110 dark:bg-emerald-900/20 dark:text-emerald-400">
                <span class="material-symbols-outlined text-[28px]">schedule</span>
                <div class="absolute -right-2 -top-2 flex size-6 items-center justify-center rounded-full border-2 border-white bg-emerald-500 text-[10px] font-bold text-white dark:border-slate-800">40%</div>
            </div>
            <p class="text-[10px] font-bold uppercase text-slate-500">Đúng hạn</p>
            <p class="text-lg font-black text-slate-700 dark:text-white">{{ number_format($onTimeRate, 1) }}%</p>
        </div>

        <!-- Connector lg -->
        <div class="hidden h-px w-8 bg-slate-100 lg:block dark:bg-slate-700"></div>

        <!-- Metric 2: SLA -->
        <div class="group relative z-10 flex w-full flex-col items-center lg:w-1/4">
            <div class="relative mb-2 flex size-14 items-center justify-center rounded-2xl bg-blue-50 text-blue-600 transition-transform group-hover:scale-110 dark:bg-blue-900/20 dark:text-blue-400">
                <span class="material-symbols-outlined text-[28px]">verified</span>
                <div class="absolute -right-2 -top-2 flex size-6 items-center justify-center rounded-full border-2 border-white bg-blue-500 text-[10px] font-bold text-white dark:border-slate-800">40%</div>
            </div>
            <p class="text-[10px] font-bold uppercase text-slate-500">Đạt SLA</p>
            <p class="text-lg font-black text-slate-700 dark:text-white">{{ number_format($slaRate, 1) }}%</p>
        </div>

        <!-- Connector lg -->
        <div class="hidden h-px w-8 bg-slate-100 lg:block dark:bg-slate-700"></div>

        <!-- Metric 3: Stars -->
        <div class="group relative z-10 flex w-full flex-col items-center lg:w-1/4">
            <div class="relative mb-2 flex size-14 items-center justify-center rounded-2xl bg-amber-50 text-amber-600 transition-transform group-hover:scale-110 dark:bg-amber-900/20 dark:text-amber-400">
                <span class="material-symbols-outlined text-[28px]">star</span>
                <div class="absolute -right-2 -top-2 flex size-6 items-center justify-center rounded-full border-2 border-white bg-amber-500 text-[10px] font-bold text-white dark:border-slate-800">20%</div>
            </div>
            <p class="text-[10px] font-bold uppercase text-slate-500">Đánh giá sao</p>
            <p class="text-lg font-black text-slate-700 dark:text-white">{{ number_format($starScore, 1) }}%</p>
        </div>

        <!-- Result Arrow for lg -->
        <div class="hidden items-center lg:flex lg:w-1/12">
            <span class="material-symbols-outlined text-slate-300 dark:text-slate-600">arrow_forward</span>
        </div>

        <!-- Final Result Card -->
        <div class="relative z-10 mt-4 flex w-full flex-col items-center rounded-2xl bg-primary/5 p-4 lg:mt-0 lg:w-1/4 dark:bg-primary/10">
            <p class="text-[10px] font-bold uppercase text-primary">Điểm tổng kết</p>
            <p class="text-3xl font-black text-primary">{{ number_format($finalScore, 1) }}</p>
            <div class="mt-1 h-1 w-12 rounded-full bg-primary/20"></div>
        </div>
    </div>

    <!-- Background Decoration -->
    <div class="absolute inset-0 z-0 opacity-5 pointer-events-none">
        <svg class="h-full w-full" viewBox="0 0 100 100" preserveAspectRatio="none">
            <path d="M0,50 Q25,30 50,50 T100,50" fill="none" stroke="currentColor" stroke-width="0.5" class="text-primary" />
            <path d="M0,60 Q25,40 50,60 T100,60" fill="none" stroke="currentColor" stroke-width="0.5" class="text-primary" />
        </svg>
    </div>
</div>
