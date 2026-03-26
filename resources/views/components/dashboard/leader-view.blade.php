<?php
use App\Models\Task;
use App\Enums\TaskStatus;
use App\Enums\TaskPriority;
use Livewire\Component;

new class extends Component {
    public array $data = [];

    public $activityLogs = [];

    public array $weeklyStats = [];

    public function mount(array $data): void
    {
        $this->data = $data;
        $this->activityLogs = \App\Models\ActivityLog::with('user')->latest()->limit(5)->get();

        // Tính toán số task hoàn thành và tạo mới trong 7 ngày qua
        $this->weeklyStats = collect(range(6, 0))
            ->map(function (int $daysAgo): array {
                $date = now()->subDays($daysAgo);

                return [
                    'label' => $date->isoFormat('dd'),
                    'completed' => \App\Models\Task::whereDate('updated_at', $date)->where('status', \App\Enums\TaskStatus::Completed->value)->count(),
                    'created' => \App\Models\Task::whereDate('created_at', $date)->count(),
                ];
            })
            ->all();
    }

    public function filterDate()
    {
        // TODO: Implement date filtering logic
        $this->dispatch('toast', message: 'Tính năng lọc 7 ngày qua đang được phát triển', type: 'info');
    }

    public function exportReport()
    {
        // TODO: Implement report export logic
        $this->dispatch('toast', message: 'Tính năng xuất báo cáo đang được phát triển', type: 'info');
    }
};
?>

