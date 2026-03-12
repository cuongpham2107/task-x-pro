@props([
    'paginator' => null, // LengthAwarePaginator instance (tuỳ chọn)
    'paginatorLabel' => 'bản ghi', // đơn vị đếm: "dự án", "thành viên", v.v.
])

<div {{ $attributes->class([' @container']) }}>
    {{-- Table wrapper --}}
    <div
        class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-left">
                {{ $slot }}
            </table>
        </div>
    </div>

    {{-- Pagination --}}
    @if ($paginator && $paginator->total() > 0)
        <div class="mt-4 flex items-center justify-between px-2">
            <div class="text-sm text-slate-500 dark:text-slate-400">
                Hiển thị
                <span
                    class="font-semibold text-slate-900 dark:text-slate-100">{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }}</span>
                trên
                <span class="font-semibold text-slate-900 dark:text-slate-100">{{ $paginator->total() }}</span>
                {{ $paginatorLabel }}
            </div>
            {{ $paginator->links(data: ['scrollTo' => false]) }}
        </div>
    @endif
</div>
