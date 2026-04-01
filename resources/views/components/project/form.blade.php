<?php

use App\Models\Project;
use App\Services\Projects\ProjectService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component {
    public bool $showFormModal = false;

    public string $mode = 'create';

    public ?int $editingProjectId = null;

    public bool $showCreateButton = false;

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('required|string|in:warehouse,customs,trucking,software,gms,tower')]
    public string $type = '';

    #[Validate('nullable|date')]
    public ?string $startDate = '';

    #[Validate('nullable|date|after_or_equal:startDate')]
    public ?string $endDate = '';

    #[Validate('nullable|string|max:5000')]
    public ?string $objective = '';

    #[Validate('nullable|numeric|min:0')]
    public ?string $budget = '';

    public bool $is_phase = false;

    /** @var array<int, int> */
    #[
        Validate([
            'leaderIds' => 'required|array|min:1',
            'leaderIds.*' => 'integer|exists:users,id',
        ]),
    ]
    public array $leaderIds = [];

    // Form options (populated on mount)
    /** @var array<string, string> */
    public array $projectTypeLabels = [];

    /** @var Collection<int, \App\Models\User> */
    public Collection $leaderOptions;

    public function mount(): void
    {
        $this->loadFormOptions();
        $this->showCreateButton = auth()->user()?->can('project.create') ?? false;
    }

    #[On('project-create-requested')]
    public function showCreateFormModal(): void
    {
        Gate::forUser(auth()->user())->authorize('create', Project::class);

        $this->resetFormModal();
        $this->mode = 'create';
        $this->showFormModal = true;
    }

    #[On('project-edit-requested')]
    public function showEditFormModal(int $projectId): void
    {
        $this->resetValidation();

        $project = app(ProjectService::class)->findForEdit(auth()->user(), $projectId);

        Gate::forUser(auth()->user())->authorize('update', $project);

        $this->editingProjectId = $project->id;
        $this->mode = 'edit';
        $this->name = (string) $project->name;
        $this->type = $project->type instanceof \BackedEnum ? (string) $project->type->value : (string) $project->type;
        $this->startDate = $project->start_date instanceof Carbon ? $project->start_date->toDateString() : '';
        $this->endDate = $project->end_date instanceof Carbon ? $project->end_date->toDateString() : '';
        $this->objective = (string) ($project->objective ?? '');
        $this->budget = $project->budget !== null ? (string) $project->budget : '';
        $this->leaderIds = $project->leaders()->pluck('users.id')->map(fn($id) => (int) $id)->values()->all();
        $this->is_phase = false; // Reset phase toggle on edit

        $this->showFormModal = true;
    }

    public function resetFormModal(): void
    {
        $this->reset(['name', 'type', 'startDate', 'endDate', 'objective', 'budget', 'leaderIds', 'editingProjectId', 'is_phase']);
        $this->resetValidation();
    }

    private function loadFormOptions(): void
    {
        $options = app(ProjectService::class)->formOptions();
        $this->projectTypeLabels = $options['project_type_labels'];
        $this->leaderOptions = $options['leaders'];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'name.required' => 'Tên dự án là bắt buộc.',
            'name.max' => 'Tên dự án không được vượt quá 255 ký tự.',
            'type.required' => 'Loại dự án là bắt buộc.',
            'type.in' => 'Loại dự án không hợp lệ.',
            'endDate.after_or_equal' => 'Ngày kết thúc phải sau hoặc bằng ngày bắt đầu.',
            'budget.numeric' => 'Ngân sách phải là số.',
            'budget.min' => 'Ngân sách không được âm.',
            'leaderIds.required' => 'Bạn phải chọn ít nhất 1 leader.',
            'leaderIds.array' => 'Danh sách leader không hợp lệ.',
            'leaderIds.min' => 'Bạn phải chọn ít nhất 1 leader.',
            'leaderIds.*.integer' => 'Leader không hợp lệ.',
            'leaderIds.*.exists' => 'Leader đã chọn không tồn tại.',
        ];
    }

    public function updateStatus(string $newStatus): void
    {
        if ($this->editingProjectId === null) {
            return;
        }

        try {
            $project = app(ProjectService::class)->findForEdit(auth()->user(), $this->editingProjectId);
            Gate::forUser(auth()->user())->authorize('update', $project);

            // Use the service to update just the status
            app(ProjectService::class)->update(actor: auth()->user(), project: $project, attributes: ['status' => $newStatus]);

            session()->flash('success', 'Trạng thái dự án đã được cập nhật thành công!');
            $this->dispatch('toast', message: 'Trạng thái dự án đã được cập nhật!', type: 'success');

            $this->showFormModal = false;
            $this->resetFormModal();
            $this->dispatch('project-saved');
        } catch (\Exception $e) {
            $this->dispatch('toast', message: 'Lỗi: ' . $e->getMessage(), type: 'error');
        }
    }

    #[Computed]
    public function project(): ?Project
    {
        return $this->editingProjectId ? Project::find($this->editingProjectId) : null;
    }

    public function save(): void
    {
        try {
            // Normalize inputs so validation handles empty strings as null where appropriate
            $this->startDate = $this->startDate === '' ? null : $this->startDate;
            $this->endDate = $this->endDate === '' ? null : $this->endDate;
            $this->budget = $this->budget === '' || $this->budget === null ? null : (string) $this->budget;

            // Parse common date formats from UI (e.g. 31.12.2026 or 31/12/2026) into Y-m-d
            $tryParse = function (?string $value): ?string {
                if ($value === null) {
                    return null;
                }

                // Try a list of common formats
                $formats = ['Y-m-d', 'd.m.Y', 'd/m/Y', 'd-m-Y', 'd M Y', 'd F Y'];
                foreach ($formats as $fmt) {
                    try {
                        $dt = Carbon::createFromFormat($fmt, $value);

                        return $dt->toDateString();
                    } catch (\Exception $e) {
                        // ignore and try next
                    }
                }

                // Fallback to parse - may throw if invalid
                try {
                    $dt = Carbon::parse($value);

                    return $dt->toDateString();
                } catch (\Exception $e) {
                    return $value; // keep original so validator will report proper error
                }
            };

            $this->startDate = $tryParse($this->startDate);
            $this->endDate = $tryParse($this->endDate);

            $this->validate();

            $projectService = app(ProjectService::class);
            $attributes = [
                'name' => $this->name,
                'type' => $this->type,
                'start_date' => $this->startDate ?: null,
                'end_date' => $this->endDate ?: null,
                'objective' => $this->objective ?: null,
                'budget' => $this->budget !== '' ? $this->budget : null,
            ];

            // Build phase payloads: null means don't touch phases (default for edit)
            $phasePayloads = null;

            if ($this->mode === 'create') {
                $phasePayloads = $this->is_phase ? null : [];
            }

            if ($this->is_phase) {
                $phaseQueryService = app(\App\Services\Phases\PhaseQueryService::class);
                $payloads = $phaseQueryService->payloadsFromTemplates($this->type);

                if ($payloads !== []) {
                    $phasePayloads = $payloads;
                }
            }

            if ($this->mode === 'edit' && $this->editingProjectId !== null) {
                $project = $projectService->findForEdit(auth()->user(), $this->editingProjectId);

                $projectService->update(actor: auth()->user(), project: $project, attributes: $attributes, leaderIds: $this->leaderIds ?: [], phasePayloads: $phasePayloads);

                session()->flash('success', 'Dự án đã được cập nhật thành công!');
                $this->dispatch('toast', message: 'Dự án đã được cập nhật thành công!', type: 'success');
            } else {
                // Ensure leaderIds is an array
                $leaderIds = is_array($this->leaderIds) ? $this->leaderIds : [];

                $projectService->create(actor: auth()->user(), attributes: $attributes, leaderIds: $leaderIds ?: [], phasePayloads: $phasePayloads);

                session()->flash('success', 'Dự án đã được tạo thành công!');
                $this->dispatch('toast', message: 'Dự án đã được tạo thành công!', type: 'success');
            }

            $this->showFormModal = false;
            $this->resetFormModal();

            $this->dispatch('project-saved');
        } catch (ValidationException $e) {
            \Log::error('Validation failed', ['errors' => $e->validator->errors()->toArray()]);
            $this->setErrorBag($e->validator->errors());
            $this->dispatch('toast', message: 'Vui lòng kiểm tra lại thông tin! ' . $e->getMessage(), type: 'error');
        } catch (\Exception $e) {
            \Log::error('Project save failed', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            session()->flash('error', 'Có lỗi xảy ra: ' . $e->getMessage());
            $this->dispatch('toast', message: 'Có lỗi xảy ra: ' . $e->getMessage(), type: 'error');
        }
    }
};
?>

