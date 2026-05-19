@props(['projects'])

@php
    use App\Enums\ProjectStatus;
    use Illuminate\Contracts\Pagination\LengthAwarePaginator;
    use Illuminate\Support\Carbon;

    $projectCollection = $projects instanceof LengthAwarePaginator ? $projects->getCollection() : collect($projects);

    $datedProjects = $projectCollection->filter(function ($project) {
        if ($project->start_date === null || $project->end_date === null) {
            return false;
        }
        $s = $project->start_date instanceof Carbon
            ? $project->start_date
            : Carbon::parse($project->start_date);
        $e = $project->end_date instanceof Carbon
            ? $project->end_date
            : Carbon::parse($project->end_date);
        // Loai bo project co ngay khong hop le (truoc nam 2000)
        if ($s->year < 2000 || $e->year < 2000) {
            return false;
        }
        return true;
    });

    if ($datedProjects->isEmpty()) {
        $gantt = [
            'hasTimeline' => false,
            'items' => [],
        ];
    } else {
        $starts = $datedProjects->map(function ($project) {
            return $project->start_date instanceof Carbon
                ? $project->start_date->copy()->startOfDay()
                : Carbon::parse($project->start_date)->startOfDay();
        });

        $ends = $datedProjects->map(function ($project) {
            return $project->end_date instanceof Carbon
                ? $project->end_date->copy()->startOfDay()
                : Carbon::parse($project->end_date)->startOfDay();
        });

        $rangeStart = $starts->min();
        $rangeEnd = $ends->max();
        $totalDays = max(1, $rangeStart->diffInDays($rangeEnd) + 1);

        // Safety cap: max 3650 ngay (~10 nam) de tranh treo UI tu du lieu xau
        if ($totalDays > 3650) {
            $rangeEnd = $rangeStart->copy()->addDays(3650 - 1);
            $totalDays = 3650;
        }

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
        foreach ($projectCollection as $project) {
            $statusEnum = $project->status instanceof ProjectStatus
                ? $project->status
                : ProjectStatus::tryFrom($project->status ?? '');

            $start =
                $project->start_date instanceof Carbon
                    ? $project->start_date->copy()->startOfDay()
                    : ($project->start_date
                        ? Carbon::parse($project->start_date)->startOfDay()
                        : null);

            $end =
                $project->end_date instanceof Carbon
                    ? $project->end_date->copy()->startOfDay()
                    : ($project->end_date
                        ? Carbon::parse($project->end_date)->startOfDay()
                        : null);

            $hasDate = $start !== null && $end !== null
                && $start->year >= 2000 && $end->year >= 2000;
            if ($hasDate && $end->lt($start)) {
                $end = $start->copy();
            }

            $offset = $hasDate ? $rangeStart->diffInDays($start) : 0;
            $duration = $hasDate ? max(1, $start->diffInDays($end) + 1) : 0;

            // Color mapping for project status
            $statusValue = $statusEnum?->value ?? '';
            $barClass = match ($statusValue) {
                'running' => 'bg-blue-500',
                'completed' => 'bg-green-500',
                'paused' => 'bg-amber-500',
                'cancelled' => 'bg-red-500',
                'overdue' => 'bg-orange-500',
                default => 'bg-slate-400',
            };

            $trackClass = match ($statusValue) {
                'running' => 'bg-blue-100/70 dark:bg-blue-900/20',
                'completed' => 'bg-green-100/70 dark:bg-green-900/20',
                'paused' => 'bg-amber-100/70 dark:bg-amber-900/20',
                'cancelled' => 'bg-red-100/70 dark:bg-red-900/20',
                'overdue' => 'bg-orange-100/70 dark:bg-orange-900/20',
                default => 'bg-slate-100 dark:bg-slate-800',
            };

            $progress = (int) ($project->progress ?? 0);

            $items[] = [
                'id' => $project->id,
                'name' => $project->name,
                'type' => $project->projectType?->label ?? ($project->type ?? '—'),
                'manager' => $project->leaders?->first()?->name ?? '—',
                'progress' => $progress,
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
                <p class="text-xs text-slate-400">Chưa đủ dữ liệu ngày bắt đầu &amp; ngày kết thúc.</p>
            @endif
        </div>
        @if ($projects instanceof LengthAwarePaginator)
            <div class="text-xs text-slate-400">
                Trang {{ $projects->currentPage() }} / {{ $projects->lastPage() }}
            </div>
        @endif
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
                <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">Không đủ dữ liệu để hiển thị Gantt</p>
                <p class="text-xs text-slate-400">Hãy cập nhật ngày bắt đầu và hạn chót cho các dự án.</p>
            </div>
        @else
            <div class="flex min-h-0 flex-1 overflow-hidden">
                {{-- Left panel: project list --}}
                <div class="w-96 flex shrink-0 flex-col border-r border-slate-200 dark:border-slate-700">
                    <div
                        class="grid h-16 shrink-0 grid-cols-[minmax(0,1fr)_auto_80px] items-center gap-3 border-b border-slate-200 bg-slate-50 pl-4 pr-6 text-xs font-bold uppercase tracking-wider text-slate-500 dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-400">
                        <span class="truncate">Dự án</span>
                        <span class="text-right">QL</span>
                        <span class="text-right">Tiến độ</span>
                    </div>

                    <div class="flex-1 overflow-y-auto overflow-x-hidden" x-ref="lb" @scroll="fromLeft($event)"
                        style="scrollbar-width: thin; scrollbar-gutter: stable;">
                        @foreach ($gantt['items'] as $item)
                            <a href="{{ route('projects.phases.index', $item['id']) }}" class="block">
                                <div
                                    class="grid h-16 grid-cols-[minmax(0,1fr)_auto_80px] items-center gap-3 border-b border-slate-100 pl-4 pr-6 transition-colors hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/40">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-slate-700 dark:text-slate-200">
                                            {{ $item['name'] }}
                                        </p>
                                        <div class="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                                            <span class="truncate">{{ $item['type'] }}</span>
                                            <span
                                                class="{{ $item['statusClass'] }} inline-flex shrink-0 items-center justify-center whitespace-nowrap rounded-full px-2 py-0.5 text-2xs font-semibold">
                                                {{ $item['statusLabel'] }}
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
                                    <div class="text-right">
                                        <span class="text-xs text-slate-500">{{ $item['manager'] }}</span>
                                    </div>
                                    <div class="flex items-center justify-end gap-2">
                                        <div class="h-1.5 w-12 flex-1 rounded-full bg-slate-200 dark:bg-slate-700">
                                            <div class="h-1.5 rounded-full transition-all {{ $item['progress'] >= 100 ? 'bg-green-500' : ($item['progress'] >= 60 ? 'bg-primary' : ($item['progress'] >= 30 ? 'bg-amber-400' : 'bg-slate-400')) }}"
                                                style="width: {{ $item['progress'] }}%">
                                            </div>
                                        </div>
                                        <span class="w-7 text-right text-xs font-bold text-slate-600 dark:text-slate-100">
                                            {{ $item['progress'] }}%
                                        </span>
                                    </div>
                                </div>
                            </a>
                        @endforeach
                        <div class="h-3"></div>
                    </div>
                </div>

                {{-- Right panel: timeline --}}
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

    @if ($projects instanceof LengthAwarePaginator && $projects->total() > 0)
        <div class="mt-1 flex items-center justify-between px-2">
            <div class="text-sm text-slate-500 dark:text-slate-400">
                Hiển thị
                <span
                    class="font-semibold text-slate-600 dark:text-slate-100">{{ $projects->firstItem() }}–{{ $projects->lastItem() }}</span>
                trên
                <span class="font-semibold text-slate-600 dark:text-slate-100">{{ $projects->total() }}</span>
                dự án
            </div>
            {{ $projects->links(data: ['scrollTo' => false]) }}
        </div>
    @endif
</div>
