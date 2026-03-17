@props(['items' => []])

{{--
Nhận vào mảng $items, mỗi phần tử gồm:
- label : string (bắt buộc) — văn bản hiển thị
- url : string (tùy chọn) — nếu có thì render <a>, không thì render <span>
        - icon : string (tùy chọn) — Material Symbol icon name
        --}}

<nav class="mb-4 flex" aria-label="Breadcrumb">
    <ol class="inline-flex items-center space-x-1 md:space-x-2 rtl:space-x-reverse">
        @foreach ($items as $index => $item)
            @php
                $isLast = $loop->last;
                $hasUrl = !empty($item['url']);
            @endphp

            @if ($index === 0)
                <li class="inline-flex items-center">
                    <a href="{{ $item['url'] ?? '#' }}" wire:navigate
                        class="hover:text-primary inline-flex items-center text-sm font-medium text-slate-600 transition-colors dark:text-slate-400">
                        @if (!empty($item['icon']))
                            {{-- We can use a generic Home SVG here or map the Material Symbol if needed --}}
                            <svg class="me-1.5 h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24"
                                height="24" fill="none" viewBox="0 0 24 24">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"
                                    stroke-width="2"
                                    d="m4 12 8-8 8 8M6 10.5V19a1 1 0 0 0 1 1h3v-3a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v3h3a1 1 0 0 0 1-1v-8.5" />
                            </svg>
                        @endif
                        {{ $item['label'] }}
                    </a>
                </li>
            @else
                <li @if ($isLast) aria-current="page" @endif>
                    <div class="flex items-center space-x-1.5">
                        <svg class="h-3.5 w-3.5 text-slate-400 rtl:rotate-180" aria-hidden="true"
                            xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none"
                            viewBox="0 0 24 24">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="m9 5 7 7-7 7" />
                        </svg>

                        @if ($hasUrl && !$isLast)
                            <a href="{{ $item['url'] }}" wire:navigate
                                class="hover:text-primary inline-flex items-center text-sm font-medium text-slate-600 transition-colors dark:text-slate-400">
                                @if (!empty($item['icon']))
                                    <span class="material-symbols-outlined me-1.5"
                                        style="font-size:16px;">{{ $item['icon'] }}</span>
                                @endif
                                {{ $item['label'] }}
                            </a>
                        @else
                            <span
                                class="inline-flex items-center text-sm font-medium text-slate-400 dark:text-slate-500">
                                @if (!empty($item['icon']))
                                    <span class="material-symbols-outlined me-1.5"
                                        style="font-size:16px;">{{ $item['icon'] }}</span>
                                @endif
                                {{ $item['label'] }}
                            </span>
                        @endif
                    </div>
                </li>
            @endif
        @endforeach
    </ol>
</nav>
