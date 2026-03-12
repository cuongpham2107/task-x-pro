@php
/**
 * Gantt Chart Partial
 *
 * Fixes applied in this version:
 *
 *  1. Badge legibility: badge ALWAYS uses white text + semi-dark opaque background
 *     so it remains readable whether the solid progress fill covers it or not.
 *     Previous bug: badge colour = fill colour → invisible at high progress (e.g. 96%).
 *
 *  2. Day markers: removed `transform:translateX(-50%)` which, combined with
 *     `overflow-y:hidden` on the header container, caused the tick lines to be clipped.
 *     Markers now render from their left edge; month-boundary markers use a bold style.
 *     The header container is now `overflow-hidden` (not overflow-y:hidden separately).
 */

$g = $ganttData ?? ['hasTimeline' => false, 'items' => []];

// ── Per-status colour palette ────────────────────────────────────────────────
// 'fill'  → solid bar progress colour
// 'track' → translucent track (full duration)
// 'dot'   → milestone dots
// Badge always: white text on rgba(0,0,0,0.28) — readable on ANY colour fill
$palette = [
    'completed' => [
        'fill'   => '#16a34a',              // green-600
        'track'  => 'rgba(22,163,74,0.13)',
        'border' => 'rgba(22,163,74,0.32)',
        'dot'    => '#15803d',              // green-700
        'shadow' => '0 1px 3px rgba(0,0,0,0.55)',
    ],
    'active' => [
        'fill'   => '#2563eb',              // blue-600
        'track'  => 'rgba(37,99,235,0.12)',
        'border' => 'rgba(37,99,235,0.32)',
        'dot'    => '#1d4ed8',              // blue-700
        'shadow' => '0 1px 3px rgba(0,0,0,0.55)',
    ],
    'pending' => [
        'fill'   => '#64748b',              // slate-500
        'track'  => 'rgba(100,116,139,0.11)',
        'border' => 'rgba(100,116,139,0.28)',
        'dot'    => '#475569',              // slate-600
        'shadow' => '0 1px 3px rgba(0,0,0,0.50)',
    ],
];

// Badge style: always legible regardless of what colour the bar fill is
// White text + semi-opaque dark bg works on green, blue, slate, and the track.
$badgeBg   = 'rgba(0,0,0,0.28)';
$badgeText = '#ffffff';

// ── Today offset ─────────────────────────────────────────────────────────────
$todayOffset = null;
if ($g['hasTimeline'] ?? false) {
    $today    = \Illuminate\Support\Carbon::now();
    $pStart   = \Illuminate\Support\Carbon::createFromFormat('d/m/Y', $g['projectStart']);
    $pEnd     = \Illuminate\Support\Carbon::createFromFormat('d/m/Y', $g['projectEnd']);
    $tDays    = max(1, $pStart->diffInDays($pEnd) + 1);
    $diff     = $pStart->diffInDays($today, false);
    if ($diff >= 0 && $diff <= $tDays) {
        $todayOffset = round(($diff / $tDays) * 100, 4);
    }
}

$minInnerPx = max(count($g['months'] ?? []) * 140, 520);
@endphp

<div
    class="flex flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
    style="height: 560px;"
    x-data="{
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
            this.$refs.lb.scrollTop  = e.target.scrollTop;
            this.$refs.rh.scrollLeft = e.target.scrollLeft;
            this.$nextTick(() => this.busy = false);
        }
    }"
