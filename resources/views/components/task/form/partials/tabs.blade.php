            @if ($mode === 'edit')
                <div
                    class="mb-8 flex border-b border-slate-200 dark:border-slate-800"
                >
                    <button
                        type="button"
                        wire:click="$set('activeTab', 'general')"
                        class="{{ $activeTab === 'general' ? 'text-primary' : 'text-slate-400 hover:text-slate-600 dark:hover:text-slate-200' }} group relative flex items-center gap-2 px-6 py-3 text-sm font-bold transition-all"
                    >
                        <span
                            class="material-symbols-outlined text-lg"
                        >info</span>
                        Thông tin chung
                        @if ($activeTab === 'general')
                            <div
                                class="bg-primary absolute -bottom-px left-0 h-0.5 w-full rounded-full shadow-[0_0_8px_rgba(var(--color-primary),0.5)]"
                            ></div>
                        @endif
                    </button>
                    <button
                        type="button"
                        wire:click="$set('activeTab', 'issues')"
                        class="{{ $activeTab === 'issues' ? 'text-primary' : 'text-slate-400 hover:text-slate-600 dark:hover:text-slate-200' }} group relative flex items-center gap-2 px-6 py-3 text-sm font-bold transition-all"
                    >
                        <span
                            class="material-symbols-outlined text-lg"
                        >error_outline</span>
                        Vấn đề & Đề xuất
                        @if ($activeTab === 'issues')
                            <div
                                class="bg-primary absolute -bottom-px left-0 h-0.5 w-full rounded-full shadow-[0_0_8px_rgba(var(--color-primary),0.5)]"
                            ></div>
                        @endif
                    </button>
                    <button
                        type="button"
                        wire:click="$set('activeTab', 'comments')"
                        class="{{ $activeTab === 'comments' ? 'text-primary' : 'text-slate-400 hover:text-slate-600 dark:hover:text-slate-200' }} group relative flex items-center gap-2 px-6 py-3 text-sm font-bold transition-all"
                    >
                        <span
                            class="material-symbols-outlined text-lg"
                        >comment</span>
                        Bình luận
                        <span class="rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-300">
                            {{ $taskComments->count() }}
                        </span>
                        @if ($activeTab === 'comments')
                            <div
                                class="bg-primary absolute -bottom-px left-0 h-0.5 w-full rounded-full shadow-[0_0_8px_rgba(var(--color-primary),0.5)]"
                            ></div>
                        @endif
                    </button>
                    <button
                        type="button"
                        wire:click="$set('activeTab', 'logs')"
                        class="{{ $activeTab === 'logs' ? 'text-primary' : 'text-slate-400 hover:text-slate-600 dark:hover:text-slate-200' }} group relative flex items-center gap-2 px-6 py-3 text-sm font-bold transition-all"
                    >
                        <span
                            class="material-symbols-outlined text-lg"
                        >history</span>
                        Nhật ký
                        @if ($activeTab === 'logs')
                            <div
                                class="bg-primary absolute -bottom-px left-0 h-0.5 w-full rounded-full shadow-[0_0_8px_rgba(var(--color-primary),0.5)]"
                            ></div>
                        @endif
                    </button>
                </div>
            @endif
