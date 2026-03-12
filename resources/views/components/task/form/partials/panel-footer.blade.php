@php
    $isCompletedLocked = $this->isCompletedLocked;

    $canApprove = false;
    if ($mode === 'edit' && $original_status === 'waiting_approval') {
        if (auth()->user()?->hasRole('super_admin')) {
            $canApprove = true;
        } elseif ($workflow_type === 'single') {
            $canApprove = auth()->user()?->hasRole('leader');
        } elseif ($workflow_type === 'double') {
            $canApprove = auth()
                ->user()
                ?->hasAnyRole(['leader', 'ceo']);
        }
    }

    $showApprovalActions = !$isCompletedLocked && $canApprove;

    $canSubmitApproval = false;
    if ($mode === 'edit' && ($original_status === 'in_progress' || $original_status === 'late')) {
        $user = auth()->user();
        if ($user) {
            $isPic = (int) $pic_id === $user->id;
            $isCoPic = in_array($user->id, $co_pic_ids);

            if ($isPic || $isCoPic || $user->hasRole('super_admin')) {
                $canSubmitApproval = true;
            }
        }
    }
@endphp
<div class="flex w-full items-center justify-between">
    <div>
        @if ($mode === 'edit' && $status === 'pending' && !$isCompletedLocked)
            <x-ui.button variant="primary" icon="play_arrow" wire:click="startTask" loading="startTask">Bắt đầu công
                việc</x-ui.button>
        @endif

        {{-- Gửi xét duyệt công việc --}}
        @if ($canSubmitApproval && !$isCompletedLocked)
            <x-ui.button variant="primary" icon="send" wire:click="submitForApproval" loading="submitForApproval">Gửi
                duyệt</x-ui.button>
        @endif

        @if ($showApprovalActions)
            <x-ui.button variant="danger" icon="close" wire:click="openRejectReasonModal">Không đạt</x-ui.button>
            <x-ui.button variant="primary" icon="check_circle" wire:click="approveTask"
                loading="approveTask">Đạt</x-ui.button>
        @endif
    </div>
    <div class="flex items-center gap-3">

        <x-ui.button variant="secondary" wire:click="$set('showFormModal', false)">Hủy</x-ui.button>
        @if (!$isCompletedLocked)
            <x-ui.button wire:click="save" variant="primary" icon="save" loading="save">
                {{ !empty($editing_task_id) ? 'Cập nhật' : 'Thêm mới' }}
            </x-ui.button>
        @endif
    </div>
</div>
