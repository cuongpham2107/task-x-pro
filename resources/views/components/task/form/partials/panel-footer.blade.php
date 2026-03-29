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
        @if ($this->canStartTask)
            <div class="group relative inline-flex flex-col">
                <x-ui.button variant="primary" icon="play_arrow" wire:click="startTask" loading="startTask"
                    :disabled="!$this->isPhaseStarted">
                    Bắt đầu công việc</x-ui.button>
                @if (!$this->isPhaseStarted && $this->phase?->start_date)
                    <div
                        class="absolute bottom-full left-0 z-50 mb-2 hidden w-48 rounded-lg bg-slate-900 px-2 py-1 text-xs text-white group-hover:block">
                        Giai đoạn chưa bắt đầu (Từ {{ $this->phase->start_date->format('d/m/Y') }}).
                        <div class="absolute left-4 top-full h-0 w-0 border-8 border-transparent border-t-slate-900">
                        </div>
                    </div>
                @endif
            </div>
        @endif

        {{-- Gửi xét duyệt công việc --}}
        @if ($canSubmitApproval && !$isCompletedLocked)
            <div class="group relative inline-flex flex-col">
                <x-ui.button variant="primary" icon="send" wire:click="submitForApproval" loading="submitForApproval"
                    :disabled="$progress < 100">
                    Gửi duyệt</x-ui.button>
                @if ($progress < 100)
                    <div
                        class="absolute bottom-full left-0 z-50 mb-2 hidden w-48 rounded-lg bg-slate-900 px-2 py-1 text-xs text-white group-hover:block">
                        Tiến độ công việc phải đạt 100% để gửi duyệt.
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

        <div class="group relative inline-flex flex-col">
            <x-ui.button wire:click="save" variant="primary" icon="save" loading="save" :disabled="$isCompletedLocked || !$canPersistTask">
                {{ !empty($editing_task_id) ? 'Cập nhật' : 'Thêm mới' }}
            </x-ui.button>

            @if ($isCompletedLocked)
                <div
                    class="absolute bottom-full right-0 z-50 mb-2 hidden w-48 rounded-lg bg-slate-900 px-2 py-1 text-xs text-white group-hover:block">
                    Công việc đã hoàn thành, không thể chỉnh sửa.
                    <div class="absolute right-4 top-full h-0 w-0 border-8 border-transparent border-t-slate-900">
                    </div>
                </div>
            @elseif (!$canPersistTask)
                <div
                    class="absolute bottom-full right-0 z-50 mb-2 hidden w-48 rounded-lg bg-slate-900 px-2 py-1 text-xs text-white group-hover:block">
                    @if ($isCeo)
                        CEO chỉ có quyền xem, không thể chỉnh sửa.
                    @else
                        Bạn không có quyền chỉnh sửa công việc này.
                    @endif
                    <div class="absolute right-4 top-full h-0 w-0 border-8 border-transparent border-t-slate-900">
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
