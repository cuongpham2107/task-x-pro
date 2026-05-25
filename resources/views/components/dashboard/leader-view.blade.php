<?php
use App\Models\ActivityLog;
use App\Models\Task;
use App\Services\Dashboard\DashboardService;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public array $data = [];

    public array $teamData = [];

    public $activityLogs = [];

    public array $weeklyStats = [];

    public function mount(array $data): void
    {
        $this->data = $data;
        $this->loadTeamData();
        $this->loadWeeklyStats();
    }

    public function loadTeamData(): void
    {
        $dashboardService = app(DashboardService::class);
        $this->teamData = $dashboardService->getLeaderTeamData(auth()->user());
    }

    public function loadWeeklyStats(): void
    {
        $this->activityLogs = ActivityLog::with('user')->latest()->limit(5)->get();

        $this->weeklyStats = collect(range(6, 0))
            ->map(function (int $daysAgo): array {
                $date = now()->subDays($daysAgo);

                return [
                    'label' => $date->isoFormat('dd'),
                    'completed' => Task::whereDate('updated_at', $date)->where('status', \App\Enums\TaskStatus::Completed->value)->count(),
                    'created' => Task::whereDate('created_at', $date)->count(),
                ];
            })
            ->all();
    }

    #[On('task-updated')]
    #[On('task-saved')]
    public function refreshData(): void
    {
        $dashboardService = app(DashboardService::class);
        $this->data = $dashboardService->getIndexData(auth()->user());
        $this->loadTeamData();
        $this->loadWeeklyStats();

        $this->dispatch('charts-updated', [
            'weeklyStats' => $this->weeklyStats,
            'projects' => $this->data['projects'],
        ]);
    }
};
?>

