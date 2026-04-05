    <x-ui.modal
        wire:model="showRejectReasonModal"
        wire:key="task-reject-reason-modal-{{ $editing_task_id ?? 'none' }}-{{ $showRejectReasonModal ? 'open' : 'closed' }}"
        maxWidth="md"
    >
        <x-slot
            name="header"
        >
            <x-ui.form.heading
                icon="rule"
                title="Từ chối phê duyệt"
                description="Nhập lý do không đạt để chuyển task về trạng thái Đang thực hiện."
            />
        </x-slot>

        <div
            class="space-y-4"
        >
            <p
                class="text-sm text-slate-600 dark:text-slate-300"
            >Công việc: <span
                    class="font-semibold text-slate-600 dark:text-slate-100"
                >{{ trim($name) !== '' ? $name : 'Chưa xác định' }}</span></p>

            <div>
                <label
                    class="label-text"
                >Lý do không đạt <span
                        class="text-red-500"
                    >*</span></label>
                <textarea
                    rows="4"
                    class="input-field"
                    placeholder="Nhập lý do từ chối để PIC cập nhật lại công việc..."
                    wire:model="rejectReason"
                ></textarea>
                @error('rejectReason')
                    <p
                        class="mt-1 text-xs font-medium text-red-500"
                    >{{ $message }}</p>
                @enderror
            </div>
        </div>

        <x-slot
            name="footer"
        >
            <x-ui.button
                variant="secondary"
                wire:click="closeRejectReasonModal"
            >Hủy</x-ui.button>
            <x-ui.button
                variant="danger"
                icon="close"
                wire:click="rejectTask"
                loading="rejectTask"
            >Xác nhận không đạt</x-ui.button>
        </x-slot>
    </x-ui.modal>