>

    {{-- ── Empty state ─────────────────────────────────────────────────────── --}}
    @if (! ($g['hasTimeline'] ?? false))
        <div class="flex flex-1 flex-col items-center justify-center gap-3 text-center">
            <span class="material-symbols-outlined text-5xl text-slate-300 dark:text-slate-700">view_timeline</span>
            <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">Không đủ dữ liệu để hiển thị Gantt</p>
            <p class="text-xs text-slate-400">Hãy thêm ngày bắt đầu &amp; kết thúc cho ít nhất một giai đoạn.</p>
        </div>

    @else
        <div class="flex min-h-0 flex-1 overflow-hidden">

            {{-- ════════════════════ LEFT PANEL ════════════════════ --}}
            <div class="flex w-72 shrink-0 flex-col border-r border-slate-200 dark:border-slate-700 lg:w-80">

                {{-- Left header (h-20 matches right header height) --}}
                <div class="flex h-20 shrink-0 flex-col justify-center gap-1 border-b border-slate-200 bg-slate-50 px-4 dark:border-slate-700 dark:bg-slate-800/60">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Giai đoạn</span>
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Trọng số</span>
                    </div>
                    <p class="text-2xs text-slate-400">
                        {{ $g['projectStart'] }} → {{ $g['projectEnd'] }}
                        &nbsp;·&nbsp;<span class="font-semibold">{{ $g['totalDays'] }} ngày</span>
                    </p>
                </div>

                {{-- Left rows --}}
                <div
                    class="flex-1 overflow-y-auto overflow-x-hidden"
                    x-ref="lb"
                    @scroll="fromLeft($event)"
                    style="scrollbar-width: thin;"
                >
                    @foreach ($phases as $idx => $phase)
                        @php
                            $rowBg = match ($phase->status) {
                                'active'    => 'bg-blue-50/60 dark:bg-blue-900/10',
                                'completed' => 'bg-green-50/50 dark:bg-green-900/10',
                                default     => '',
                            };
                            $nameStyle = match ($phase->status) {
                                'active'    => 'color:#1d4ed8',
                                'completed' => 'color:#15803d',
                                default     => '',
                            };
                        @endphp
                        <a
                            href="{{ route('projects.phases.tasks.index', [$project, $phase]) }}"
                            class="block"
                        >
                            <div class="flex h-16 items-center justify-between border-b border-slate-100 px-4 transition-colors hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/40 {{ $rowBg }}">
                                <div class="flex min-w-0 items-center gap-2.5">
                                    <span class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-2xs font-bold text-slate-500 ring-1 ring-slate-200 dark:text-slate-400 dark:ring-slate-700">
                                        {{ $idx + 1 }}
                                    </span>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-slate-700 dark:text-slate-200" style="{{ $nameStyle }}">
                                            {{ $phase->name }}
                                        </p>
                                        @if ($phase->start_date && $phase->end_date)
                                            <p class="text-[11px] text-slate-400">
                                                {{ $phase->start_date->format('d/m') }} → {{ $phase->end_date->format('d/m/Y') }}
                                            </p>
                                        @else
                                            <p class="text-[11px] italic text-slate-300 dark:text-slate-600">Chưa có ngày</p>
                                        @endif
                                    </div>
                                </div>
                                <span class="ml-2 shrink-0 rounded-md bg-slate-100 px-2 py-0.5 text-xs font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                    {{ number_format($phase->weight, 0) }}%
                                </span>
                            </div>
                        </a>
                    @endforeach
                    <div class="h-4"></div>
                </div>
            </div>

            {{-- ════════════════════ RIGHT PANEL ════════════════════ --}}
            <div class="flex min-w-0 flex-1 flex-col overflow-hidden">

                {{-- ────────── Timeline header ──────────
                     h-20 split into two rows:
                       Row 1 (top, h-10):     Month + Year columns
                       Divider (h-px):        line separator
                       Row 2 (bottom, h-[39px]): Day-of-month markers

                     FIX: use `overflow-hidden` (not overflow-y:hidden + overflow-x:auto
                     separately) to avoid clip bugs. Horizontal scroll is handled by
                     the inner min-width div; scrollLeft is synced from the bars body.
                ──────────────────────────────────────── --}}
                <div
                    class="h-20 shrink-0 border-b border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-800/60"
                    x-ref="rh"
                    style="overflow-y: clip; overflow-x: hidden; scrollbar-width: none;"
                >
                    <div class="relative h-full" style="min-width: {{ $minInnerPx }}px;">

                        {{-- Row 1: Month columns (top half) --}}
                        @foreach ($g['months'] as $month)
                            <div
                                class="absolute top-0 flex h-10 items-center border-r border-slate-200/70 px-2 dark:border-slate-700/70"
                                style="left: {{ $month['left'] }}%; width: {{ $month['width'] }}%;"
                            >
                                <span class="truncate text-xs font-bold text-slate-700 dark:text-slate-200">
                                    {{ $month['label'] }}
                                    <span class="ml-0.5 font-normal text-slate-400 dark:text-slate-500">{{ $month['year'] }}</span>
                                </span>
                            </div>
                        @endforeach

                        {{-- Divider between rows --}}
                        <div class="absolute left-0 right-0 top-10 h-px bg-slate-200 dark:bg-slate-700"></div>

                        {{-- Row 2: Day markers (bottom half)
                             FIX: no translateX(-50%) — that caused clipping with overflow-hidden.
                             Each marker is positioned by its left edge. The tick line grows
                             downward from the number, fully inside the container bounds.
                        --}}
                        @foreach (($g['dayMarkers'] ?? []) as $dm)
                            {{--
                                isMonthStart: left-align (translateX(0)) so "01" lines up with
                                the month label text above which is also left-aligned in its column.
                                Other days: center (translateX(-50%)) on their position tick.
                            --}}
                            <div
                                class="absolute flex flex-col items-center"
                                style="left: {{ $dm['left'] }}%; top: 55px; transform: {{ $dm['isMonthStart'] ? 'translateX(40%)' : 'translateX(-50%)' }};"
                            >
                                @if ($dm['isMonthStart'])
                                    <span class="text-2xs font-bold leading-none text-slate-600 dark:text-slate-300">{{ $dm['label'] }}</span>
                                    <span class="mt-0.5 block h-2.5 w-px bg-slate-400 dark:bg-slate-500"></span>
                                @else
                                    <span class="text-[9px] leading-none text-slate-400 dark:text-slate-600">{{ $dm['label'] }}</span>
                                    <span class="mt-0.5 block h-1.5 w-px bg-slate-200 dark:bg-slate-700"></span>
                                @endif
                            </div>
                        @endforeach

                    </div>
                </div>

                {{-- ────────── Bars body ────────── --}}
                <div
                    class="relative flex-1 overflow-auto"
                    x-ref="rb"
                    @scroll="fromRight($event)"
                    style="scrollbar-width: thin;"
                >
                    <div class="relative" style="min-width: {{ $minInnerPx }}px; min-height: 100%;">

                        {{-- Week grid lines --}}
                        @foreach (($g['weekLines'] ?? []) as $line)
                            <div class="pointer-events-none absolute inset-y-0 w-px bg-slate-100 dark:bg-slate-800" style="left: {{ $line }}%;"></div>
                        @endforeach

                        {{-- Month separator lines --}}
                        @foreach ($g['months'] as $month)
                            @if ($month['left'] > 0)
                                <div class="pointer-events-none absolute inset-y-0 w-px bg-slate-200/80 dark:bg-slate-700/80" style="left: {{ $month['left'] + 0.8 }}%;"></div>
                            @endif
                        @endforeach

                        {{-- Today indicator --}}
                        @if ($todayOffset !== null)
                            <div class="pointer-events-none absolute inset-y-0 z-20" style="left: {{ $todayOffset }}%;">
                                <div class="absolute inset-y-0 w-0.5 bg-red-400" style="opacity:.75;"></div>
                                <div class="absolute top-0 -translate-x-1/2">
                                    <span class="inline-flex items-center gap-1 whitespace-nowrap rounded-b-md bg-red-500 px-1.5 py-0.5 text-[9px] font-bold text-white shadow">
                                        <span class="inline-block h-1.5 w-1.5 rounded-full bg-white opacity-80"></span>Hôm nay
                                    </span>
                                </div>
                            </div>
                        @endif

                        {{-- ── Phase bar rows ── --}}
                        @foreach ($g['items'] as $item)
                            @php
                                $c        = $palette[$item['color']] ?? $palette['pending'];
                                $progress = min(100, max(0, (int) ($item['progress'] ?? 0)));
                                $barLeft  = "calc({$item['left']}% + 10px)";
                                $barWidth = "calc({$item['width']}% - 20px)";
                            @endphp

                            <div class="relative flex h-16 items-center border-b border-slate-100 dark:border-slate-800">
                                @if ($item['hasDate'])

                                    {{-- Bar track (full duration, translucent) --}}
                                    <div
                                        class="absolute flex h-9 items-center overflow-hidden rounded-lg"
                                        style="
                                            left: {{ $barLeft }};
                                            width: {{ $barWidth }};
                                            background-color: {{ $c['track'] }};
                                            box-shadow: inset 0 0 0 1px {{ $c['border'] }};
                                        "
                                    >
                                        {{-- Progress fill (solid, underneath label) --}}
                                        <div
                                            class="absolute left-0 top-0 h-full rounded-lg"
                                            style="width: {{ $progress }}%; background-color: {{ $c['fill'] }};"
                                        ></div>

                                        {{-- Label row (z-10: always above fill) --}}
                                        <div class="relative z-10 flex w-full items-center justify-between gap-2 px-2.5">

                                            {{-- Phase name: white + shadow, readable on any fill colour --}}
                                            <span
                                                class="min-w-0 truncate text-xs font-semibold"
                                                style="color:#fff; text-shadow:{{ $c['shadow'] }};"
                                            >{{ $item['name'] }}</span>

                                            {{-- Progress badge
                                                 FIX: white text + semi-opaque dark bg
                                                 → always readable whether fill covers this area or not
                                                 Previous bug: badge text = fill colour → invisible at high %
                                            --}}
                                            <span
                                                class="shrink-0 rounded px-1.5 py-0.5 text-2xs font-bold"
                                                style="
                                                    background-color: {{ $badgeBg }};
                                                    color: {{ $badgeText }};
                                                "
                                            >{{ $progress }}%</span>
                                        </div>
                                    </div>

                                    {{-- Start milestone dot --}}
                                    <div
                                        class="absolute z-10 h-3 w-3 rounded-full ring-2 ring-white dark:ring-slate-900"
                                        style="
                                            left: {{ $item['left'] }}%;
                                            top: 50%;
                                            transform: translate(-50%, -50%);
                                            background-color: {{ $c['dot'] }};
                                        "
                                    ></div>

                                    {{-- End milestone dot --}}
                                    <div
                                        class="absolute z-10 h-3 w-3 rounded-full ring-2 ring-white dark:ring-slate-900"
                                        style="
                                            left: calc({{ $item['left'] }}% + {{ $item['width'] }}%);
                                            top: 50%;
                                            transform: translate(-50%, -50%);
                                            background-color: {{ $c['dot'] }};
                                        "
                                    ></div>

                                @else
                                    <div class="absolute left-4 flex items-center gap-2 text-xs italic text-slate-300 dark:text-slate-600">
                                        <span class="material-symbols-outlined text-base">event_busy</span>
                                        Chưa thiết lập ngày cho giai đoạn này
                                    </div>
                                @endif
                            </div>
                        @endforeach

                        <div class="h-4"></div>
                    </div>
                </div>{{-- end bars body --}}

            </div>{{-- end right panel --}}
        </div>{{-- end two-column --}}

        {{-- ── Footer legend ── --}}
        <div class="flex h-11 shrink-0 items-center justify-between border-t border-slate-200 bg-white px-6 dark:border-slate-700 dark:bg-slate-900">
            <div class="flex items-center gap-5">
                <div class="flex items-center gap-1.5">
                    <span class="inline-block h-2.5 w-3.5 rounded-sm" style="background:#16a34a;"></span>
                    <span class="text-xs text-slate-500">Hoàn thành</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="inline-block h-2.5 w-3.5 rounded-sm" style="background:#2563eb;"></span>
                    <span class="text-xs text-slate-500">Đang thực hiện</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="inline-block h-2.5 w-3.5 rounded-sm" style="background:#64748b;"></span>
                    <span class="text-xs text-slate-500">Kế hoạch</span>
                </div>
                @if ($todayOffset !== null)
                    <div class="flex items-center gap-1.5">
                        <span class="inline-block h-2.5 w-0.5 rounded-sm bg-red-400"></span>
                        <span class="text-xs text-slate-500">Hôm nay</span>
                    </div>
                @endif
            </div>
            <div class="flex items-center gap-2.5 text-[11px] text-slate-400">
                <span><span class="font-semibold text-slate-500 dark:text-slate-400">Bắt đầu:</span> {{ $g['projectStart'] }}</span>
                <span class="text-slate-200 dark:text-slate-700">·</span>
                <span><span class="font-semibold text-slate-500 dark:text-slate-400">Kết thúc:</span> {{ $g['projectEnd'] }}</span>
                <span class="text-slate-200 dark:text-slate-700">·</span>
                <span><span class="font-semibold text-slate-500 dark:text-slate-400">Tổng:</span> {{ $g['totalDays'] }} ngày</span>
            </div>
        </div>
    @endif

</div>