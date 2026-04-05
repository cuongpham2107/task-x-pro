@props(['tasks'])

@php
    use App\Enums\TaskStatus;
    use Illuminate\Contracts\Pagination\LengthAwarePaginator;
    use Illuminate\Support\Carbon;

    $taskCollection = $tasks instanceof LengthAwarePaginator ? $tasks->getCollection() : collect($tasks);

    $datedTasks = $taskCollection->filter(fn($task) => $task->started_at !== null && $task->deadline !== null);

    if ($datedTasks->isEmpty()) {
        $gantt = [
            'hasTimeline' => false,
            'items' => [],
        ];
    } else {
        $starts = $datedTasks->map(function ($task) {
            return $task->started_at instanceof Carbon
                ? $task->started_at->copy()->startOfDay()
                : Carbon::parse($task->started_at)->startOfDay();
        });

        $ends = $datedTasks->map(function ($task) {
            return $task->deadline instanceof Carbon
                ? $task->deadline->copy()->startOfDay()
                : Carbon::parse($task->deadline)->startOfDay();
        });

        $rangeStart = $starts->min();
        $rangeEnd = $ends->max();
        $totalDays = max(1, $rangeStart->diffInDays($rangeEnd) + 1);

        $months = [];
        $cursor = $rangeStart->copy()->startOfMonth();

        while ($cursor->lte($rangeEnd)) {
            $monthStart = $cursor->copy()->startOfMonth();
            $monthEnd = $cursor->copy()->endOfMonth();

            $from = $monthStart->lt($rangeStart) ? $rangeStart->copy() : $monthStart;
            $to = $monthEnd->gt($rangeEnd) ? $rangeEnd->copy() : $monthEnd;
            $days = max(1, $from->diffInDays($to) + 1);
            $offsetDays = $rangeStart->diffInDays($from);

            $months[] = [
                'label' => $cursor->locale(app()->getLocale())->isoFormat('MMM'),
                'year' => $cursor->format('Y'),
                'left' => round(($offsetDays / $totalDays) * 100, 4),
                'width' => round(($days / $totalDays) * 100, 4),
            ];

            $cursor->addMonth();
        }

        $weekLines = [];
        for ($day = 7; $day < $totalDays; $day += 7) {
            $weekLines[] = round(($day / $totalDays) * 100, 4);
        }

        $items = [];
        foreach ($taskCollection as $task) {
            $statusEnum = \App\Enums\TaskStatus::tryFrom($task->status->value ?? ($task->status ?? ''));

            $start =
                $task->started_at instanceof Carbon
                    ? $task->started_at->copy()->startOfDay()
                    : ($task->started_at
                        ? Carbon::parse($task->started_at)->startOfDay()
                        : null);

            $end =
                $task->deadline instanceof Carbon
                    ? $task->deadline->copy()->startOfDay()
                    : ($task->deadline
                        ? Carbon::parse($task->deadline)->startOfDay()
                        : null);

            $hasDate = $start !== null && $end !== null;
            if ($hasDate && $end->lt($start)) {
                $end = $start->copy();
            }

            $offset = $hasDate ? $rangeStart->diffInDays($start) : 0;
            $duration = $hasDate ? max(1, $start->diffInDays($end) + 1) : 0;

            $color = $statusEnum?->color() ?? 'slate';

            $barClass = match ($color) {
                'primary' => 'bg-primary',
                'green' => 'bg-green-500',
                'orange' => 'bg-orange-500',
                'red' => 'bg-red-500',
                default => 'bg-slate-400',
            };

            $trackClass = match ($color) {
                'primary' => 'bg-primary/10',
                'green' => 'bg-green-100/70 dark:bg-green-900/20',
                'orange' => 'bg-orange-100/70 dark:bg-orange-900/20',
                'red' => 'bg-red-100/70 dark:bg-red-900/20',
                default => 'bg-slate-100 dark:bg-slate-800',
            };

            $items[] = [
                'id' => $task->id,
                'name' => $task->name,
                'project' => $task->phase?->project?->name ?? '—',
                'phase' => $task->phase?->name ?? '—',
                'statusLabel' => $statusEnum?->label() ?? '—',
                'statusClass' =>
                    $statusEnum?->badgeClass() ?? 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400',
                'hasDate' => $hasDate,
                'startLabel' => $start?->format('d/m/Y'),
                'endLabel' => $end?->format('d/m/Y'),
                'left' => $hasDate ? round(($offset / $totalDays) * 100, 4) : 0,
                'width' => $hasDate ? round(($duration / $totalDays) * 100, 4) : 0,
                'barClass' => $barClass,
                'trackClass' => $trackClass,
            ];
        }

        $gantt = [
            'hasTimeline' => true,
            'rangeStart' => $rangeStart->format('d/m/Y'),
            'rangeEnd' => $rangeEnd->format('d/m/Y'),
            'totalDays' => $totalDays,
            'months' => $months,
            'weekLines' => $weekLines,
            'items' => $items,
        ];
    }

    $minInnerPx = max(count($gantt['months'] ?? []) * 160, 560);
@endphp

