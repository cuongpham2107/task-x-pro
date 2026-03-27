@php
    $user = auth()->user();
    $isCompletedLocked = $this->isCompletedLocked;
    $editingTask = $editing_task_id ? \App\Models\Task::find($editing_task_id) : null;

    $isCeo = $user->hasRole('ceo');
    $isLeader = $isResponsibleLeader;
    $isPicUser = (int) $pic_id === $user->id;

    // Leader can save in create mode or when editing a non-completed task
    $canPersistTask = false;
    if ($mode === 'create') {
        $canPersistTask = $isLeader && !$isCeo && $user->can('create', \App\Models\Task::class);
    } elseif ($editingTask !== null) {
        // Leader can always save; PIC can save if they have permission (Participant/PIC)
        $canPersistTask =
            !$isCeo &&
            (($isLeader && $user->can('update', $editingTask)) || ($isPicUser && $user->can('update', $editingTask)));
    }

    $canApprove = false;
    if ($mode === 'edit' && $original_status === 'waiting_approval') {
        if ($user && $editingTask && $user->can('approve', $editingTask)) {
            if ($user->hasRole('super_admin')) {
                $canApprove = true;
            } elseif ($workflow_type === 'single') {
                $canApprove = !$this->hasLeaderApproved;
            } elseif ($workflow_type === 'double') {
                if ($isCeo) {
                    $canApprove = $this->hasLeaderApproved && !$this->hasCeoApproved;
                } elseif ($isLeader) {
                    $canApprove = !$this->hasLeaderApproved;
                }
            }
        }
    }

    $showApprovalActions = !$isCompletedLocked && $canApprove;

    $canSubmitApproval = false;
    if ($mode === 'edit' && ($original_status === 'in_progress' || $original_status === 'late')) {
        // Only PIC can submit for approval (not leaders who are not the PIC)
        if ($isPicUser || $user->hasRole('super_admin')) {
            $canSubmitApproval = true;
        }
    }
@endphp
<div class="flex w-full items-center justify-between">
    <div>
        @if ($mode === 'edit' && $status === 'pending' && !$isCompletedLocked)
            @php
                $startUser = auth()->user();
                $isAssignee = $startUser && (int) $pic_id === $startUser->id;
                $canStartTask = $startUser && ($startUser->hasRole('super_admin') || $isAssignee);
            @endphp
            @if ($canStartTask)
                <x-ui.button variant="primary" icon="play_arrow" wire:click="startTask" loading="startTask">
                    Bắt đầu công việc</x-ui.button>
            @endif
        @endif

        {{-- Gửi xét duyệt công việc --}}
        @if ($canSubmitApproval && !$isCompletedLocked)
            <div class="group relative inline-flex flex-col">
                <x-ui.button variant="primary" icon="send" wire:click="submitForApproval" loading="submitForApproval"
                    :disabled="$progress < 90">
                    Gửi duyệt</x-ui.button>
                @if ($progress < 90)
                    <div
                        class="absolute bottom-full left-0 z-50 mb-2 hidden w-48 rounded-lg bg-slate-900 px-2 py-1 text-xs text-white group-hover:block">
                        Tiến độ công việc phải đạt ít nhất 90% để gửi duyệt.
                        <div class="absolute left-4 top-full h-0 w-0 border-8 border-transparent border-t-slate-900">
                        </div>
                    </div>
                @endif
            </div>
        @endif

        @if ($showApprovalActions)
            <x-ui.button variant="danger" icon="close" wire:click="openRejectReasonModal">Không đạt</x-ui.button>
            <x-ui.button variant="primary" icon="check_circle" wire:click="approveTask"
                loading="approveTask">Đạt</x-ui.button>
        @endif
    </div>
    <div class="flex items-center gap-3">

        <x-ui.button variant="secondary" wire:click="$set('showFormModal', false)">Hủy</x-ui.button>
        @if (!$isCompletedLocked && $canPersistTask)
            <x-ui.button wire:click="save" variant="primary" icon="save" loading="save">
                {{ !empty($editing_task_id) ? 'Cập nhật' : 'Thêm mới' }}
            </x-ui.button>
        @endif
    </div>
</div>
