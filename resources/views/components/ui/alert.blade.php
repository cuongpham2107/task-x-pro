{{--
    <x-ui.alert />                               → auto-reads session flash (success, error, warning, info)
    <x-ui.alert type="success" message="Done!" /> → manual inline alert (static, not dismissible by default)
    <x-ui.alert type="error" :dismissible="true"> Custom slot content </x-ui.alert>

    Variants: success | error | warning | info
    Modes:
      - Toast (default for session flash): fixed bottom-right, auto-dismiss after 4s
      - Inline (when used with message prop or slot): static in-flow banner
--}}

@props([
    'type'        => null,   // success | error | warning | info
    'message'     => null,   // text message
    'dismissible' => true,   // show close button
    'toast'       => false,  // force toast mode (auto for session flash)
    'duration'    => 4000,   // auto-dismiss ms (0 = no auto-dismiss)
])

@php
    // ─── Auto-detect from session flash ─────────────────────
    $flashType    = null;
    $flashMessage = null;

    foreach (['success', 'error', 'warning', 'info'] as $key) {
        if (session()->has($key)) {
            $flashType    = $key;
            $flashMessage = session($key);
            break;
        }
    }

    $isFlash      = !is_null($flashType);
    $resolvedType = $type ?? $flashType ?? 'info';
    $resolvedMsg  = $message ?? $flashMessage;
    $isToast      = $toast || $isFlash;

    // ─── Variant styles ─────────────────────────────────────
    $config = match ($resolvedType) {
        'success' => [
            'icon'    => 'check_circle',
            'bg'      => 'bg-green-50 dark:bg-green-900/20',
            'border'  => 'border-green-200 dark:border-green-800',
            'text'    => 'text-green-800 dark:text-green-300',
            'iconClr' => 'text-green-500 dark:text-green-400',
            'toastBg' => 'bg-green-600',
        ],
        'error' => [
            'icon'    => 'error',
            'bg'      => 'bg-red-50 dark:bg-red-900/20',
            'border'  => 'border-red-200 dark:border-red-800',
            'text'    => 'text-red-800 dark:text-red-300',
            'iconClr' => 'text-red-500 dark:text-red-400',
            'toastBg' => 'bg-red-600',
        ],
        'warning' => [
            'icon'    => 'warning',
            'bg'      => 'bg-amber-50 dark:bg-amber-900/20',
            'border'  => 'border-amber-200 dark:border-amber-800',
            'text'    => 'text-amber-800 dark:text-amber-300',
            'iconClr' => 'text-amber-500 dark:text-amber-400',
            'toastBg' => 'bg-amber-600',
        ],
        default => [
            'icon'    => 'info',
            'bg'      => 'bg-blue-50 dark:bg-blue-900/20',
            'border'  => 'border-blue-200 dark:border-blue-800',
            'text'    => 'text-blue-800 dark:text-blue-300',
            'iconClr' => 'text-blue-500 dark:text-blue-400',
            'toastBg' => 'bg-blue-600',
        ],
    };
@endphp

@if ($isToast && $resolvedMsg)
    {{-- ════════════════ TOAST MODE ════════════════ --}}
    <div
        x-data="{ show: true }"
        x-init="@if($duration > 0) setTimeout(() => show = false, {{ $duration }}) @endif"
        x-show="show"
        x-cloak
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 translate-y-4 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-4 scale-95"
        class="fixed bottom-6 right-6 z-100 max-w-sm w-full"
    >
        <div class="flex items-start gap-3 px-5 py-4 rounded-xl shadow-2xl border {{ $config['bg'] }} {{ $config['border'] }} {{ $config['text'] }} backdrop-blur-sm">
            <span class="material-symbols-outlined text-xl {{ $config['iconClr'] }} shrink-0 mt-0.5">{{ $config['icon'] }}</span>
            <p class="text-sm font-medium flex-1">{{ $resolvedMsg }}</p>
            @if ($dismissible)
                <button @click="show = false" class="shrink-0 opacity-60 hover:opacity-100 transition-opacity">
                    <span class="material-symbols-outlined text-base">close</span>
                </button>
            @endif
        </div>
    </div>

@elseif (!$isToast && ($resolvedMsg || $slot->isNotEmpty()))
    {{-- ════════════════ INLINE MODE ════════════════ --}}
    <div
        @if ($dismissible) x-data="{ show: true }" x-show="show" x-transition @endif
        {{ $attributes->class([
            'flex items-start gap-3 px-4 py-3 rounded-xl border text-sm',
            $config['bg'],
            $config['border'],
            $config['text'],
        ]) }}
    >
        <span class="material-symbols-outlined text-lg {{ $config['iconClr'] }} shrink-0 mt-0.5">{{ $config['icon'] }}</span>
        <div class="flex-1 font-medium">
            @if ($slot->isNotEmpty())
                {{ $slot }}
            @else
                {{ $resolvedMsg }}
            @endif
        </div>
        @if ($dismissible)
            <button @click="show = false" class="shrink-0 opacity-60 hover:opacity-100 transition-opacity">
                <span class="material-symbols-outlined text-base">close</span>
            </button>
        @endif
    </div>
@endif
