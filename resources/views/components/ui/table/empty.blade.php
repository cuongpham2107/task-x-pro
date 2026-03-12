@props([
    'colspan'   => 1,
    'icon'      => 'inbox',     // Material Symbol icon name
    'message'   => 'Chưa có dữ liệu.',
    'actionLabel' => null,      // text của link CTA
    'actionHref'  => null,      // href của link CTA
])

<tr>
    <td colspan="{{ $colspan }}" class="px-6 py-16 text-center text-slate-400 dark:text-slate-500">
        <span class="material-symbols-outlined text-4xl mb-2 block">{{ $icon }}</span>
        <span>{{ $message }}</span>
        @if ($actionHref && $actionLabel)
            <a href="{{ $actionHref }}" wire:navigate class="text-primary font-semibold hover:underline ml-1">
                {{ $actionLabel }}
            </a>
        @endif
        {{ $slot }}
    </td>
</tr>