<div>
    {{-- Button moved to index page --}}

    <x-ui.modal wire:model="showFormModal" maxWidth="5xl"
        wire:key="project-form-modal-{{ $editingProjectId ?? 'create' }}">
        <x-slot name="header">
            @if (!empty($editingProjectId))
                <x-ui.form.heading icon="edit" title="Cập nhật dự án"
                    description="Chỉnh sửa thông tin dự án và lưu thay đổi." />
            @else
                <x-ui.form.heading icon="add" title="Tạo dự án mới"
                    description="Hoàn thiện thông tin dưới đây để khởi tạo dự án của bạn." />
            @endif
        </x-slot>

        <form id="project-form" wire:submit="save" novalidate>
            <x-ui.section-card title="Thông tin chung" icon="description" iconBg="bg-transparent"
                iconColor="text-primary" :separator="true">
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    {{-- Tên dự án --}}
                    <div class="col-span-full">
                        <x-ui.input label="Tên dự án" name="name" wire:model="name"
                            placeholder="Ví dụ: Chiến dịch Marketing Q4 - 2024" required />
                    </div>

                    {{-- Loại hình dự án --}}
                    <div>
                        <x-ui.select label="Loại hình dự án" name="type" wire:model.live="type" icon="category"
                            :options="$projectTypeLabels" required />
                    </div>

                    {{-- Ngân sách --}}
                    <div>
                        <x-ui.input label="Ngân sách (VND)" name="budget" type="number" wire:model="budget"
                            placeholder="Ví dụ: 500000000" min="0" />
                    </div>

                    {{-- Ngày bắt đầu --}}
                    <div>
                        <x-ui.datepicker label="Ngày bắt đầu" name="startDate" wire:model="startDate" />
                    </div>
                    {{-- Ngày kết thúc --}}
                    <div>
                        <x-ui.datepicker label="Ngày kết thúc (Dự kiến)" name="endDate" wire:model="endDate" />
                    </div>
                    {{-- Sử dụng mẫu phase hay không --}}
                    {{-- Sử dụng mẫu phase hay không --}}
                    @php
                        $isPhaseOptions = [
                            1 => [
                                'label' => 'Có',
                                'description' => 'Áp dụng các giai đoạn mẫu cho dự án này.',
                                'icon' => 'task_alt',
                                'color' => 'text-brand',
                                'bg' => 'bg-brand/5',
                            ],
                            0 => [
                                'label' => 'Không',
                                'description' => 'Thiết lập cấu trúc công việc từ đầu.',
                                'icon' => 'block',
                                'color' => 'text-slate-600',
                                'bg' => 'bg-slate-50',
                            ],
                        ];
                    @endphp
                    <x-ui.radio-group name="is_phase" wire:model.live="is_phase" label="Sử dụng mẫu phase"
                        :options="$isPhaseOptions" :hidden="!empty($editingProjectId)" />

                    {{-- Leader --}}
                    @if ($leaderOptions->isNotEmpty())
                        <div class="col-span-full">

                            <x-ui.user-multi-select model="leaderIds" :users="$leaderOptions" label="Leader dự án"
                                placeholder="Tìm tên hoặc email leader..." empty-text="Không tìm thấy leader phù hợp"
                                dropdown-position="top" required
                                wire:key="project-leaders-{{ $mode }}-{{ $editingProjectId ?? 'new' }}" />
                            <x-ui.field-error field="leaderIds" />
                            <x-ui.field-error field="leader_ids" />

                        </div>
                    @endif
                    {{-- Mô tả / Mục tiêu --}}
                    <div class="col-span-full">
                        <x-ui.textarea label="Mục tiêu dự án" name="objective" wire:model="objective"
                            placeholder="Nhập mục tiêu và phạm vi dự án của bạn..." rows="3" />
                    </div>



                </div>
            </x-ui.section-card>



        </form>

        <x-slot name="footer">
            <div class="flex flex-1 items-center justify-start gap-2">
                @if ($editingProjectId && $this->project)
                    @can('update', $this->project)
                        @if (!in_array($this->project->status->value, ['running', 'completed', 'cancelled'], true))
                            <x-ui.button variant="primary" size="sm" icon="play_circle"
                                wire:click="updateStatus('running')" loading="updateStatus('running')">
                                Bắt đầu
                            </x-ui.button>
                        @endif

                        @if ($this->project->status->value !== 'completed')
                            <x-ui.button variant="success" size="sm" icon="check_circle" :hidden="$this->project->progress !== 100"
                                wire:click="updateStatus('completed')" loading="updateStatus('completed')">
                                Hoàn thành
                            </x-ui.button>
                        @endif

                        @if ($this->project->status->value !== 'paused')
                            <x-ui.button variant="warning" size="sm" icon="pause_circle" :hidden="$this->project->status->value === 'completed'"
                                wire:click="updateStatus('paused')" loading="updateStatus('paused')">
                                Tạm dừng
                            </x-ui.button>
                        @endif

                        @if ($this->project->status->value !== 'cancelled')
                            <x-ui.button variant="danger" size="sm" icon="cancel" :hidden="$this->project->status->value === 'completed'"
                                wire:click="updateStatus('cancelled')" loading="updateStatus('cancelled')">
                                Hủy dự án
                            </x-ui.button>
                        @endif
                    @endcan
                @endif
            </div>

            <div class="flex items-center gap-3">
                <x-ui.button variant="secondary" @click="isOpen = false">
                    Hủy
                </x-ui.button>
                <x-ui.button type="submit" form="project-form" variant="primary" icon="save" loading="save">
                    {{ $editingProjectId ? 'Cập nhật dự án' : 'Tạo dự án' }}
                </x-ui.button>
            </div>
        </x-slot>
    </x-ui.modal>
</div>
