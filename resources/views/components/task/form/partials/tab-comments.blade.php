<div
    class="h-128 flex flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
    <div class="flex items-center justify-between border-b border-slate-100 p-4 dark:border-slate-800">
        <div>
            <h3 class="flex items-center gap-2 font-bold text-slate-600 dark:text-white">
                <span class="material-symbols-outlined text-primary">forum</span>
                Trao đổi thảo luận
            </h3>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                CEO, Leader, PIC chính và PIC hỗ trợ có thể để lại trao đổi ngay trong task này.
            </p>
        </div>

        <span
            class="rounded-full bg-slate-100 px-2 py-1 text-[11px] font-bold text-slate-500 dark:bg-slate-800 dark:text-slate-300">
            {{ $taskComments->count() }} bình luận
        </span>
    </div>

    <div class="flex-1 space-y-4 overflow-y-auto bg-slate-50/70 p-4 dark:bg-slate-950/40">
        @forelse ($taskComments->sortBy('created_at') as $comment)
            @php
                $isCurrentUser = (int) $comment->user_id === (int) auth()->id();
                $authorName = $comment->user?->name ?? 'Người dùng';
                $authorAvatar = $comment->user?->avatar_url;
                $authorRoleLabel = match (true) {
                    $comment->user?->hasRole('ceo') => 'CEO',
                    $comment->user?->hasRole('leader') => 'Leader',
                    (int) $comment->user_id === (int) $pic_id => 'PIC chính',
                    in_array((int) $comment->user_id, collect($co_pic_ids)->map(fn($id) => (int) $id)->all(), true)
                        => 'PIC hỗ trợ',
                    default => 'Thành viên',
                };
            @endphp

            <div @class(['flex gap-3', 'flex-row-reverse' => $isCurrentUser])>
                @if ($authorAvatar)
                    <img src="{{ $authorAvatar }}" alt="{{ $authorName }}"
                        class="size-9 shrink-0 rounded-full object-cover ring-2 ring-white dark:ring-slate-800">
                @else
                    <div
                        class="bg-primary/15 text-primary flex size-9 shrink-0 items-center justify-center rounded-full text-sm font-bold ring-2 ring-white dark:ring-slate-800">
                        {{ mb_strtoupper(mb_substr($authorName, 0, 1)) }}
                    </div>
                @endif

                <div @class([
                    'flex max-w-[85%] flex-col gap-1',
                    'items-end' => $isCurrentUser,
                ])>
                    <div @class([
                        'rounded-2xl px-3 py-2.5 shadow-sm',
                        'rounded-br-md bg-primary text-white' => $isCurrentUser,
                        'rounded-bl-md border border-slate-200 bg-white text-slate-800 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200' => !$isCurrentUser,
                    ])>
                        <div class="mb-1 flex flex-wrap items-center gap-2">
                            <span @class([
                                'text-[11px] font-bold',
                                'text-white/90' => $isCurrentUser,
                                'text-primary' => !$isCurrentUser,
                            ])>
                                {{ $authorName }}
                            </span>

                            <span @class([
                                'rounded-full px-1.5 py-0.5 text-[10px] font-semibold',
                                'bg-white/15 text-white/90' => $isCurrentUser,
                                'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-300' => !$isCurrentUser,
                            ])>
                                {{ $authorRoleLabel }}
                            </span>
                        </div>

                        <p class="wrap-break-word text-sm leading-6">
                            {{ $comment->content }}
                        </p>
                    </div>

                    <span class="px-1 text-[11px] text-slate-400">
                        {{ $comment->created_at?->format('d/m/Y H:i') }}
                    </span>
                </div>
            </div>
        @empty
            <div class="flex h-full min-h-52 flex-col items-center justify-center text-center">
                <span class="material-symbols-outlined text-4xl text-slate-300 dark:text-slate-700">chat</span>
                <p class="mt-3 text-sm font-semibold text-slate-500 dark:text-slate-300">
                    Chưa có trao đổi nào trong task này.
                </p>
                <p class="mt-1 max-w-md text-xs text-slate-400">
                    Hãy để lại cập nhật, yêu cầu hỗ trợ hoặc phản hồi để cả nhóm theo dõi cùng một luồng.
                </p>
            </div>
        @endforelse
    </div>

    <div class="border-t border-slate-100 bg-white p-3 dark:border-slate-800 dark:bg-slate-900">
        @if ($this->canCommentCurrentTask())
            <form wire:submit="addComment" class="space-y-2">
                <div class="relative">
                    <textarea wire:model="newComment" class="input-field min-h-24 resize-none pr-12 text-sm"
                        placeholder="Nhập nội dung trao đổi với nhóm..." rows="3"></textarea>

                    <button type="submit"
                        class="bg-primary absolute bottom-3 right-3 inline-flex size-9 items-center justify-center rounded-full text-white shadow-sm transition hover:opacity-90"
                        wire:loading.attr="disabled" wire:target="addComment">
                        <span wire:loading.remove wire:target="addComment"
                            class="material-symbols-outlined text-lg">send</span>
                        <span wire:loading wire:target="addComment"
                            class="material-symbols-outlined animate-spin text-lg">progress_activity</span>
                    </button>
                </div>

                <div class="flex items-center justify-between gap-3">
                    <x-ui.field-error field="newComment" />
                    <p class="text-[11px] text-slate-400">
                        Bình luận sẽ hiển thị ngay cho các thành viên tham gia task.
                    </p>
                </div>
            </form>
        @else
            <div
                class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-700 dark:border-amber-900/50 dark:bg-amber-950/20 dark:text-amber-300">
                Bạn không có quyền bình luận trong task này.
            </div>
        @endif
    </div>
</div>
