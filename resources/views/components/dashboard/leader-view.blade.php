<?php
use Livewire\Component;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Task;

new class extends Component {
    public array $data = [];

    public function mount(array $data)
    {
        $this->data = $data;
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
        <div class="bg-background-light dark:bg-background-dark flex-1 overflow-y-auto p-8">
            <div class="mx-auto max-w-7xl space-y-8" x-show="ready" x-transition:enter="transition ease-out duration-500"
                x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0"
                style="display: none;">
                <!-- Page Header -->
                <div class="flex flex-col justify-between gap-4 md:flex-row md:items-end">
                    <div>
                        <x-ui.heading title="Bảng điều khiển Vận hành và Phê duyệt"
                            description="Theo dõi thời gian thực các chỉ số vận hành và quy trình phê duyệt doanh nghiệp." />
                    </div>
                    <div class="flex gap-2">
                        {{-- <x-ui.button
                            variant="outline"
                            size="sm"
                            icon="calendar_today"
                            wire:click="filterDate"
                        >
                            7 ngày qua
                        </x-ui.button> --}}
                        {{-- <x-ui.button
                            variant="outline"
                            size="sm"
                            icon="download"
                            wire:click="exportReport"
                        >
                            Xuất báo cáo
                        </x-ui.button> --}}
                    </div>
                </div>
                <!-- Top Stats & Chart -->
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <!-- Line Chart Component -->
                    <div
                        class="flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-2 dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex items-start justify-between">
                            <div>
                                <h3 class="text-lg font-bold text-slate-900 dark:text-white">Tiến độ Dự án: Thực tế vs
                                    Kế hoạch</h3>
                                <p class="text-sm text-slate-500">Tổng hợp dữ liệu từ
                                    {{ $data['projects']['running'] ?? 0 }} dự án đang chạy</p>
                            </div>
                            <div class="flex items-center gap-4 text-xs font-semibold">
                                <div class="flex items-center gap-1.5"><span
                                        class="bg-primary size-2.5 rounded-full"></span><span>Thực tế</span></div>
                                <div class="flex items-center gap-1.5"><span
                                        class="size-2.5 rounded-full bg-slate-300"></span><span>Kế hoạch</span></div>
                            </div>
                        </div>
                        <div class="relative mt-4 min-h-[240px] flex-1">
                            <!-- Simplified Line Chart SVG -->
                            <svg class="h-full w-full" preserveaspectratio="none" viewbox="0 0 800 240">
                                <!-- Grid Lines -->
                                <line class="dark:stroke-slate-800" stroke="#e2e8f0" stroke-dasharray="4" x1="0"
                                    x2="800" y1="60" y2="60"></line>
                                <line class="dark:stroke-slate-800" stroke="#e2e8f0" stroke-dasharray="4" x1="0"
                                    x2="800" y1="120" y2="120"></line>
                                <line class="dark:stroke-slate-800" stroke="#e2e8f0" stroke-dasharray="4" x1="0"
                                    x2="800" y1="180" y2="180"></line>
                                <!-- Planned Line (Light gray) -->
                                <path d="M0,180 L100,160 L200,140 L300,120 L400,100 L500,80 L600,60 L700,40 L800,20"
                                    fill="none" stroke="#cbd5e1" stroke-width="2"></path>
                                <!-- Actual Line (Primary) -->
                                <path
                                    d="M0,180 C50,185 100,150 150,155 C200,160 250,120 300,110 C350,100 400,115 450,90 C500,65 550,75 600,45 C650,15 750,30 800,10"
                                    fill="none" stroke="#135bec" stroke-linecap="round" stroke-width="4"></path>
                                <!-- Gradient Fill for Actual -->
                                <path
                                    d="M0,180 C50,185 100,150 150,155 C200,160 250,120 300,110 C350,100 400,115 450,90 C500,65 550,75 600,45 C650,15 750,30 800,10 V240 H0 Z"
                                    fill="url(#grad1)" opacity="0.1"></path>
                                <defs>
                                    <lineargradient id="grad1" x1="0%" x2="0%" y1="0%"
                                        y2="100%">
                                        <stop offset="0%" style="stop-color:#135bec;stop-opacity:1"></stop>
                                        <stop offset="100%" style="stop-color:#135bec;stop-opacity:0"></stop>
                                    </lineargradient>
                                </defs>
                            </svg>
                            <div class="mt-2 flex justify-between px-1 text-[10px] font-bold text-slate-400">
                                <span>T2</span><span>T3</span><span>T4</span><span>T5</span><span>T6</span><span>T7</span><span>CN</span>
                            </div>
                        </div>
                    </div>
                    <!-- PIC Resource Heatmap -->
                    <div
                        class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <h3 class="mb-4 text-lg font-bold text-slate-900 dark:text-white">Tổng quan</h3>
                        <div class="space-y-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-slate-500">Tổng số dự án</p>
                                    <p class="text-2xl font-bold text-slate-900 dark:text-white">
                                        {{ $data['projects']['total'] ?? 0 }}</p>
                                </div>
                                <span
                                    class="material-symbols-outlined text-primary text-3xl opacity-50">folder_shared</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-slate-500">Công việc đang chạy</p>
                                    <p class="text-2xl font-bold text-slate-900 dark:text-white">
                                        {{ $data['tasks']['in_progress'] ?? 0 }}</p>
                                </div>
                                <span class="material-symbols-outlined text-3xl text-blue-500 opacity-50">pending</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-slate-500">Chờ phê duyệt</p>
                                    <p class="text-2xl font-bold text-slate-900 dark:text-white">
                                        {{ $data['tasks']['waiting_approval'] ?? 0 }}</p>
                                </div>
                                <span
                                    class="material-symbols-outlined text-3xl text-amber-500 opacity-50">fact_check</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-slate-500">Công việc quá hạn</p>
                                    <p class="text-2xl font-bold text-slate-900 dark:text-white">
                                        {{ $data['tasks']['late'] ?? 0 }}</p>
                                </div>
                                <span class="material-symbols-outlined text-3xl text-red-500 opacity-50">warning</span>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Approval Queue Section -->
                <div
                    class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-center justify-between border-b border-slate-200 p-6 dark:border-slate-800">
                        <div class="flex items-center gap-3">
                            <h2 class="text-lg font-bold text-slate-900 dark:text-white">Công việc gần đây</h2>
                        </div>
                        <button class="text-primary text-sm font-bold hover:underline">Xem tất cả</button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead>
                                <tr
                                    class="bg-slate-50 text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:bg-slate-800/50">
                                    <th class="px-6 py-4">Tên công việc</th>
                                    <th class="px-6 py-4">Dự án</th>
                                    <th class="px-6 py-4">Người thực hiện</th>
                                    <th class="px-6 py-4">Hạn chót</th>
                                    <th class="px-6 py-4">Trạng thái</th>
                                    <th class="px-6 py-4">Tiến độ</th>
                                    <th class="px-6 py-4 text-right">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                @forelse($data['recent_tasks'] as $task)
                                    @php
                                        $priorityEnum =
                                            $task->priority instanceof \App\Enums\TaskPriority
                                                ? $task->priority
                                                : \App\Enums\TaskPriority::tryFrom($task->priority ?? '');
                                        $priorityColor = match ($priorityEnum?->value ?? '') {
                                            'urgent' => 'red',
                                            'high' => 'orange',
                                            'medium' => 'amber',
                                            default => 'blue',
                                        };
                                    @endphp
                                    <tr class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-800">
                                        <td class="px-6 py-4">
                                            @if ($task->phase && $task->phase->project)
                                                <button
                                                    wire:click="$dispatch('task-edit-requested', { taskId: {{ $task->id }} })"
                                                    class="hover:text-primary block text-left font-semibold text-slate-900 transition-colors dark:text-slate-100">
                                                    {{ $task->name }}
                                                </button>
                                            @else
                                                <div class="font-semibold text-slate-900 dark:text-slate-100">
                                                    {{ $task->name }}</div>
                                            @endif
                                            <div class="mt-1 flex items-center gap-2">
                                                <x-ui.badge :color="$priorityColor" size="xs">
                                                    {{ $priorityEnum?->label() ?? '—' }}
                                                </x-ui.badge>
                                                @if ($task->comments_count > 0)
                                                    <span class="flex items-center gap-1 text-[10px] text-slate-400"
                                                        title="{{ $task->comments_count }} bình luận">
                                                        <span
                                                            class="material-symbols-outlined text-[14px]">chat_bubble</span>
                                                        {{ $task->comments_count }}
                                                    </span>
                                                @endif

                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-slate-500">
                                            {{ $task->phase?->project?->name ?? 'N/A' }}
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-2">
                                                @if ($task->pic)
                                                    <a href="{{ route('users.show', $task->pic) }}"
                                                        class="hover:border-primary block size-8 overflow-hidden rounded-full border-2 border-transparent bg-slate-200 transition-all"
                                                        title="{{ $task->pic->name }}{{ $task->pic->job_title ? ' - ' . $task->pic->job_title : '' }}">
                                                        @if ($task->pic->avatar_url)
                                                            <img src="{{ $task->pic->avatar_url }}"
                                                                alt="{{ $task->pic->name }}"
                                                                class="h-full w-full object-cover">
                                                        @else
                                                            <span
                                                                class="flex h-full w-full items-center justify-center text-[10px] font-bold text-slate-500">{{ substr($task->pic->name, 0, 1) }}</span>
                                                        @endif
                                                    </a>
                                                    <div class="flex flex-col">
                                                        <span
                                                            class="text-sm font-medium">{{ $task->pic->name }}</span>
                                                        @if ($task->coPics->isNotEmpty())
                                                            <span
                                                                class="text-[10px] text-slate-400">+{{ $task->coPics->count() }}
                                                                người khác</span>
                                                        @endif
                                                    </div>
                                                @else
                                                    <span class="text-sm text-slate-400">Chưa giao</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-slate-500">
                                            {{ $task->deadline ? \Carbon\Carbon::parse($task->deadline)->format('d/m/Y') : 'N/A' }}
                                            @if (
                                                $task->deadline &&
                                                    \Carbon\Carbon::parse($task->deadline)->isPast() &&
                                                    $task->status !== \App\Enums\TaskStatus::Completed)
                                                <span class="mt-0.5 block text-[10px] font-bold text-rose-500">Quá
                                                    hạn</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4">
                                            <span
                                                class="{{ $task->status instanceof TaskStatus ? $task->status->badgeClass() : 'bg-slate-100 text-slate-600' }} rounded px-2 py-1 text-[10px] font-bold uppercase">
                                                {{ $task->status instanceof TaskStatus ? $task->status->label() : $task->status }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="w-full max-w-[100px]">
                                                <div
                                                    class="mb-1 flex items-center justify-between text-[10px] text-slate-500">
                                                    <span>{{ $task->progress }}%</span>
                                                </div>
                                                <div
                                                    class="h-1.5 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                                    <div class="{{ $task->progress >= 100 ? 'bg-green-500' : 'bg-primary' }} h-full rounded-full"
                                                        style="width: {{ $task->progress }}%"></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            @if ($task->phase && $task->phase->project)
                                                <button
                                                    wire:click="$dispatch('task-edit-requested', { taskId: {{ $task->id }} })"
                                                    class="ml-auto flex size-8 items-center justify-center rounded-lg bg-slate-100 text-slate-600 transition-colors hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-400"
                                                    title="Chi tiết">
                                                    <span class="material-symbols-outlined text-lg">visibility</span>
                                                </button>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-6 py-8 text-center text-sm text-slate-500">
                                            Không có công việc nào gần đây.
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
</div>