<div x-data="{ ready: false }" x-init="setTimeout(() => ready = true, 100)">
    <main class="flex flex-1 overflow-hidden">
        <!-- Content Area -->
        <div class="dark:bg-background-dark flex-1 overflow-y-auto bg-slate-50 p-6 lg:p-8">
            <div class="mx-auto space-y-8" x-show="ready" x-transition:enter="transition ease-out duration-500"
                x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0"
                style="display: none;">

                <!-- Page Header -->
                <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                    <div>
                        <x-ui.heading title="Chào, {{ auth()->user()->name }}!"
                            description="Theo dõi thời gian thực các chỉ số vận hành và quy trình phê duyệt hôm nay."
                            class="mb-0" />
                    </div>
                </div>

                <!-- Top Stats Cards -->
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-4">
                    <div
                        class="flex flex-col gap-2 rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition-all hover:shadow-md dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex items-start justify-between">
                            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Tổng dự án</p>
                            <span
                                class="rounded-lg bg-blue-100 p-2 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400">
                                <span class="material-symbols-outlined text-[20px]">folder_shared</span>
                            </span>
                        </div>
                        <h3 class="text-3xl font-bold text-slate-900 dark:text-white">
                            {{ $data['projects']['total'] ?? 0 }}</h3>
                        <p class="flex items-center gap-1 text-sm font-bold text-emerald-600">
                            <span class="material-symbols-outlined text-[16px]">trending_up</span>
                            Dữ liệu hệ thống
                        </p>
                    </div>

                    <div
                        class="flex flex-col gap-2 rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition-all hover:shadow-md dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex items-start justify-between">
                            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Đang thực hiện</p>
                            <span
                                class="rounded-lg bg-indigo-100 p-2 text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400">
                                <span class="material-symbols-outlined text-[20px]">pending</span>
                            </span>
                        </div>
                        <h3 class="text-3xl font-bold text-slate-900 dark:text-white">
                            {{ $data['tasks']['in_progress'] ?? 0 }}</h3>
                        <p class="flex items-center gap-1 text-sm font-bold text-indigo-600">
                            <span class="material-symbols-outlined text-[16px]">sync</span>
                            Tiến độ thực tế
                        </p>
                    </div>

                    <div
                        class="flex flex-col gap-2 rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition-all hover:shadow-md dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex items-start justify-between">
                            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Chờ phê duyệt</p>
                            <span
                                class="rounded-lg bg-amber-100 p-2 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400">
                                <span class="material-symbols-outlined text-[20px]">fact_check</span>
                            </span>
                        </div>
                        <h3 class="text-3xl font-bold text-slate-900 dark:text-white">
                            {{ $data['tasks']['waiting_approval'] ?? 0 }}</h3>
                        <p class="flex items-center gap-1 text-sm font-bold text-amber-600">
                            <span class="material-symbols-outlined text-[16px]">pending_actions</span>
                            Cần xử lý ngay
                        </p>
                    </div>

                    <div
                        class="flex flex-col gap-2 rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition-all hover:shadow-md dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex items-start justify-between">
                            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Đã quá hạn</p>
                            <span
                                class="rounded-lg bg-rose-100 p-2 text-rose-600 dark:bg-rose-900/30 dark:text-rose-400">
                                <span class="material-symbols-outlined text-[20px]">warning</span>
                            </span>
                        </div>
                        <h3 class="text-3xl font-bold text-slate-900 dark:text-white">{{ $data['tasks']['late'] ?? 0 }}
                        </h3>
                        <p class="flex items-center gap-1 text-sm font-bold text-rose-600">
                            <span class="material-symbols-outlined text-[16px]">error</span>
                            Cảnh báo rủi ro
                        </p>
                    </div>
                </div>

                <!-- Main Grid: Chart & Activity -->
                <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
                    <!-- Progress Chart -->
                    <div
                        class="flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-2 dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex items-start justify-between">
                            <div>
                                <h3 class="text-lg font-bold text-slate-900 dark:text-white">Hiệu suất vận hành Dự án
                                </h3>
                                <p class="text-sm text-slate-500">Thực tế vs Kế hoạch (Dữ liệu 7 ngày gần nhất)</p>
                            </div>
                            <div class="flex items-center gap-4 text-xs font-semibold">
                                <div class="flex items-center gap-1.5"><span
                                        class="bg-primary size-2.5 rounded-full"></span><span>Thực tế</span></div>
                                <div class="flex items-center gap-1.5"><span
                                        class="size-2.5 rounded-full bg-slate-200 dark:bg-slate-700"></span><span>Kế
                                        hoạch</span></div>
                            </div>
                        </div>
                        <div class="relative mt-4 h-[280px] flex-1">
                            <canvas id="leaderActivityChart"></canvas>
                        </div>
                    </div>

                    <!-- Side Column: Activity & Chart -->
                    <div class="flex flex-col gap-8">
                        <!-- Project Status Doughnut -->
                        <div
                            class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                            <h4 class="mb-4 font-bold text-slate-900 dark:text-white">Trạng thái Dự án</h4>
                            <div class="relative h-48">
                                <canvas id="projectStatusChart"></canvas>
                            </div>
                            <div class="mt-4 space-y-2 border-t border-slate-100 pt-4 dark:border-slate-800">
                                <div class="flex items-center justify-between text-xs">
                                    <div class="flex items-center gap-2">
                                        <span class="size-2.5 rounded-full bg-emerald-500"></span>
                                        <span class="text-slate-500">Hoàn thành</span>
                                    </div>
                                    <span
                                        class="font-bold text-slate-900 dark:text-white">{{ $data['projects']['completed'] ?? 0 }}</span>
                                </div>
                                <div class="flex items-center justify-between text-xs">
                                    <div class="flex items-center gap-2">
                                        <span class="size-2.5 rounded-full bg-blue-500"></span>
                                        <span class="text-slate-500">Đang chạy</span>
                                    </div>
                                    <span
                                        class="font-bold text-slate-900 dark:text-white">{{ $data['projects']['running'] ?? 0 }}</span>
                                </div>
                                <div class="flex items-center justify-between text-xs">
                                    <div class="flex items-center gap-2">
                                        <span class="size-2.5 rounded-full bg-amber-500"></span>
                                        <span class="text-slate-500">Tạm dừng</span>
                                    </div>
                                    <span
                                        class="font-bold text-slate-900 dark:text-white">{{ $data['projects']['paused'] ?? 0 }}</span>
                                </div>
                                <div
                                    class="flex items-center justify-between border-t border-slate-100 pt-2 text-xs dark:border-slate-800">
                                    <span class="font-semibold text-slate-500">Tổng</span>
                                    <span
                                        class="font-bold text-slate-900 dark:text-white">{{ $data['projects']['total'] ?? 0 }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Activity Log -->
                        <div
                            class="flex-1 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                            <div class="border-b border-slate-100 p-6 dark:border-slate-800">
                                <h4 class="font-bold text-slate-900 dark:text-white">Hoạt động gần đây</h4>
                            </div>
                            <div class="max-h-[300px] space-y-6 overflow-y-auto p-6">
                                @forelse($activityLogs as $log)
                                    <div
                                        class="before:left-2.75 relative flex gap-4 border-b border-slate-50 pb-4 before:absolute before:bottom-0 before:top-8 before:w-0.5 before:bg-slate-100 last:border-0 last:pb-0 last:before:hidden dark:border-slate-800 dark:before:bg-slate-700">
                                        <x-ui.avatar-stack :users="collect([$log->user])" size="6"
                                            class="z-10 border-4 border-white dark:border-slate-900" />
                                        <div class="flex flex-col gap-1">
                                            <p class="text-xs leading-relaxed dark:text-slate-300">
                                                <span
                                                    class="font-bold text-slate-900 dark:text-white">{{ $log->user->name ?? 'System' }}</span>
                                                {{ $log->description ?? ($log->action === 'status_updated' ? 'đã cập nhật trạng thái công việc' : 'đã thực hiện một hành động') }}
                                            </p>
                                            <span
                                                class="text-[10px] text-slate-400">{{ $log->created_at->diffForHumans() }}</span>
                                        </div>
                                    </div>
                                @empty
                                    <div class="py-4 text-center text-sm text-slate-500">Chưa có hoạt động nào.</div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Approval Queue Table Card -->
                <div
                    class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-center justify-between border-b border-slate-100 p-6 dark:border-slate-800">
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-amber-500">pending_actions</span>
                            <h2 class="text-lg font-bold text-slate-900 dark:text-white">Công việc chờ duyệt</h2>
                        </div>
                        <div class="flex items-center gap-2">
                            <span
                                class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-bold text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">
                                {{ count($data['approval_tasks'] ?? []) }} tasks
                            </span>
                        </div>
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
                                        $priorityEnum =
                                            $task->priority instanceof App\Enums\TaskPriority
                                                ? $task->priority
                                                : \App\Enums\TaskPriority::tryFrom($task->priority ?? '');
                                        $priorityColor = match ($priorityEnum?->value ?? '') {
                                            'urgent' => 'red',
                                            'high' => 'orange',
                                            'medium' => 'amber',
                                            default => 'blue',
                                        };
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
                                                    @if ($task->comments_count > 0)
                                                        <span
                                                            class="flex items-center gap-1 text-[10px] text-slate-400">
                                                            <span
                                                                class="material-symbols-outlined text-[14px]">chat_bubble</span>
                                                            {{ $task->comments_count }}
                                                        </span>
                                                    @endif
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
                                                <span
                                                    class="text-xs font-medium text-slate-700 dark:text-slate-300">{{ $task->pic?->name ?? 'N/A' }}</span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex flex-col">
                                                <span
                                                    class="{{ $task->deadline && \Carbon\Carbon::parse($task->deadline)->isPast() ? 'text-rose-500' : 'text-slate-600 dark:text-slate-400' }} text-xs font-medium">
                                                    {{ $task->deadline ? \Carbon\Carbon::parse($task->deadline)->format('d/m/Y') : 'N/A' }}
                                                </span>
                                                @if ($task->deadline && \Carbon\Carbon::parse($task->deadline)->isPast())
                                                    <span class="text-[10px] font-bold uppercase text-rose-500">Quá
                                                        hạn</span>
                                                @endif
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
                                            @php
                                                $statusEnum =
                                                    $task->status instanceof TaskStatus
                                                        ? $task->status
                                                        : TaskStatus::tryFrom($task->status ?? '');
                                            @endphp
                                            <span
                                                class="{{ $statusEnum?->badgeClass() ?? 'bg-slate-100 text-slate-600' }} rounded px-2 py-0.5 text-[10px] font-bold uppercase">
                                                {{ $statusEnum?->label() ?? $task->status }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div
                                                class="mb-1 flex items-center justify-between text-[10px] font-bold text-slate-500">
                                                <span> {{ $task->updated_at->diffForHumans() }}</span>
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
                                        <td colspan="8" class="px-6 py-12 text-center">
                                            <div class="flex flex-col items-center gap-2">
                                                <span
                                                    class="material-symbols-outlined text-4xl text-slate-200 dark:text-slate-800">check_circle</span>
                                                <p class="text-sm font-medium tracking-wide text-slate-500">Tất cả công
                                                    việc đã được phê duyệt!</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <livewire:task.form />

    <script>
        document.addEventListener('livewire:navigated', () => {
            // Line chart: Task hoàn thành vs tạo mới 7 ngày qua
            const lineCtx = document.getElementById('leaderActivityChart');
            if (lineCtx) {
                const isDark = document.documentElement.classList.contains('dark');
                const gridColor = isDark ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)';
                const labelColor = isDark ? '#94a3b8' : '#64748b';

                new Chart(lineCtx, {
                    type: 'line',
                    data: {
                        labels: @json(array_column($weeklyStats, 'label')),
                        datasets: [{
                                label: 'Hoàn thành',
                                data: @json(array_column($weeklyStats, 'completed')),
                                borderColor: '#3b82f6',
                                backgroundColor: 'rgba(59,130,246,0.08)',
                                borderWidth: 2.5,
                                tension: 0.4,
                                fill: true,
                                pointRadius: 4,
                                pointHoverRadius: 6,
                                pointBackgroundColor: '#3b82f6',
                            },
                            {
                                label: 'Tạo mới',
                                data: @json(array_column($weeklyStats, 'created')),
                                borderColor: '#e2e8f0',
                                backgroundColor: 'transparent',
                                borderWidth: 2,
                                tension: 0.4,
                                fill: false,
                                pointRadius: 3,
                                pointHoverRadius: 5,
                                pointBackgroundColor: '#e2e8f0',
                                borderDash: [5, 4],
                            },
                        ],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            mode: 'index',
                            intersect: false
                        },
                        plugins: {
                            legend: {
                                display: false,
                            },
                            tooltip: {
                                backgroundColor: isDark ? '#1e293b' : '#fff',
                                borderColor: isDark ? '#334155' : '#e2e8f0',
                                borderWidth: 1,
                                titleColor: labelColor,
                                bodyColor: labelColor,
                                padding: 10,
                            },
                        },
                        scales: {
                            x: {
                                grid: {
                                    color: gridColor
                                },
                                ticks: {
                                    color: labelColor,
                                    font: {
                                        size: 11,
                                        weight: '600'
                                    }
                                },
                            },
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: gridColor
                                },
                                ticks: {
                                    color: labelColor,
                                    font: {
                                        size: 11
                                    },
                                    precision: 0
                                },
                            },
                        },
                    },
                });
            }

            // Doughnut chart: Trạng thái dự án
            const ctx = document.getElementById('projectStatusChart');
            if (ctx) {
                new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Hoàn thành', 'Đang chạy', 'Tạm dừng'],
                        datasets: [{
                            data: [
                                {{ $data['projects']['completed'] ?? 0 }},
                                {{ $data['projects']['running'] ?? 0 }},
                                {{ $data['projects']['paused'] ?? 0 }}
                            ],
                            backgroundColor: ['#10b981', '#3b82f6', '#f59e0b'],
                            borderWidth: 0,
                            hoverOffset: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '70%',
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    usePointStyle: true,
                                    padding: 15,
                                    font: {
                                        size: 10,
                                        weight: '600'
                                    },
                                    color: document.documentElement.classList.contains('dark') ? '#94a3b8' :
                                        '#64748b'
                                }
                            }
                        }
                    }
                });
            }
        });
    </script>
</div>
