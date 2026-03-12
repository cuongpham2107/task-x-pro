    <x-ui.modal
        wire:model="showCompletionRatingModal"
        wire:key="task-completion-rating-modal-{{ $editing_task_id ?? 'none' }}-{{ $showCompletionRatingModal ? 'open' : 'closed' }}"
        maxWidth="md"
    >
        <x-slot
            name="header"
        >
            <x-ui.form.heading
                icon="star_rate"
                title="Đánh giá hoàn thành công việc"
                description="Vui lòng chọn số sao trước khi chuyển trạng thái sang Hoàn thành."
            />
        </x-slot>

        <div
            class="space-y-4"
        >
            @php
                $modalTaskName = $this->completionModalTaskName();
            @endphp
            <p
                class="text-sm text-slate-600 dark:text-slate-300"
            >Công việc: <span
                    class="font-semibold text-slate-900 dark:text-slate-100"
                >{{ $modalTaskName !== '' ? $modalTaskName : 'Chưa xác định' }}</span></p>

            <div
                class="flex items-center justify-center gap-1"
            >
                @for ($i = 1; $i <= 5; $i++)
                    <button
                        type="button"
                        wire:click="setCompletionStarRating({{ $i }})"
                        class="transition-transform hover:scale-110"
                    >
                        <span
                            class="material-symbols-outlined {{ $completionStarRating !== null && $i <= $completionStarRating ? 'text-amber-400' : 'text-slate-300 dark:text-slate-600' }} text-4xl"
                        >star</span>
                    </button>
                @endfor
            </div>

            @error('completionStarRating')
                <p
                    class="text-center text-xs font-medium text-red-500"
                >{{ $message }}</p>
            @enderror

            <div>
                <label
                    class="label-text"
                >Nhận xét (tùy chọn)</label>
                <textarea
                    rows="3"
                    class="input-field"
                    placeholder="Nhập nhận xét đánh giá..."
                    wire:model="completionApprovalComment"
                ></textarea>
            </div>
        </div>

        <x-slot
            name="footer"
        >
            <x-ui.button
                variant="secondary"
                wire:click="closeCompletionRatingModal"
            >Hủy</x-ui.button>
            <x-ui.button
                variant="primary"
                icon="check"
                wire:click="save"
                loading="save"
            >Xác nhận hoàn thành</x-ui.button>
        </x-slot>
    </x-ui.modal>