<div class="flex flex-col gap-3">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">Tổng quan tiến độ theo thời gian</p>
            @if ($gantt['hasTimeline'])
                <p class="text-xs text-slate-500">
                    {{ $gantt['rangeStart'] }} → {{ $gantt['rangeEnd'] }}
                    <span class="font-semibold">({{ $gantt['totalDays'] }} ngày)</span>
                </p>
            @else
                <p class="text-xs text-slate-400">Chưa đủ dữ liệu ngày bắt đầu &amp; hạn kết thúc.</p>
            @endif
        </div>
        <div class="text-xs text-slate-400">
            Trang {{ $tasks->currentPage() }} / {{ $tasks->lastPage() }}
        </div>
    </div>

    <div class="flex flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
        style="height: 520px;" x-data="{
            busy: false,
            fromLeft(e) {
                if (this.busy) return;
                this.busy = true;
                this.$refs.rb.scrollTop = e.target.scrollTop;
                this.$nextTick(() => this.busy = false);
            },
            fromRight(e) {
                if (this.busy) return;
                this.busy = true;
                this.$refs.lb.scrollTop = e.target.scrollTop;
                this.$refs.rh.scrollLeft = e.target.scrollLeft;
                this.$nextTick(() => this.busy = false);
            },
        }">
        @if (!$gantt['hasTimeline'])
            <div class="flex flex-1 flex-col items-center justify-center gap-3 text-center">
                <span class="material-symbols-outlined text-5xl text-slate-300 dark:text-slate-700">view_timeline</span>
                <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">Không đủ dữ liệu để hiển thị Gantt
                </p>
                <p class="text-xs text-slate-400">Hãy cập nhật ngày bắt đầu và hạn chót cho công việc.</p>
            </div>
        @else
            <div class="flex min-h-0 flex-1 overflow-hidden">
                <div class="w-120 flex shrink-0 flex-col border-r border-slate-200 dark:border-slate-700">
                    <div
                        class="grid h-16 shrink-0 grid-cols-[minmax(0,1fr)_140px] items-center gap-4 border-b border-slate-200 bg-slate-50 pl-4 pr-6 text-xs font-bold uppercase tracking-wider text-slate-500 dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-400">
                        <span class="truncate">Công việc</span>
                        <span class="text-right">Trạng thái</span>
                    </div>

                    <div class="flex-1 overflow-y-auto overflow-x-hidden" x-ref="lb" @scroll="fromLeft($event)"
                        style="scrollbar-width: thin; scrollbar-gutter: stable;">
                        @foreach ($gantt['items'] as $item)
                            <div
                                class="grid h-16 grid-cols-[minmax(0,1fr)_140px] items-center gap-4 border-b border-slate-100 pl-4 pr-6 dark:border-slate-800">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-slate-700 dark:text-slate-200">
                                        {{ $item['name'] }}</p>
                                    <div class="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                                        <span class="truncate">{{ $item['project'] }}</span>
                                        <span
                                            class="text-2xs rounded-md bg-slate-100 px-2 py-0.5 font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                            {{ $item['phase'] }}
                                        </span>
                                    </div>
                                    <p class="text-[11px] text-slate-400">
                                        @if ($item['hasDate'])
                                            {{ $item['startLabel'] }} → {{ $item['endLabel'] }}
                                        @else
                                            Chưa đủ ngày
                                        @endif
                                    </p>
                                </div>
                                <div class="flex justify-end">
                                    <span
                                        class="{{ $item['statusClass'] }} inline-flex shrink-0 items-center justify-center whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-semibold">
                                        {{ $item['statusLabel'] !== '' ? $item['statusLabel'] : '—' }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                        <div class="h-3"></div>
                    </div>
                </div>

                <div class="flex min-w-0 flex-1 flex-col overflow-hidden">
                    <div class="h-16 shrink-0 border-b border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-800/60"
                        x-ref="rh" style="overflow-y: clip; overflow-x: hidden; scrollbar-width: none;">
                        <div class="relative h-full" style="min-width: {{ $minInnerPx }}px;">
                            @foreach ($gantt['months'] as $month)
                                <div class="absolute top-0 flex h-full items-center border-r border-slate-200/70 px-2 dark:border-slate-700/70"
                                    style="left: {{ $month['left'] }}%; width: {{ $month['width'] }}%;">
                                    <span class="truncate text-xs font-bold text-slate-700 dark:text-slate-200">
                                        {{ $month['label'] }}
                                        <span
                                            class="ml-0.5 font-normal text-slate-400 dark:text-slate-500">{{ $month['year'] }}</span>
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="flex-1 overflow-auto" x-ref="rb" @scroll="fromRight($event)"
                        style="scrollbar-width: thin;">
                        <div class="relative" style="min-width: {{ $minInnerPx }}px;">
                            @foreach ($gantt['weekLines'] as $line)
                                <div class="absolute inset-y-0 w-px bg-slate-100 dark:bg-slate-800"
                                    style="left: {{ $line }}%;"></div>
                            @endforeach

                            @foreach ($gantt['items'] as $item)
                                <div class="relative h-16 border-b border-slate-100 px-4 dark:border-slate-800">
                                    <div class="absolute inset-y-0 left-0 right-0 flex items-center">
                                        @if ($item['hasDate'])
                                            <div class="{{ $item['trackClass'] }} relative h-2.5 w-full rounded-full">
                                                <div class="{{ $item['barClass'] }} absolute h-2.5 rounded-full"
                                                    style="left: {{ $item['left'] }}%; width: {{ $item['width'] }}%;">
                                                </div>
                                            </div>
                                        @else
                                            <span class="text-xs italic text-slate-400">Chưa có ngày để hiển thị</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                            <div class="h-3"></div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>

    @if ($tasks instanceof LengthAwarePaginator && $tasks->total() > 0)
        <div class="mt-1 flex items-center justify-between px-2">
            <div class="text-sm text-slate-500 dark:text-slate-400">
                Hiển thị
                <span
                    class="font-semibold text-slate-600 dark:text-slate-100">{{ $tasks->firstItem() }}–{{ $tasks->lastItem() }}</span>
                trên
                <span class="font-semibold text-slate-600 dark:text-slate-100">{{ $tasks->total() }}</span>
                công việc
            </div>
            {{ $tasks->links(data: ['scrollTo' => false]) }}
        </div>
    @endif
</div>
