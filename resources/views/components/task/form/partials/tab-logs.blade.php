<div class="animate-in fade-in slide-in-from-bottom-2 duration-300">
    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="mb-6 flex items-center justify-between">
            <h3 class="flex items-center gap-2 font-bold text-slate-900 dark:text-white">
                <span class="material-symbols-outlined text-primary">history_edu</span>
                Nhật ký hoạt động & Phê duyệt
            </h3>
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-sm text-slate-400">lock</span>
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Hệ thống ghi
                    nhận tự động - Không thể sửa</span>
            </div>
        </div>
        @if ($this->logs->isEmpty())
            <div class="flex flex-col items-center justify-center py-12 text-slate-400">
                <span class="material-symbols-outlined mb-2 text-4xl">history</span>
                <p class="text-sm">Chưa có nhật ký hoạt động nào.</p>
            </div>
        @else
            <ol class="relative ms-4 mt-2 border-s border-slate-100 dark:border-slate-800">
                @foreach ($this->logs as $log)
                    <li class="mb-10 ms-7 last:mb-0">
                        <span @class([
                            'absolute flex items-center justify-center w-8 h-8 rounded-full -start-4 ring-4 ring-white dark:ring-slate-900',
                            'bg-green-100 text-green-600 dark:bg-green-900/30 dark:text-green-400' =>
                                $log->color === 'green',
                            'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400' =>
                                $log->color === 'red',
                            'bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400' =>
                                $log->color === 'blue',
                            'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' =>
                                $log->color === 'slate',
                        ])>
                            <span class="material-symbols-outlined text-lg">{{ $log->icon }}</span>
                        </span>

                        <div class="flex flex-col gap-1">
                            <div class="flex flex-col justify-between md:flex-row md:items-center">
                                <h3 class="font-bold leading-tight text-slate-900 dark:text-white">
                                    @if ($log->type === 'approval' && $log->action === 'rejected')
                                        <span class="text-red-600 dark:text-red-400">Từ chối phê duyệt</span>
                                    @elseif ($log->type === 'approval' && $log->action === 'approved')
                                        <span class="text-green-600 dark:text-green-400">Đã phê duyệt</span>
                                    @else
                                        {{ $log->action_label }}
                                    @endif
                                    bởi <span class="text-primary font-bold">{{ $log->user_name }}</span>
                                </h3>
                                <time class="whitespace-nowrap text-[11px] font-medium text-slate-400">
                                    {{ $log->created_at->translatedFormat('d/m/Y, H:i') }}
                                </time>
                            </div>

                            @if ($log->comment)
                                <div @class([
                                    'mt-2 rounded-lg border p-3',
                                    'bg-red-50 border-red-100 dark:bg-red-900/10 dark:border-red-900/20' => in_array(
                                        $log->action,
                                        ['rejected', 'approval_rejected'],
                                        true),
                                    'bg-slate-50 border-slate-100 dark:bg-slate-800/50 dark:border-slate-800' => !in_array(
                                        $log->action,
                                        ['rejected', 'approval_rejected'],
                                        true),
                                ])>
                                    <p class="text-xs italic leading-relaxed text-slate-700 dark:text-slate-300">
                                        <strong>{{ in_array($log->action, ['rejected', 'approval_rejected'], true) ? 'Lý do:' : 'Ghi chú:' }}</strong>
                                        {{ $log->comment }}
                                    </p>
                                </div>
                            @endif

                            @if ($log->type === 'activity')
                                @if ($log->action === 'progress_updated' && isset($log->new_values['progress']))
                                    <div class="mt-2 flex items-center gap-2 text-xs">
                                        <span
                                            class="rounded bg-slate-100 px-2 py-0.5 text-slate-500 line-through dark:bg-slate-800">{{ $log->old_values['progress'] ?? 0 }}%</span>
                                        <span
                                            class="material-symbols-outlined text-sm text-slate-400">arrow_forward</span>
                                        <span
                                            class="rounded bg-blue-100 px-2 py-0.5 font-bold text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">{{ $log->new_values['progress'] }}%</span>
                                    </div>
                                @elseif ($log->action === 'status_updated' && isset($log->new_values['status']))
                                    @php
                                        $oldStatus = \App\Enums\TaskStatus::tryFrom($log->old_values['status'] ?? '');
                                        $newStatus = \App\Enums\TaskStatus::tryFrom($log->new_values['status']);
                                    @endphp
                                    <div class="mt-2 flex items-center gap-2 text-xs">
                                        <span
                                            class="rounded bg-slate-100 px-2 py-0.5 text-slate-500 line-through dark:bg-slate-800">
                                            {{ $oldStatus ? $oldStatus->label() : $log->old_values['status'] ?? 'Chưa xác định' }}
                                        </span>
                                        <span
                                            class="material-symbols-outlined text-sm text-slate-400">arrow_forward</span>
                                        <span @class([
                                            'px-2 py-0.5 rounded font-bold',
                                            'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' =>
                                                $newStatus?->value === 'completed',
                                            'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' => in_array(
                                                $newStatus?->value,
                                                ['in_progress', 'review'],
                                                true),
                                            'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-400' => !in_array(
                                                $newStatus?->value,
                                                ['completed', 'in_progress', 'review'],
                                                true),
                                        ])>
                                            {{ $newStatus ? $newStatus->label() : $log->new_values['status'] }}
                                        </span>
                                    </div>
                                @endif
                            @endif

                            @if ($log->type === 'approval' && $log->star_rating)
                                <div class="mt-2 flex items-center gap-1">
                                    <x-ui.star-rating :rating="$log->star_rating" size="4" />
                                    <span
                                        class="ml-1 text-[10px] font-bold text-slate-400">({{ $log->star_rating }}/5)</span>
                                </div>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ol>
        @endif
    </div>
</div>
