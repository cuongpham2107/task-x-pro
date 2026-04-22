@props([
    'metrics' => [], // ['Label' => value, ...]
    'finalScore' => 0,
    'size' => 'size-72',
])

@php
    // Default metrics if none provided
    if (empty($metrics)) {
        $metrics = [
            'Đúng hạn' => 0,
            'SLA' => 0,
            'Đánh giá' => 0,
        ];
    }

    $maxR = 38; // Slightly smaller to prevent clipping
    $cx = 50;
    $cy = 50;

    $labels = array_keys($metrics);
    $values = array_values($metrics);
    $count = count($labels);

    $points = [];
    $axes = [];

    for ($i = 0; $i < $count; $i++) {
        // Calculate angle based on number of metrics
        // Starting from -90deg (top)
        $angle = -90 + $i * (360 / $count);
        $rad = deg2rad($angle);

        $val = (float) $values[$i];
        $r = ($val / 100) * $maxR;

        $points[] = [
            'x' => round($cx + $r * cos($rad), 2),
            'y' => round($cy + $r * sin($rad), 2),
        ];

        $axes[] = [
            'ax' => round($cx + $maxR * cos($rad), 2),
            'ay' => round($cy + $maxR * sin($rad), 2),
            'label' => $labels[$i],
            'tx' => round($cx + ($maxR + 10) * cos($rad), 2),
            'ty' => round($cy + ($maxR + 10) * sin($rad), 2),
        ];
    }

    $polyPath = collect($points)->map(fn($p) => $p['x'] . ' ' . $p['y'])->join(' L ');
    $polyPath = 'M ' . $polyPath . ' Z';
@endphp

<div {{ $attributes->merge(['class' => 'relative flex flex-col items-center justify-center']) }}>
    <div class="{{ $size }} relative">
        <svg class="size-full overflow-visible" viewBox="0 0 100 100">
            <!-- Grid levels (Polygons instead of circles for better aesthetic) -->
            @foreach ([0.25, 0.5, 0.75, 1] as $level)
                @php
                    $gridPoints = [];
                    foreach ($axes as $index => $axis) {
                        $rad = deg2rad(-90 + $index * (360 / $count));
                        $r = $level * $maxR;
                        $gridPoints[] = round($cx + $r * cos($rad), 2) . ' ' . round($cy + $r * sin($rad), 2);
                    }
                    $gridPath = 'M ' . implode(' L ', $gridPoints) . ' Z';
                @endphp
                <path d="{{ $gridPath }}" fill="none" stroke="currentColor"
                    class="text-slate-100 dark:text-slate-800" stroke-width="0.5" />
            @endforeach

            <!-- Axis lines -->
            @foreach ($axes as $axis)
                <line x1="50" y1="50" x2="{{ $axis['ax'] }}" y2="{{ $axis['ay'] }}" stroke="currentColor"
                    class="text-slate-100 dark:text-slate-800" stroke-width="0.5" />
            @endforeach

            <!-- Data Area -->
            <path d="{{ $polyPath }}" fill="url(#radarGradient)" class="opacity-40" />

            <!-- Path Border -->
            <path d="{{ $polyPath }}" fill="none" stroke="currentColor" class="text-primary" stroke-width="2"
                stroke-linejoin="round" />

            <!-- Data Dots -->
            @foreach ($points as $p)
                <circle cx="{{ $p['x'] }}" cy="{{ $p['y'] }}" r="1.5" class="fill-primary" />
            @endforeach

            <!-- Labels -->
            @foreach ($axes as $axis)
                <text x="{{ $axis['tx'] }}" y="{{ $axis['ty'] }}" text-anchor="middle" dominant-baseline="middle"
                    class="fill-slate-400 text-[3.5px] font-black uppercase dark:fill-slate-500">
                    {{ $axis['label'] }}
                </text>
            @endforeach

            <defs>
                <radialGradient id="radarGradient">
                    <stop offset="0%" stop-color="var(--color-primary, #ec5b13)" stop-opacity="0.6" />
                    <stop offset="100%" stop-color="var(--color-primary, #ec5b13)" stop-opacity="0.1" />
                </radialGradient>
            </defs>
        </svg>

        <!-- Center Value -->
        <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
            <span class="text-3xl font-black text-slate-700 dark:text-white">{{ number_format($finalScore, 0) }}</span>
            <span class="text-primary/60 text-[9px] font-black uppercase tracking-widest">Final</span>
        </div>
    </div>
</div>
