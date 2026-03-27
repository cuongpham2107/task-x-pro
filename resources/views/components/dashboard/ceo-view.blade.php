<?php
use Livewire\Component;
use App\Enums\TaskStatus;
use App\Enums\TaskPriority;
use App\Models\ActivityLog;
use Illuminate\Support\Str;
use App\Services\Dashboard\DashboardService;

new class extends Component {
    public array $data = [];
    public $activityLogs = [];
    public $selectedMonth;
    public $selectedYear;

    public function mount(array $data)
    {
        $this->data = $data;
        $this->activityLogs = ActivityLog::with('user')->latest()->limit(5)->get();
        $this->selectedMonth = now()->month;
        $this->selectedYear = now()->year;
    }

    public function updatedSelectedMonth()
    {
        $this->updateTopPerformers();
    }

    public function updatedSelectedYear()
    {
        $this->updateTopPerformers();
    }

    public function updateTopPerformers()
    {
        $dashboardService = app(DashboardService::class);
        $this->data['top_performers'] = $dashboardService->topPerformerList(5, $this->selectedMonth, $this->selectedYear);
        $this->dispatch('update-top-performers-chart', data: $this->data['top_performers']);
    }

    public function exportReport()
    {
        // TODO: Implement report export logic
        $this->dispatch('toast', message: 'Tính năng xuất báo cáo CEO đang được phát triển', type: 'info');
    }
};
?>
<div x-data="{ ready: false }" x-init="setTimeout(() => ready = true, 100)">
    <main class="flex flex-1 overflow-hidden">

        <div class="dark:bg-background-dark flex-1 overflow-y-auto p-6 lg:p-8">
            <div class="mb-8 flex flex-col justify-between gap-4 md:flex-row md:items-center" x-show="ready"
                x-transition:enter="transition ease-out duration-500" x-transition:enter-start="opacity-0 translate-y-4"
                x-transition:enter-end="opacity-100 translate-y-0" style="display: none;">
                <div>
                    <x-ui.heading title="Chào, {{ auth()->user()->name }}!"
                        description="Đây là báo cáo hiệu suất tổng quát của hệ thống TaskXPro hôm nay."
                        class="mb-0" />
                </div>
                {{-- <div class="flex gap-3">
                    <x-ui.button
                        variant="outline"
                        size="sm"
                        icon="download"
                        wire:click="exportReport"
                    >
                        Xuất báo cáo
                    </x-ui.button>
                </div> --}}
            </div>
            <div class="mb-8 grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-4" x-show="ready"
                x-transition:enter="transition ease-out duration-500 delay-100"
                x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0"
                style="display: none;">
                <div
                    class="flex flex-col gap-2 rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-start justify-between">
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Tổng dự án đang chạy</p>
                        <span class="rounded-lg bg-blue-100 p-2 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400">
                            <span class="material-symbols-outlined text-[20px]">rocket_launch</span>
                        </span>
                    </div>
                    <h3 class="text-3xl font-bold text-slate-900 dark:text-white">
                        {{ $data['projects']['running'] ?? 0 }}</h3>
                    <p class="flex items-center gap-1 text-sm font-bold text-emerald-600">
                        <span class="material-symbols-outlined text-[16px]">trending_up</span>
                        +{{ $data['projects']['total'] > 0 ? round(($data['projects']['running'] / $data['projects']['total']) * 100, 1) : 0 }}%
                        tỷ lệ
                    </p>
                </div>
                <div
                    class="flex flex-col gap-2 rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-start justify-between">
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Công việc quá hạn</p>
                        <span class="rounded-lg bg-red-100 p-2 text-red-600 dark:bg-red-900/30 dark:text-red-400">
                            <span class="material-symbols-outlined text-[20px]">event_busy</span>
                        </span>
                    </div>
                    <h3 class="text-3xl font-bold text-slate-900 dark:text-white">{{ $data['tasks']['late'] ?? 0 }}</h3>
                    <p class="flex items-center gap-1 text-sm font-bold text-red-600">
                        <span class="material-symbols-outlined text-[16px]">warning</span> Cần xử lý gấp
                    </p>
                </div>
                <div
                    class="flex flex-col gap-2 rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-start justify-between">
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Việc chờ phê duyệt</p>
                        <span
                            class="rounded-lg bg-amber-100 p-2 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400">
                            <span class="material-symbols-outlined text-[20px]">pending_actions</span>
                        </span>
                    </div>
                    <h3 class="text-3xl font-bold text-slate-900 dark:text-white">
                        {{ $data['tasks']['waiting_approval'] ?? 0 }}</h3>
                    <p class="flex items-center gap-1 text-sm font-bold text-emerald-600">
                        <span class="material-symbols-outlined text-[16px]">trending_up</span> Đang chờ duyệt
                    </p>
                </div>
                <div
                    class="flex flex-col gap-2 rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-start justify-between">
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Tiến độ trung bình</p>
                        <span
                            class="rounded-lg bg-emerald-100 p-2 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400">
                            <span class="material-symbols-outlined text-[20px]">verified</span>
                        </span>
                    </div>
                    <h3 class="text-3xl font-bold text-slate-900 dark:text-white">
                        {{ $data['projects']['avg_progress'] ?? 0 }}%</h3>
                    <div class="mt-2 h-1.5 w-full rounded-full bg-slate-100 dark:bg-slate-800">
                        <div class="h-1.5 rounded-full bg-emerald-500"
                            style="width: {{ $data['projects']['avg_progress'] ?? 0 }}%"></div>
                    </div>
                </div>
            </div>
            <div class="mb-8 grid grid-cols-1 gap-8 xl:grid-cols-2" x-show="ready"
                x-transition:enter="transition ease-out duration-500 delay-200"
                x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0"
                style="display: none;">
                <div
                    class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <h4 class="font-bold text-slate-900 dark:text-white">Top Nhân viên xuất sắc</h4>
                        <div class="flex gap-2">
                            <select wire:model.live="selectedMonth"
                                class="focus:border-primary focus:ring-primary rounded-lg border-slate-200 bg-slate-50 text-xs font-bold text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400">
                                @foreach (range(1, 12) as $m)
                                    <option value="{{ $m }}">Tháng {{ $m }}</option>
                                @endforeach
                            </select>
                            <select wire:model.live="selectedYear"
                                class="focus:border-primary focus:ring-primary rounded-lg border-slate-200 bg-slate-50 text-xs font-bold text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400">
                                @foreach (range(now()->year - 2, now()->year) as $y)
                                    <option value="{{ $y }}">Năm {{ $y }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="h-62.5 relative w-full">
                        <canvas id="topPerformersChart" wire:ignore></canvas>
                    </div>
                </div>
                <div
                    class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h4 class="mb-6 font-bold text-slate-900 dark:text-white">Tiến trình Phase tổng hợp</h4>
                    <div class="flex h-full flex-col items-center justify-between gap-8 py-4 md:flex-row">
                        <div class="h-62.5 relative flex w-full items-center justify-center">
                            <canvas id="phaseProgressChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="grid grid-cols-1 gap-8 lg:grid-cols-4" x-show="ready"
                x-transition:enter="transition ease-out duration-500 delay-300"
                x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0"
                style="display: none;">
                <div
                    class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm lg:col-span-3 dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-center justify-between border-b border-slate-100 p-6 dark:border-slate-800">
                        <h4 class="font-bold text-slate-900 dark:text-white">Danh sách cần duyệt (2 cấp)</h4>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse text-left">
                            <thead>
                                <tr
                                    class="bg-slate-50/50 text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:bg-slate-800/50">
                                    <th class="px-6 py-4">Tên công việc</th>
                                    <th class="px-6 py-4">Dự án</th>
                                    <th class="px-6 py-4">Người thực hiện</th>
                                    <th class="px-6 py-4">Hạn chót</th>
                                    <th class="px-6 py-4">Tiến độ</th>
                                    <th class="px-6 py-4">Trạng thái</th>
                                    <th class="px-6 py-4">Cập nhập lúc</th>
                                    <th class="px-6 py-4 text-right">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                @forelse($data['approval_tasks'] as $task)
                                    @php
                                        $priorityEnum = \App\Enums\TaskPriority::tryFrom(
                                            $task->priority->value ?? ($task->priority ?? ''),
                                        );
                                        $priorityColor = match ($priorityEnum?->value ?? '') {
                                            'urgent' => 'red',
                                            'high' => 'orange',
                                            'medium' => 'amber',
                                            default => 'blue',
                                        };
                                        $statusEnum = \App\Enums\TaskStatus::tryFrom(
                                            $task->status->value ?? ($task->status ?? ''),
                                        );
                                    @endphp
                                    <tr class="transition-colors hover:bg-slate-50/50 dark:hover:bg-slate-800/50">
                                        <td class="px-6 py-4">
                                            <div class="flex flex-col gap-1">
                                                <button
                                                    wire:click="$dispatch('task-edit-requested', { taskId: {{ $task->id }} })"
                                                    class="hover:text-primary max-w-[200px] truncate text-left text-sm font-bold text-slate-900 transition-colors dark:text-slate-100">
                                                    {{ $task->name }}
                                                </button>
                                                <div class="flex items-center gap-2">
                                                    <x-ui.badge :color="$priorityColor" size="2xs">
                                                        {{ $priorityEnum?->label() ?? '—' }}
                                                    </x-ui.badge>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span
                                                class="text-xs font-medium text-slate-600 dark:text-slate-400">{{ $task->phase?->project?->name ?? 'N/A' }}</span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-3">
                                                @php
                                                    $taskUsers = collect();
                                                    if ($task->pic) {
                                                        $taskUsers->push($task->pic);
                                                    }
                                                    if ($task->coPics) {
                                                        $taskUsers = $taskUsers->concat($task->coPics);
                                                    }
                                                @endphp
                                                <x-ui.avatar-stack :users="$taskUsers" size="8" />
                                                {{-- <span
                                                    class="text-xs font-medium text-slate-700 dark:text-slate-300">{{ $task->pic?->name ?? 'N/A' }}</span> --}}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex flex-col">
                                                <span
                                                    class="{{ $task->deadline && \Carbon\Carbon::parse($task->deadline)->isPast() ? 'text-rose-500' : 'text-slate-600 dark:text-slate-400' }} text-xs font-medium">
                                                    {{ $task->deadline ? \Carbon\Carbon::parse($task->deadline)->format('d/m/Y') : 'N/A' }}
                                                </span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="w-24">
                                                <div
                                                    class="mb-1 flex items-center justify-between text-[10px] font-bold text-slate-500">
                                                    <span>{{ $task->progress }}%</span>
                                                </div>
                                                <div
                                                    class="h-1.5 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                                    <div class="{{ $task->progress >= 100 ? 'bg-emerald-500' : 'bg-primary' }} h-full transition-all duration-500"
                                                        style="width: {{ $task->progress }}%"></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span
                                                class="{{ $statusEnum?->badgeClass() ?? 'bg-slate-100 text-slate-600' }} rounded px-2 py-0.5 text-[10px] font-bold uppercase">
                                                {{ $statusEnum?->label() ?? $task->status }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-[10px] font-bold text-slate-500">
                                                {{ $task->updated_at->diffForHumans() }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <button
                                                wire:click="$dispatch('task-edit-requested', { taskId: {{ $task->id }} })"
                                                class="hover:border-primary hover:text-primary ml-auto flex size-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-400 transition-all dark:border-slate-800 dark:bg-slate-900">
                                                <span class="material-symbols-outlined text-[18px]">visibility</span>
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="px-6 py-12 text-center text-slate-500">
                                            Không có yêu cầu duyệt nào.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div
                    class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="border-b border-slate-100 p-6 dark:border-slate-800">
                        <h4 class="font-bold text-slate-900 dark:text-white">Hoạt động gần đây</h4>
                    </div>
                    <div class="space-y-6 p-6">
                        @forelse($activityLogs as $log)
                            <div
                                class="before:left-2.75 relative flex gap-4 before:absolute before:bottom-0 before:top-8 before:w-0.5 before:bg-slate-100 last:before:hidden dark:before:bg-slate-800">
                                <div
                                    class="bg-primary z-10 flex size-6 shrink-0 items-center justify-center overflow-hidden rounded-full border-4 border-white dark:border-slate-900">
                                    @if ($log->user && $log->user->avatar_url)
                                        <img src="{{ $log->user->avatar_url }}" class="h-full w-full object-cover"
                                            alt="{{ $log->user->name }}">
                                    @else
                                        <span
                                            class="text-[8px] font-bold text-white">{{ $log->user ? substr($log->user->name, 0, 1) : 'S' }}</span>
                                    @endif
                                </div>
                                <div class="flex flex-col gap-1">
                                    <p class="text-xs leading-relaxed">
                                        <span class="font-bold">{{ $log->user->name ?? 'System' }}</span>
                                        {{ $log->description ?? 'đã thực hiện một hành động' }}
                                    </p>
                                    <span
                                        class="text-2xs text-slate-400">{{ $log->created_at->diffForHumans() }}</span>
                                </div>
                            </div>
                        @empty
                            <div class="text-center text-sm text-slate-500">Chưa có hoạt động nào.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </main>
    <livewire:task.form />

    {{-- expose initial data to global scope; chart logic lives in resources/js/dashboard-ceo.js --}}
    <script>
        window.__dashboardTopPerformersData = @json(collect($data['top_performers'])->take(5));
        window.__dashboardPhaseTotal = {{ $data['phases']['total'] ?? 0 }};
        window.__dashboardPhaseActive = {{ $data['phases']['active'] ?? 0 }};
        window.__dashboardPhaseCompleted = {{ $data['phases']['completed'] ?? 0 }};
    </script>
</div>
