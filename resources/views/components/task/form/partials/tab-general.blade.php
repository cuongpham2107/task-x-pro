@php
    $user = auth()->user();
    $isManager = $user->hasAnyRole(['leader', 'super_admin']);
    $isCeo = $user->hasRole('ceo');
    $isRestricted = !$isManager;
@endphp

<div class="{{ $this->isCompletedLocked ? 'pointer-events-none select-none opacity-70' : '' }} grid grid-cols-2 gap-6">
    {{-- Tên công việc --}}
    <div class="col-span-full">
        <x-ui.input label="Tên công việc" name="name" placeholder="Nhập tên công việc..." wire:model="name"
            :disabled="$isCeo" required />
    </div>
    @if (!$this->isPhaseScoped)
        <div class="col-span-full grid grid-cols-1 gap-6 md:grid-cols-2">
            <div>
                <x-ui.select label="Dự án" name="project_id" wire:model.live="project_id" icon="folder"
                    placeholder="Chọn dự án" :options="$this->projectSelectOptions" :disabled="$isRestricted" />
            </div>

            <div>
                <x-ui.select label="Giai đoạn" name="phase_id" wire:model.live="phase_id" icon="timeline"
                    placeholder="Chọn giai đoạn" :options="$this->phaseSelectOptions" required :disabled="$isRestricted" />
            </div>
        </div>
    @endif

    {{-- Loại công việc & Trạng thái --}}
    <div>
        <x-ui.select label="Loại công việc" name="type" wire:model="type" icon="category" :options="$taskTypeLabels"
            :disabled="$isRestricted" required />
    </div>

    <div>
        <x-ui.select label="Trạng thái" name="status" wire:model="status" icon="sync" :options="$taskStatusLabels"
            :disabled="$hasDependencyBlock || $mode === 'edit' || $mode === 'create'" required />
        @if ($hasDependencyBlock)
            <div class="mt-1.5 flex items-start gap-1.5 rounded-lg bg-amber-50 px-2.5 py-2 dark:bg-amber-900/20">
                <span class="material-symbols-outlined shrink-0 text-sm text-amber-500">lock</span>
                <p class="text-[11px] font-medium leading-tight text-amber-700 dark:text-amber-400">
                    Không thể đổi trạng thái vì đang phụ thuộc vào task
                    <span class="font-bold underline">{{ $dependencyTaskName }}</span>
                    (chưa hoàn thành).
                </p>
            </div>
        @elseif ($mode === 'edit')
            <p class="mt-1.5 text-[11px] text-slate-400">Dùng các nút thao tác bên dưới để chuyển trạng thái.</p>
        @endif
    </div>

    {{-- Hạn chót & PIC --}}
    <div>
        <x-ui.datepicker label="Hạn chót" name="deadline" wire:model="deadline" required="true" :disabled="$isRestricted" />
    </div>

    <div>
        <x-ui.user-select model="pic_id" :users="$picOptions" label="Người phụ trách (PIC)"
            placeholder="Chọn hoặc tìm kiếm PIC..." required="true" :disabled="$isRestricted" />
    </div>

    {{-- Mức độ ưu tiên --}}
    <x-ui.radio-group label="Mức độ ưu tiên" name="priority" wire:model="priority"
        grid-cols="grid-cols-2 sm:grid-cols-4" :disabled="$isRestricted" :options="[
            'low' => [
                'label' => 'Thấp',
                'color' => 'text-blue-500 has-checked:bg-blue-500/5 has-checked:border-blue-500',
            ],
            'medium' => [
                'label' => 'Trung bình',
                'color' => 'text-primary has-checked:bg-primary/5 has-checked:border-primary',
            ],
            'high' => [
                'label' => 'Cao',
                'color' => 'text-orange-500 has-checked:bg-orange-500/5 has-checked:border-orange-500',
            ],
            'urgent' => [
                'label' => 'Khẩn cấp',
                'color' => 'text-red-600 has-checked:bg-red-600/5 has-checked:border-red-600',
            ],
        ]" />

    {{-- Quy trình duyệt --}}
    <x-ui.radio-group label="Quy trình phê duyệt" icon="info" name="workflow_type" wire:model="workflow_type"
        grid-cols="grid-cols-1 sm:grid-cols-2" :disabled="$isRestricted" :options="[
            'single' => [
                'label' => '1 cấp duyệt (Leader)',
                'description' => 'Leader phê duyệt là hoàn thành.',
                'icon' => 'person',
                'color' => 'text-primary has-checked:bg-primary/5 has-checked:border-primary',
            ],
            'double' => [
                'label' => '2 cấp duyệt (Leader + CEO)',
                'description' => 'Leader duyệt trước, sau đó CEO duyệt để hoàn thành.',
                'icon' => 'group',
                'color' => 'text-primary has-checked:bg-primary/5 has-checked:border-primary',
            ],
        ]" />

    {{-- Tiến độ công việc (Progress) --}}
    <x-ui.range-slider label="Tiến độ công việc" name="progress" wire:model="progress" icon="trending_up"
        start-label="Bắt đầu (0%)" end-label="Hoàn thành (100%)" :disabled="$isCeo || $status === 'pending' ||
            $this->phase->status === 'pending' ||
            (auth()->id() !== (int) $pic_id && !in_array(auth()->id(), $co_pic_ids ?: []))" />

    {{-- Link sản phẩm --}}
    <div class="col-span-full">
        <x-ui.input label="Link sản phẩm (Drive/Figma/...)" name="deliverable_url" type="url"
            placeholder="https://..." wire:model="deliverable_url" icon="link" :disabled="$isCeo" />
    </div>

    {{-- Phụ thuộc công việc --}}
    <div class="col-span-full space-y-2 text-slate-900" x-data="{
        search: '',
        showDropdown: false,
        disabled: {{ $isRestricted ? 'true' : 'false' }},
        selectedId: @entangle('dependency_task_id').live,
        allTasks: {{ Js::from($dependencyTaskOptions->map(fn($t) => ['id' => $t->id, 'name' => $t->name, 'status' => $t->status instanceof \BackedEnum ? $t->status->value : $t->status])->values()) }},
        get selectedTask() {
            if (!this.selectedId) return null;
            return this.allTasks.find(t => Number(t.id) === Number(this.selectedId));
        },
        get filtered() {
            if (!this.search.trim()) return this.allTasks;
            const q = this.search.toLowerCase();
            return this.allTasks.filter(t => t.name.toLowerCase().includes(q));
        },
        select(task) {
            if (this.disabled) return;
            this.selectedId = Number(task.id);
            this.search = '';
            this.showDropdown = false;
        },
        clear() {
            if (this.disabled) return;
            this.selectedId = null;
            this.search = '';
        },
        statusLabel(status) {
            const map = { 'pending': 'Chờ', 'in_progress': 'Đang làm', 'waiting_approval': 'Chờ duyệt', 'completed': 'Hoàn thành', 'cancelled': 'Đã hủy' };
            return map[status] || status;
        },
        statusColor(status) {
            const map = {
                'pending': 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
                'in_progress': 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
                'waiting_approval': 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
                'completed': 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
                'cancelled': 'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400',
            };
            return map[status] || 'bg-slate-100 text-slate-600';
        }
    }" @click.outside="showDropdown = false">
        <label class="label-text">
            <span class="flex items-center gap-1.5">
                <span class="material-symbols-outlined text-base text-slate-400">account_tree</span>
                Phụ thuộc công việc
            </span>
        </label>
        <p class="-mt-1 text-xs text-slate-400">Task này chỉ được bắt đầu khi task phụ thuộc hoàn thành.</p>

        <div class="relative mt-1">
            <div @click="if (!disabled) { showDropdown = !showDropdown; $nextTick(() => { if (showDropdown) $refs.depSearch.focus() }) }"
                class="input-field flex items-center justify-between gap-2 overflow-hidden bg-white py-2.5 pl-3 pr-2 transition-all dark:bg-slate-900"
                :class="{
                    'cursor-not-allowed opacity-60 bg-slate-50 dark:bg-slate-800/50': disabled,
                    'cursor-pointer hover:border-slate-400': !disabled,
                    'border-primary ring-2 ring-primary/20': showDropdown,
                    'border-slate-300 dark:border-slate-700': !showDropdown
                }">
                <div class="flex min-w-0 items-center gap-2">
                    <template x-if="selectedTask">
                        <div class="flex items-center gap-2 overflow-hidden">
                            <span class="material-symbols-outlined text-primary text-lg">task_alt</span>
                            <span class="truncate text-sm font-medium text-slate-900 dark:text-white"
                                x-text="selectedTask.name"></span>
                            <span class="text-2xs shrink-0 rounded-full px-2 py-0.5 font-bold"
                                :class="statusColor(selectedTask.status)"
                                x-text="statusLabel(selectedTask.status)"></span>
                        </div>
                    </template>
                    <template x-if="!selectedTask">
                        <span class="text-sm text-slate-400">Không phụ thuộc (tùy chọn)</span>
                    </template>
                </div>
                <div class="flex shrink-0 items-center gap-1 text-slate-400">
                    <template x-if="selectedTask">
                        <button type="button" @click.stop="clear()" class="transition-colors hover:text-red-500">
                            <span class="material-symbols-outlined text-lg">close</span>
                        </button>
                    </template>
                    <span class="material-symbols-outlined text-xl transition-transform duration-200"
                        :class="showDropdown ? 'rotate-180' : ''">expand_more</span>
                </div>
            </div>

            <div x-show="showDropdown" x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="scale-95 opacity-0" x-transition:enter-end="scale-100 opacity-100"
                x-transition:leave="transition ease-in duration-100" x-transition:leave-start="scale-100 opacity-100"
                x-transition:leave-end="scale-95 opacity-0"
                class="absolute left-0 top-full z-30 mt-2 w-full overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl dark:border-slate-700 dark:bg-slate-900"
                style="display: none;">
                <div class="border-b border-slate-100 p-2 dark:border-slate-800">
                    <div class="relative">
                        <span
                            class="material-symbols-outlined pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 text-base text-slate-400">search</span>
                        <x-ui.input x-ref="depSearch" x-model="search" type="text" placeholder="Tìm công việc..."
                            class="bg-slate-50 pl-8 pr-3 dark:bg-slate-800" @keydown.escape="showDropdown = false" />
                    </div>
                </div>

                <div class="custom-scrollbar max-h-60 overflow-y-auto">
                    <template x-if="filtered.length === 0">
                        <div class="px-4 py-6 text-center text-sm text-slate-400">
                            <span class="material-symbols-outlined mb-1 block text-2xl">search_off</span>
                            Không tìm thấy công việc nào.
                        </div>
                    </template>

                    <template x-for="task in filtered" :key="task.id">
                        <button type="button" @click="select(task)"
                            class="group w-full border-b border-slate-50 transition-colors last:border-none dark:border-slate-800/50"
                            :class="Number(selectedId) === Number(task.id) ? 'bg-primary/5' :
                                'hover:bg-slate-50 dark:hover:bg-slate-800/50'">
                            <div class="flex items-center gap-3 px-4 py-3">
                                <span class="material-symbols-outlined shrink-0 text-lg"
                                    :class="Number(selectedId) === Number(task.id) ? 'text-primary' :
                                        'text-slate-400'">task_alt</span>
                                <div class="min-w-0 flex-1 text-left">
                                    <p class="truncate text-sm font-medium"
                                        :class="Number(selectedId) === Number(task.id) ? 'text-primary' :
                                            'text-slate-900 dark:text-white'"
                                        x-text="task.name"></p>
                                </div>
                                <span class="text-2xs shrink-0 rounded-full px-2 py-0.5 font-bold"
                                    :class="statusColor(task.status)" x-text="statusLabel(task.status)"></span>
                                <template x-if="Number(selectedId) === Number(task.id)">
                                    <span class="material-symbols-outlined text-primary font-bold">check_circle</span>
                                </template>
                            </div>
                        </button>
                    </template>
                </div>
            </div>
        </div>
        <x-ui.field-error field="dependency_task_id" />
    </div>

    {{-- Co-PIC --}}
    <div class="col-span-full">
        <x-ui.user-multi-select model="co_pic_ids" :users="$picOptions" label="Người hỗ trợ (Co-PIC)"
            placeholder="Chọn người hỗ trợ..." :disabled="$isRestricted" />
    </div>

    {{-- Mô tả công việc --}}
    <x-ui.textarea label="Mô tả công việc" name="description" placeholder="Nhập chi tiết yêu cầu công việc..."
        rows="4" wire:model="description" :disabled="$isRestricted" />

    {{-- Đính kèm tệp --}}
    <x-ui.file-upload name="files" :existing-attachments="$existing_attachments" :new-files="$files" label="Đính kèm tệp"
        :disabled="$isCeo" />
</div>