<div x-data="{ ready: false }" x-init="setTimeout(() => ready = true, 100)">
    <main class="flex flex-1 overflow-hidden">
        <!-- Content Area -->
        <div class="dark:bg-background-dark flex-1 overflow-y-auto p-6 lg:p-8">
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
                        <h3 class="text-3xl font-bold text-slate-600 dark:text-white">
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
                        <h3 class="text-3xl font-bold text-slate-600 dark:text-white">
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
                        <h3 class="text-3xl font-bold text-slate-600 dark:text-white">
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
                        <h3 class="text-3xl font-bold text-slate-600 dark:text-white">{{ $data['tasks']['late'] ?? 0 }}
                        </h3>
                        <p class="flex items-center gap-1 text-sm font-bold text-rose-600">
                            <span class="material-symbols-outlined text-[16px]">error</span>
                            Cảnh báo rủi ro
                        </p>
                    </div>
                </div>

                <!-- Team Stats Cards Row 2 -->
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-4">
                    <div
                        class="flex flex-col gap-2 rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition-all hover:shadow-md dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex items-start justify-between">
                            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Task trong team</p>
                            <span
                                class="rounded-lg bg-violet-100 p-2 text-violet-600 dark:bg-violet-900/30 dark:text-violet-400">
                                <span class="material-symbols-outlined text-[20px]">group_work</span>
                            </span>
                        </div>
                        <h3 class="text-3xl font-bold text-slate-600 dark:text-white">
                            {{ $teamData['team_tasks_total'] ?? 0 }}</h3>
                        <p class="flex items-center gap-1 text-sm font-bold text-violet-600">
                            <span class="material-symbols-outlined text-[16px]">assignment</span>
                            Tổng số công việc
                        </p>
                    </div>

                    <div
                        class="flex flex-col gap-2 rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition-all hover:shadow-md dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex items-start justify-between">
                            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Sắp đến hạn &le;3 ngày</p>
                            <span
                                class="rounded-lg bg-amber-100 p-2 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400">
                                <span class="material-symbols-outlined text-[20px]">event_upcoming</span>
                            </span>
                        </div>
                        <h3 class="text-3xl font-bold text-slate-600 dark:text-white">
                            {{ $teamData['team_tasks_due_soon'] ?? 0 }}</h3>
                        <p class="flex items-center gap-1 text-sm font-bold text-amber-600">
                            <span class="material-symbols-outlined text-[16px]">schedule</span>
                            Cần theo dõi sát
                        </p>
                    </div>

                    <div
                        class="flex flex-col gap-2 rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition-all hover:shadow-md dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex items-start justify-between">
                            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Quá hạn</p>
                            <span
                                class="rounded-lg bg-rose-100 p-2 text-rose-600 dark:bg-rose-900/30 dark:text-rose-400">
                                <span class="material-symbols-outlined text-[20px]">error_outline</span>
                            </span>
                        </div>
                        <h3 class="text-3xl font-bold text-slate-600 dark:text-white">
                            {{ $teamData['team_tasks_overdue'] ?? 0 }}</h3>
                        <p class="flex items-center gap-1 text-sm font-bold text-rose-600">
                            <span class="material-symbols-outlined text-[16px]">warning</span>
                            Cảnh báo rủi ro
                        </p>
                    </div>

                    <div
                        class="flex flex-col gap-2 rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition-all hover:shadow-md dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex items-start justify-between">
                            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Overload PIC</p>
                            <span
                                class="rounded-lg bg-orange-100 p-2 text-orange-600 dark:bg-orange-900/30 dark:text-orange-400">
                                <span class="material-symbols-outlined text-[20px]">priority_high</span>
                            </span>
                        </div>
                        <h3 class="text-3xl font-bold text-slate-600 dark:text-white">
                            {{ $teamData['team_overloaded_pic_count'] ?? 0 }}</h3>
                        <p class="flex items-center gap-1 text-sm font-bold text-orange-600">
                            <span class="material-symbols-outlined text-[16px]">people</span>
                            Nhân sự quá tải
                        </p>
                    </div>
                </div>

                <!-- Overload Alert -->
                @if (($teamData['team_overloaded_pic_count'] ?? 0) > 0)
                    <div
                        class="flex items-center gap-3 rounded-xl border border-orange-200 bg-orange-50 p-4 text-sm font-semibold text-orange-700 dark:border-orange-900/30 dark:bg-orange-900/20 dark:text-orange-300">
                        <span class="material-symbols-outlined text-2xl">warning_amber</span>
                        <span>Có <strong>{{ $teamData['team_overloaded_pic_count'] }}</strong> nhân sự đang bị quá tải công
                            việc (trên 3 task quá hạn). Vui lòng xem xét điều phối lại.</span>
                    </div>
                @endif

                <!-- Main Grid: Chart & Activity -->
                <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
                    <!-- Progress Chart -->
                    <div
                        class="flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-2 dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex items-start justify-between">
                            <div>
                                <h3 class="text-lg font-bold text-slate-600 dark:text-white">Hiệu suất vận hành Dự án
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
                        <div class="relative mt-4 h-70 flex-1">
                            <canvas id="leaderActivityChart"></canvas>
                        </div>
                    </div>

                    <!-- Side Column: Activity & Chart -->
                    <div class="flex flex-col gap-8">
                        <!-- Project Status Doughnut -->
                        <div
                            class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                            <h4 class="mb-4 font-bold text-slate-600 dark:text-white">Trạng thái Dự án</h4>
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
                                        class="font-bold text-slate-600 dark:text-white">{{ $data['projects']['completed'] ?? 0 }}</span>
                                </div>
                                <div class="flex items-center justify-between text-xs">
                                    <div class="flex items-center gap-2">
                                        <span class="size-2.5 rounded-full bg-blue-500"></span>
                                        <span class="text-slate-500">Đang chạy</span>
                                    </div>
                                    <span
                                        class="font-bold text-slate-600 dark:text-white">{{ $data['projects']['running'] ?? 0 }}</span>
                                </div>
                                <div class="flex items-center justify-between text-xs">
                                    <div class="flex items-center gap-2">
                                        <span class="size-2.5 rounded-full bg-amber-500"></span>
                                        <span class="text-slate-500">Tạm dừng</span>
                                    </div>
                                    <span
                                        class="font-bold text-slate-600 dark:text-white">{{ $data['projects']['paused'] ?? 0 }}</span>
                                </div>
                                <div
                                    class="flex items-center justify-between border-t border-slate-100 pt-2 text-xs dark:border-slate-800">
                                    <span class="font-semibold text-slate-500">Tổng</span>
                                    <span
                                        class="font-bold text-slate-600 dark:text-white">{{ $data['projects']['total'] ?? 0 }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Activity Log -->
                        <div
                            class="flex-1 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                            <div class="border-b border-slate-100 p-6 dark:border-slate-800">
                                <h4 class="font-bold text-slate-600 dark:text-white">Hoạt động gần đây</h4>
                            </div>
                            <div class="max-h-75 space-y-6 overflow-y-auto p-6">
                                @forelse($activityLogs as $log)
                                    <div
                                        class="before:left-2.75 relative flex gap-4 border-b border-slate-50 pb-4 before:absolute before:bottom-0 before:top-8 before:w-0.5 before:bg-slate-100 last:border-0 last:pb-0 last:before:hidden dark:border-slate-800 dark:before:bg-slate-700">
                                        <x-ui.avatar-stack :users="collect([$log->user])" size="6"
                                            class="z-10 border-4 border-white dark:border-slate-900" />
                                        <div class="flex flex-col gap-1">
                                            <p class="text-xs leading-relaxed dark:text-slate-300">
                                                <span
                                                    class="font-bold text-slate-600 dark:text-white">{{ $log->user->name ?? 'System' }}</span>
                                                {{ $log->description ?? ($log->action === 'status_updated' ? 'đã cập nhật trạng thái công việc' : 'đã thực hiện một hành động') }}
                                            </p>
                                            <span
                                                class="text-2xs text-slate-400">{{ $log->created_at->diffForHumans() }}</span>
                                        </div>
                                    </div>
                                @empty
                                    <div class="py-4 text-center text-sm text-slate-500">Chưa có hoạt động nào.</div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Team Performance Section -->
                @if (count($teamData['team_member_performance'] ?? []) > 0)
                    <div>
                        <div class="mb-3 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <span class="material-symbols-outlined text-emerald-500">leaderboard</span>
                                <h2 class="text-lg font-bold text-slate-600 dark:text-white">Hiệu suất nhân sự</h2>
                            </div>
                            <a href="{{ route('kpi-scores.index') }}"
                                class="text-xs font-semibold text-primary hover:underline">Xem chi tiết KPI</a>
                        </div>
                        <x-ui.table>
                            <x-ui.table.head>
                                <x-ui.table.column width="min-w-52">Nhân viên</x-ui.table.column>
                                <x-ui.table.column align="center" width="min-w-24">Tổng Task</x-ui.table.column>
                                <x-ui.table.column align="center" width="min-w-24">Đúng hạn</x-ui.table.column>
                                <x-ui.table.column align="center" width="min-w-24">Đạt SLA</x-ui.table.column>
                                <x-ui.table.column align="center" width="min-w-24">Sao TB</x-ui.table.column>
                                <x-ui.table.column align="right" width="min-w-28">Final Score</x-ui.table.column>
                            </x-ui.table.head>
                            <x-ui.table.body>
                                @foreach ($teamData['team_member_performance'] as $member)
                                    @php
                                        $score = (float) ($member['final_score'] ?? 0);
                                        $scoreColor = $score >= 80 ? 'text-emerald-600' : ($score >= 60 ? 'text-amber-600' : 'text-rose-600');
                                    @endphp
                                    <x-ui.table.row wire:key="member-{{ $member['user_id'] }}">
                                        <x-ui.table.cell>
                                            <div class="flex items-center gap-3">
                                                @if ($member['avatar'])
                                                    <img src="{{ $member['avatar'] }}"
                                                        class="size-8 rounded-lg object-cover" />
                                                @else
                                                    <div
                                                        class="flex size-8 items-center justify-center rounded-lg bg-slate-100 text-xs font-bold dark:bg-slate-700">
                                                        {{ mb_substr($member['user_name'] ?? '?', 0, 1) }}
                                                    </div>
                                                @endif
                                                <div class="flex flex-col">
                                                    <span
                                                        class="text-sm font-bold text-slate-700 dark:text-white">{{ $member['user_name'] }}</span>
                                                    @if ($member['job_title'])
                                                        <span
                                                            class="text-2xs text-slate-400">{{ $member['job_title'] }}</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </x-ui.table.cell>
                                        <x-ui.table.cell align="center">
                                            <span class="text-sm font-semibold text-slate-600 dark:text-slate-300">{{ $member['total_tasks'] }}</span>
                                        </x-ui.table.cell>
                                        <x-ui.table.cell align="center">
                                            <span class="text-sm font-semibold {{ ($member['on_time_rate'] ?? 0) >= 80 ? 'text-emerald-600' : 'text-amber-600' }}">
                                                {{ number_format($member['on_time_rate'] ?? 0, 1) }}%
                                            </span>
                                        </x-ui.table.cell>
                                        <x-ui.table.cell align="center">
                                            <span class="text-sm font-semibold {{ ($member['sla_rate'] ?? 0) >= 80 ? 'text-emerald-600' : 'text-amber-600' }}">
                                                {{ number_format($member['sla_rate'] ?? 0, 1) }}%
                                            </span>
                                        </x-ui.table.cell>
                                        <x-ui.table.cell align="center">
                                            <span class="text-sm font-semibold text-slate-600 dark:text-slate-300">
                                                {{ number_format($member['avg_star'] ?? 0, 1) }}
                                            </span>
                                        </x-ui.table.cell>
                                        <x-ui.table.cell align="end">
                                            <span class="text-sm font-black {{ $scoreColor }}">
                                                {{ number_format($score, 1) }}
                                            </span>
                                        </x-ui.table.cell>
                                    </x-ui.table.row>
                                @endforeach
                            </x-ui.table.body>
                        </x-ui.table>
                    </div>
                @endif

                <!-- Approval Queue Table Card -->
                <div>
                    <div class="mb-3 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-amber-500">pending_actions</span>
                            <h2 class="text-lg font-bold text-slate-600 dark:text-white">Công việc chờ duyệt</h2>
                        </div>
                        <div class="flex items-center gap-2">
                            <span
                                class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-bold text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">
                                {{ count($data['approval_tasks'] ?? []) }} công việc
                            </span>
                        </div>
                    </div>
                    <x-ui.table>
                        <x-ui.table.head>
                            <x-ui.table.column width="min-w-56">Tên công việc</x-ui.table.column>
                            <x-ui.table.column width="min-w-36">Dự án</x-ui.table.column>
                            <x-ui.table.column width="min-w-40">Người thực hiện</x-ui.table.column>
                            <x-ui.table.column width="min-w-28">Hạn chót</x-ui.table.column>
                            <x-ui.table.column align="center" width="min-w-28">Tiến độ</x-ui.table.column>
                            <x-ui.table.column align="center" width="min-w-28">Trạng thái</x-ui.table.column>
                            <x-ui.table.column width="min-w-28">Cập nhập lúc</x-ui.table.column>
                            <x-ui.table.column align="right" width="min-w-20">Thao tác</x-ui.table.column>
                        </x-ui.table.head>
                        <x-ui.table.body>
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
                                @endphp
                                <x-ui.table.row wire:key="approval-{{ $task->id }}">
                                    <x-ui.table.cell>
                                        <div class="flex flex-col gap-1">
                                            <button
                                                wire:click="$dispatch('task-edit-requested', { taskId: {{ $task->id }} })"
                                                class="hover:text-primary max-w-50 truncate text-left text-sm font-bold text-slate-600 transition-colors dark:text-slate-100">
                                                {{ $task->name }}
                                            </button>
                                            <div class="flex items-center gap-2">
                                                <x-ui.badge :color="$priorityColor" size="2xs">
                                                    {{ $priorityEnum?->label() ?? '—' }}
                                                </x-ui.badge>
                                                @if ($task->comments_count > 0)
                                                    <span
                                                        class="flex items-center gap-1 text-2xs text-slate-400">
                                                        <span
                                                            class="material-symbols-outlined text-[14px]">chat_bubble</span>
                                                        {{ $task->comments_count }}
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    </x-ui.table.cell>
                                    <x-ui.table.cell>
                                        <span
                                            class="text-xs font-medium text-slate-600 dark:text-slate-400">{{ $task->phase?->project?->name ?? 'N/A' }}</span>
                                    </x-ui.table.cell>
                                    <x-ui.table.cell>
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
                                    </x-ui.table.cell>
                                    <x-ui.table.cell>
                                        <div class="flex flex-col">
                                            <span
                                                class="{{ $task->deadline && \Carbon\Carbon::parse($task->deadline)->isPast() ? 'text-rose-500' : 'text-slate-600 dark:text-slate-400' }} text-xs font-medium">
                                                {{ $task->deadline ? \Carbon\Carbon::parse($task->deadline)->format('d/m/Y') : 'N/A' }}
                                            </span>
                                            @if ($task->deadline && \Carbon\Carbon::parse($task->deadline)->isPast())
                                                <span class="text-2xs font-bold uppercase text-rose-500">Quá
                                                    hạn</span>
                                            @endif
                                        </div>
                                    </x-ui.table.cell>
                                    <x-ui.table.cell align="center">
                                        <div class="w-24">
                                            <div
                                                class="mb-1 flex items-center justify-between text-2xs font-bold text-slate-500">
                                                <span>{{ $task->progress }}%</span>
                                            </div>
                                            <div
                                                class="h-1.5 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                                <div class="{{ $task->progress >= 100 ? 'bg-emerald-500' : 'bg-primary' }} h-full transition-all duration-500"
                                                    style="width: {{ $task->progress }}%"></div>
                                            </div>
                                        </div>
                                    </x-ui.table.cell>
                                    <x-ui.table.cell align="center">
                                        @php
                                            $statusValue =
                                                $task->status instanceof \BackedEnum
                                                    ? $task->status->value
                                                    : $task->status->value ?? ($task->status ?? '');
                                            $statusEnum = \App\Enums\TaskStatus::tryFrom($statusValue);
                                        @endphp
                                        <span
                                            class="{{ $statusEnum?->badgeClass() ?? 'bg-slate-100 text-slate-600' }} rounded px-2 py-0.5 text-2xs font-bold uppercase">
                                            {{ $statusEnum?->label() ?? $task->status }}
                                        </span>
                                    </x-ui.table.cell>
                                    <x-ui.table.cell>
                                        <div
                                            class="mb-1 flex items-center justify-between text-2xs font-bold text-slate-500">
                                            <span> {{ $task->updated_at->diffForHumans() }}</span>
                                        </div>
                                    </x-ui.table.cell>
                                    <x-ui.table.cell align="end">
                                        <button
                                            wire:click="$dispatch('task-edit-requested', { taskId: {{ $task->id }} })"
                                            class="hover:border-primary hover:text-primary ml-auto flex size-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-400 transition-all dark:border-slate-800 dark:bg-slate-900">
                                            <span class="material-symbols-outlined text-[18px]">visibility</span>
                                        </button>
                                    </x-ui.table.cell>
                                </x-ui.table.row>
                            @empty
                                <x-ui.table.row>
                                    <x-ui.table.cell colspan="8" align="center">
                                        <div class="flex flex-col items-center gap-2 py-4">
                                            <span
                                                class="material-symbols-outlined text-4xl text-slate-200 dark:text-slate-800">check_circle</span>
                                            <p class="text-sm font-medium tracking-wide text-slate-500">Tất cả công
                                                việc đã được phê duyệt!</p>
                                        </div>
                                    </x-ui.table.cell>
                                </x-ui.table.row>
                            @endforelse
                        </x-ui.table.body>
                    </x-ui.table>
                </div>
            </div>
        </div>
    </main>
    <livewire:task.form />

    <script>
        let leaderActivityChart = null;
        let projectStatusChart = null;

        function initLeaderCharts(eventData = null) {
            // Use event data if present, otherwise fall back to initial blade data
            const weeklyStats = eventData?.weeklyStats || @json($weeklyStats);
            const projects = eventData?.projects || @json($data['projects']);

            // Destroy existing charts to prevent memory leaks and overlapping
            if (leaderActivityChart) {
                leaderActivityChart.destroy();
                leaderActivityChart = null;
            }
            if (projectStatusChart) {
                projectStatusChart.destroy();
                projectStatusChart = null;
            }

            // Line chart: Task hoàn thành vs tạo mới 7 ngày qua
            const lineCtx = document.getElementById('leaderActivityChart');
            if (lineCtx) {
                const isDark = document.documentElement.classList.contains('dark');
                const gridColor = isDark ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)';
                const labelColor = isDark ? '#94a3b8' : '#64748b';

                leaderActivityChart = new Chart(lineCtx, {
                    type: 'line',
                    data: {
                        labels: weeklyStats.map(s => s.label),
                        datasets: [{
                                label: 'Hoàn thành',
                                data: weeklyStats.map(s => s.completed),
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
                                data: weeklyStats.map(s => s.created),
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
                projectStatusChart = new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Hoàn thành', 'Đang chạy', 'Tạm dừng'],
                        datasets: [{
                            data: [
                                projects?.completed || 0,
                                projects?.running || 0,
                                projects?.paused || 0
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

            // Ensure charts correctly size when created (useful if container was hidden)
            if (leaderActivityChart && typeof leaderActivityChart.resize === 'function') {
                leaderActivityChart.resize();
            }
            if (projectStatusChart && typeof projectStatusChart.resize === 'function') {
                projectStatusChart.resize();
            }
        }

        document.addEventListener('livewire:navigated', () => {
            initLeaderCharts();
        });

        // Re-initialize after Livewire updates
        document.addEventListener('charts-updated', (event) => {
            // Livewire 3/Volt event detail might be an array or the object itself
            const data = (Array.isArray(event.detail) ? event.detail[0] : event.detail) || null;
            initLeaderCharts(data);
        });

        // Ensure charts initialize on first load: wait until canvas is visible, retrying if needed
        function waitForCanvasAndInit(retries = 12, delay = 150) {
            const leaderCanvas = document.getElementById('leaderActivityChart');
            const projectCanvas = document.getElementById('projectStatusChart');
            const isVisible = (el) => {
                if (!el) return false;
                const r = el.getBoundingClientRect();
                return r.width > 0 && r.height > 0;
            };

            if (isVisible(leaderCanvas) || isVisible(projectCanvas)) {
                initLeaderCharts();
                // ensure Chart.js recalculates sizes after a tick
                setTimeout(() => window.dispatchEvent(new Event('resize')), 50);
            } else if (retries > 0) {
                setTimeout(() => waitForCanvasAndInit(retries - 1, delay), delay);
            } else {
                // final fallback
                initLeaderCharts();
                setTimeout(() => window.dispatchEvent(new Event('resize')), 200);
            }
        }

        document.addEventListener('DOMContentLoaded', () => waitForCanvasAndInit());
        window.addEventListener('load', () => waitForCanvasAndInit());
        // attempt shortly after script runs in case DOM is already ready
        setTimeout(() => waitForCanvasAndInit(), 200);
    </script>
</div>
