<?php
use Livewire\Component;
use App\Enums\TaskPriority;
use App\Models\Task;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use Carbon\Carbon;

new class extends Component {
    public array $data = [];
    public string $filter = 'all';
    public $currentMonth;
    public $selectedDate;

    public function mount(array $data)
    {
        $this->data = $data;
        $this->currentMonth = now()->startOfMonth()->format('Y-m-d');
        $this->selectedDate = now()->format('Y-m-d');
    }

    public function prevMonth()
    {
        $this->currentMonth = Carbon::parse($this->currentMonth)->subMonth()->startOfMonth()->format('Y-m-d');
    }

    public function nextMonth()
    {
        $this->currentMonth = Carbon::parse($this->currentMonth)->addMonth()->startOfMonth()->format('Y-m-d');
    }

    public function getCalendarProperty()
    {
        $startOfMonth = Carbon::parse($this->currentMonth);
        $endOfMonth = $startOfMonth->copy()->endOfMonth();

        $startOfWeek = $startOfMonth->copy()->startOfWeek(Carbon::MONDAY);
        $endOfWeek = $endOfMonth->copy()->endOfWeek(Carbon::SUNDAY);

        // Fetch task dates for the range
        $userId = auth()->id();
        $taskDates = Task::query()
            ->where(function ($q) use ($userId) {
                $q->where('pic_id', $userId)->orWhereHas('coPics', function ($q2) use ($userId) {
                    $q2->where('user_id', $userId);
                });
            })
            ->whereBetween('deadline', [$startOfWeek->copy()->startOfDay(), $endOfWeek->copy()->endOfDay()])
            ->where('status', '!=', TaskStatus::Completed->value)
            ->get(['deadline', 'status'])
            ->groupBy(function ($task) {
                return $task->deadline ? $task->deadline->format('Y-m-d') : null;
            });

        $dates = [];
        $current = $startOfWeek->copy();

        while ($current <= $endOfWeek) {
            $dateStr = $current->format('Y-m-d');
            $hasTask = $taskDates->has($dateStr);
            $taskStatus = $hasTask ? $taskDates[$dateStr]->first()->status : null;

            $dates[] = [
                'date' => $dateStr,
                'day' => $current->day,
                'is_current_month' => $current->month === $startOfMonth->month,
                'is_today' => $current->isToday(),
                'has_task' => $hasTask,
                'task_status' => $taskStatus,
            ];
            $current->addDay();
        }

        return $dates;
    }

    public function setFilter(string $filter)
    {
        $this->filter = $filter;
    }

    public function getFilteredTasksProperty()
    {
        $tasks = collect($this->data['recent_tasks'] ?? []);

        if ($this->filter === 'high_priority') {
            return $tasks->filter(function ($task) {
                $priority = $task->priority instanceof \BackedEnum ? $task->priority->value : $task->priority;
                return in_array($priority, [TaskPriority::High->value, TaskPriority::Urgent->value]);
            });
        }

        return $tasks;
    }
};
?>
<div x-data="{ ready: false }" x-init="setTimeout(() => ready = true, 100)">
    <!-- Main Content Area -->
    <main class="grid grid-cols-1 md:grid-cols-2 md:gap-4 lg:grid-cols-4">
        <!-- Stats Grid -->
        <div class="col-span-full mb-4 grid grid-cols-2 gap-4 lg:grid-cols-4" x-show="ready"
            x-transition:enter="transition ease-out duration-500" x-transition:enter-start="opacity-0 translate-y-4"
            x-transition:enter-end="opacity-100 translate-y-0" style="display: none;">
            <div
                class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center justify-between">
                    <span class="rounded-lg bg-blue-100 p-2 text-blue-600 dark:bg-blue-900/30">
                        <span class="material-symbols-outlined">calendar_today</span>
                    </span>
                    <span
                        class="rounded-full bg-green-100 px-2 py-1 text-xs font-bold text-green-600 dark:bg-green-900/30">+{{ $data['tasks']['due_soon'] }}</span>
                </div>
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Việc sắp tới hạn</p>
                <p class="text-3xl font-black text-slate-900 dark:text-white">{{ $data['tasks']['due_soon'] ?? 0 }}</p>
            </div>
            <div
                class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center justify-between">
                    <span class="rounded-lg bg-amber-100 p-2 text-amber-600 dark:bg-amber-900/30">
                        <span class="material-symbols-outlined">pending</span>
                    </span>
                    <span
                        class="rounded-full bg-amber-100 px-2 py-1 text-xs font-bold text-amber-600 dark:bg-amber-900/30">Đang
                        chạy</span>
                </div>
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Đang thực hiện</p>
                <p class="text-3xl font-black text-slate-900 dark:text-white">{{ $data['tasks']['in_progress'] ?? 0 }}
                </p>
            </div>
            <div
                class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center justify-between">
                    <span class="rounded-lg bg-purple-100 p-2 text-purple-600 dark:bg-purple-900/30">
                        <span class="material-symbols-outlined">fact_check</span>
                    </span>
                    <span
                        class="rounded-full bg-slate-100 px-2 py-1 text-xs font-bold text-slate-500 dark:bg-slate-800">Chờ
                        duyệt</span>
                </div>
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Chờ phê duyệt</p>
                <p class="text-3xl font-black text-slate-900 dark:text-white">
                    {{ $data['tasks']['waiting_approval'] ?? 0 }}</p>
            </div>
            <div
                class="flex flex-col gap-3 rounded-2xl border border-rose-100 bg-rose-50 p-6 shadow-sm dark:border-rose-900/20 dark:bg-rose-900/10">
                <div class="flex items-center justify-between">
                    <span class="rounded-lg bg-rose-100 p-2 text-rose-600 dark:bg-rose-900/30">
                        <span class="material-symbols-outlined">error</span>
                    </span>
                    <span
                        class="rounded-full bg-rose-100 px-2 py-1 text-xs font-bold text-rose-600 dark:bg-rose-900/30">Cần
                        xử lý</span>
                </div>
                <p class="text-sm font-medium text-rose-700 dark:text-rose-400">Việc quá hạn</p>
                <p class="text-3xl font-black text-rose-900 dark:text-rose-100">{{ $data['tasks']['late'] ?? 0 }}</p>
            </div>
        </div>
        <!-- Task List Section -->
        <div class="col-span-3 flex flex-col gap-4" x-show="ready"
            x-transition:enter="transition ease-out duration-500 delay-100"
            x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0"
            style="display: none;">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white">Công việc gần đây</h2>
                <div class="flex gap-2">
                    <x-ui.button size="xs" variant="{{ $this->filter === 'all' ? 'outline' : 'ghost' }}"
                        wire:click="setFilter('all')">
                        Tất cả
                    </x-ui.button>
                    <x-ui.button size="xs" variant="{{ $this->filter === 'high_priority' ? 'primary' : 'ghost' }}"
                        wire:click="setFilter('high_priority')">
                        Ưu tiên cao
                    </x-ui.button>
                </div>
            </div>
            <div class="flex flex-col gap-3">
                @forelse($this->filteredTasks as $task)
                    <div x-data="{ show: false }" x-init="setTimeout(() => show = true, {{ $loop->index * 100 + 200 }})" x-show="show"
                        x-transition:enter="transition ease-out duration-300"
                        x-transition:enter-start="opacity-0 translate-x-4"
                        x-transition:enter-end="opacity-100 translate-x-0" style="display: none;"
                        class="hover:border-primary dark:hover:border-primary group flex cursor-pointer flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-4 transition-all sm:flex-row sm:items-center sm:justify-between sm:gap-4 dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex flex-1 items-start gap-3 sm:items-center sm:gap-4">
                            <div
                                class="group-hover:border-primary group-hover:text-primary flex h-9 w-9 shrink-0 items-center justify-center rounded-full border-2 border-slate-200 text-slate-300 transition-colors sm:h-10 sm:w-10 dark:border-slate-700">
                                <span class="material-symbols-outlined text-lg sm:text-xl">check</span>
                            </div>
                            <div class="min-w-0 flex-1 flex-col">
                                @if ($task->phase && $task->phase->project)
                                    <button
                                        wire:click="$dispatch('task-edit-requested', { taskId: {{ $task->id }} })"
                                        class="group-hover:text-primary block text-left text-sm font-semibold text-slate-900 transition-colors hover:underline sm:text-base dark:text-white">
                                        {{ $task->name }}
                                    </button>
                                @else
                                    <span
                                        class="group-hover:text-primary text-sm font-semibold text-slate-900 transition-colors sm:text-base dark:text-white">{{ $task->name }}</span>
                                @endif
                                <div class="mt-1.5 flex flex-wrap items-center gap-2 sm:gap-3">
                                    <span
                                        class="rounded bg-slate-100 px-2 py-0.5 text-[10px] font-bold uppercase text-slate-600 sm:text-[11px] dark:bg-slate-800 dark:text-slate-400">
                                        {{ $task->priority instanceof TaskPriority ? $task->priority->label() : $task->priority }}
                                    </span>
                                    <span
                                        class="{{ $task->status instanceof TaskStatus ? $task->status->badgeClass() : 'bg-slate-100 text-slate-600' }} rounded px-2 py-0.5 text-[10px] font-bold uppercase sm:text-[11px]">
                                        {{ $task->status instanceof TaskStatus ? $task->status->label() : $task->status }}
                                    </span>
                                    <span
                                        class="rounded border border-slate-200 bg-slate-50 px-2 py-0.5 text-[10px] font-bold text-slate-600 sm:text-[11px] dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400">
                                        {{ $task->type instanceof TaskType ? $task->type->label() : $task->type ?? 'N/A' }}
                                    </span>
                                    <span class="flex items-center gap-1 text-[11px] text-slate-500 sm:text-xs">
                                        <span class="material-symbols-outlined text-sm">schedule</span>
                                        Hạn:
                                        {{ $task->deadline ? Carbon::parse($task->deadline)->format('d/m/Y H:i') : 'N/A' }}
                                    </span>
                                    @if ($task->comments_count > 0)
                                        <span class="flex items-center gap-1 text-[11px] text-slate-500 sm:text-xs"
                                            title="{{ $task->comments_count }} bình luận">
                                            <span class="material-symbols-outlined text-sm">chat_bubble</span>
                                            {{ $task->comments_count }}
                                        </span>
                                    @endif
                                </div>
                                <div class="mt-3 w-full max-w-full sm:mt-2 md:max-w-[200px]">
                                    <div class="mb-1 flex items-center justify-between text-[10px] text-slate-500">
                                        <span>Tiến độ</span>
                                        <span class="font-bold">{{ $task->progress }}%</span>
                                    </div>
                                    <div
                                        class="h-1.5 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                        <div class="{{ $task->progress >= 100 ? 'bg-green-500' : 'bg-primary' }} h-full rounded-full"
                                            style="width: {{ $task->progress }}%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div
                            class="flex items-center justify-between border-t border-slate-50 pt-3 sm:justify-end sm:gap-4 sm:border-0 sm:pt-0 dark:border-slate-800">
                            <div class="flex -space-x-2">
                                @if ($task->pic)
                                    <a href="{{ route('users.show', $task->pic) }}"
                                        class="block h-7 w-7 overflow-hidden rounded-full border-2 border-white bg-slate-200 dark:border-slate-900"
                                        title="PIC: {{ $task->pic->name }}{{ $task->pic->job_title ? ' - ' . $task->pic->job_title : '' }}">
                                        @if ($task->pic->avatar_url)
                                            <img src="{{ $task->pic->avatar_url }}" class="h-full w-full object-cover"
                                                alt="{{ $task->pic->name }}">
                                        @else
                                            <span
                                                class="flex h-full w-full items-center justify-center text-[10px] font-bold text-slate-500">{{ substr($task->pic->name, 0, 1) }}</span>
                                        @endif
                                    </a>
                                @endif
                                {{-- Show first co-pic if exists --}}
                                @if ($task->coPics->isNotEmpty())
                                    @php
                                        $firstCoPic = $task->coPics->first();
                                        $remainingCount = $task->coPics->count() - 1;
                                        $remainingNames = $task->coPics->slice(1)->map(fn($u) => $u->name)->join(', ');
                                    @endphp

                                    <a href="{{ route('users.show', $firstCoPic) }}"
                                        class="block h-7 w-7 overflow-hidden rounded-full border-2 border-white bg-slate-300 dark:border-slate-900"
                                        title="Co-PIC: {{ $firstCoPic->name }}{{ $firstCoPic->job_title ? ' - ' . $firstCoPic->job_title : '' }}">
                                        @if ($firstCoPic->avatar_url)
                                            <img src="{{ $firstCoPic->avatar_url }}" class="h-full w-full object-cover"
                                                alt="{{ $firstCoPic->name }}">
                                        @else
                                            <span
                                                class="flex h-full w-full items-center justify-center text-[10px] font-bold text-slate-500">{{ substr($firstCoPic->name, 0, 1) }}</span>
                                        @endif
                                    </a>

                                    @if ($remainingCount > 0)
                                        <div class="flex h-7 w-7 cursor-help items-center justify-center rounded-full border-2 border-white bg-slate-100 text-[10px] font-bold text-slate-500 dark:border-slate-900 dark:bg-slate-800"
                                            title="Co-PICs: {{ $remainingNames }}">
                                            +{{ $remainingCount }}
                                        </div>
                                    @endif
                                @endif
                            </div>
                            @if ($task->phase && $task->phase->project)
                                <button wire:click="$dispatch('task-edit-requested', { taskId: {{ $task->id }} })"
                                    class="flex h-8 w-8 items-center justify-center rounded-full transition-colors hover:bg-slate-100 dark:hover:bg-slate-800"
                                    title="Xem chi tiết">
                                    <span class="material-symbols-outlined text-slate-400">visibility</span>
                                </button>
                            @else
                                <span class="material-symbols-outlined cursor-not-allowed text-slate-400"
                                    title="Không có liên kết">more_vert</span>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center text-slate-500">
                        <span class="material-symbols-outlined mx-auto mb-2 block text-4xl text-slate-300">task</span>
                        <p>Không có công việc nào cần xử lý ngay.</p>
                    </div>
                @endforelse
            </div>
        </div>
        <!-- Right Side Panel -->
        <aside class="col-span-1 hidden shrink-0 flex-col gap-6 lg:flex" x-show="ready"
            x-transition:enter="transition ease-out duration-500 delay-200"
            x-transition:enter-start="opacity-0 translate-x-4" x-transition:enter-end="opacity-100 translate-x-0"
            style="display: none;">
            <!-- Mini Calendar -->
            <div
                class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="mb-4 flex items-center justify-between">
                    <span class="text-sm font-bold text-slate-900 dark:text-white">Tháng
                        {{ Carbon::parse($this->currentMonth)->format('m/Y') }}</span>
                    <div class="flex gap-1">
                        <button wire:click="prevMonth" class="rounded p-1 hover:bg-slate-100 dark:hover:bg-slate-800">
                            <span class="material-symbols-outlined cursor-pointer p-1 text-sm">chevron_left</span>
                        </button>
                        <button wire:click="nextMonth" class="rounded p-1 hover:bg-slate-100 dark:hover:bg-slate-800">
                            <span class="material-symbols-outlined cursor-pointer p-1 text-sm">chevron_right</span>
                        </button>
                    </div>
                </div>

                <!-- Weekdays -->
                <div class="mb-2 grid grid-cols-7 text-center">
                    @foreach (['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'] as $day)
                        <span class="text-[10px] font-bold text-slate-400">{{ $day }}</span>
                    @endforeach
                </div>

                <!-- Days -->
                <div class="grid grid-cols-7 gap-1 text-center">
                    @foreach ($this->calendar as $date)
                        <div
                            class="{{ $date['is_current_month'] ? 'text-slate-700 dark:text-slate-300' : 'text-slate-300 dark:text-slate-600' }} {{ $date['is_today'] ? 'bg-primary text-white font-bold' : 'hover:bg-slate-50 dark:hover:bg-slate-800' }} relative flex aspect-square cursor-pointer flex-col items-center justify-center rounded-lg text-xs transition-colors">
                            <span>{{ $date['day'] }}</span>
                            @if ($date['has_task'])
                                <span
                                    class="{{ $date['is_today'] ? 'bg-white' : 'bg-red-500' }} absolute bottom-1 h-1.5 w-1.5 rounded-full">
                                </span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- KPI Card -->
            <div
                class="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-emerald-500">show_chart</span>
                        <h3 class="text-sm font-bold uppercase tracking-tight text-slate-900 dark:text-white">
                            KPI Cá nhân</h3>
                    </div>
                    <span
                        class="rounded bg-slate-100 px-2 py-0.5 text-[10px] font-bold uppercase text-slate-400 dark:bg-slate-800">Tháng
                        này</span>
                </div>
                <div class="flex flex-col gap-4">
                    <div>
                        <div class="mb-1 flex justify-between">
                            <span class="text-xs font-medium text-slate-500">Điểm tổng kết</span>
                            <span
                                class="text-xs font-bold text-slate-900 dark:text-white">{{ $data['kpi']['monthly']['final_score'] ?? 0 }}/100</span>
                        </div>
                        <div class="h-2 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                            <div class="bg-primary h-full"
                                style="width: {{ min($data['kpi']['monthly']['final_score'] ?? 0, 100) }}%"></div>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2 text-xs">
                        <div class="rounded-lg bg-slate-50 p-2 dark:bg-slate-800">
                            <span class="block text-slate-500">Đúng hạn</span>
                            <span
                                class="block font-bold text-slate-900 dark:text-white">{{ $data['kpi']['monthly']['on_time_rate'] ?? 0 }}%</span>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-2 dark:bg-slate-800">
                            <span class="block text-slate-500">Chất lượng</span>
                            <span
                                class="block font-bold text-slate-900 dark:text-white">{{ $data['kpi']['monthly']['avg_star'] ?? 0 }}
                                <span class="text-amber-500">★</span></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Motivational Quote -->
            <div
                class="from-primary shadow-primary/20 bg-linear-to-br rounded-2xl to-blue-700 p-6 text-white shadow-xl">
                <span class="material-symbols-outlined mb-2 text-3xl opacity-50">format_quote</span>
                <p class="text-sm font-medium italic leading-relaxed">"Năng suất không phải là làm nhiều việc
                    hơn, mà là làm những việc đúng đắn một cách tập trung nhất."</p>
                <div class="mt-4 flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-widest opacity-80">— TaskXPro Team</span>
                    <span class="material-symbols-outlined text-xl">rocket_launch</span>
                </div>
            </div>
        </aside>
    </main>

    <livewire:task.form />
</div>
