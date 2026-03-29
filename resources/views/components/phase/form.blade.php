<?php

use App\Models\Phase;
use App\Models\Project;
use App\Services\Phases\PhaseService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component
{
    public ?Project $project = null;

    public bool $showFormModal = false;

    public string $mode = 'create';

    public ?int $editingPhaseId = null;

    public string $name = '';

    public string $weight = '';

    public string $startDate = '';

    public string $endDate = '';

    public string $description = '';

    public function mount(Project $project): void
    {
        $this->project = $project;
        $this->showFormModal = false;
    }

    #[On('phase-create-requested')]
    public function showCreateFormModal(): void
    {
        Gate::forUser(auth()->user())->authorize('create', Phase::class);

        $this->resetFormModal();
        $this->mode = 'create';
        $this->editingPhaseId = null;
        $this->showFormModal = true;
    }

    #[On('phase-edit-requested')]
    public function showEditFormModal(int $phaseId): void
    {
        $this->resetFormModal();

        $phase = app(PhaseService::class)->findForEdit(auth()->user(), $phaseId);

        Gate::forUser(auth()->user())->authorize('update', $phase);

        $this->mode = 'edit';
        $this->editingPhaseId = $phase->id;
        $this->name = (string) $phase->name;
        $this->weight = (string) $phase->weight;
        $this->startDate = $phase->start_date instanceof Carbon ? $phase->start_date->toDateString() : '';
        $this->endDate = $phase->end_date instanceof Carbon ? $phase->end_date->toDateString() : '';
        $this->description = (string) ($phase->description ?? '');

        $this->showFormModal = true;
    }

    public function resetFormModal(): void
    {
        $this->reset(['name', 'weight', 'startDate', 'endDate', 'description', 'editingPhaseId']);
        $this->mode = 'create';
        $this->resetValidation();
    }

    public function rules(): array
    {
        $projectStart = $this->project->start_date ? $this->project->start_date->toDateString() : null;
        $projectEnd = $this->project->end_date ? $this->project->end_date->toDateString() : null;

        $rules = [
            'name' => 'required|string|max:255',
            'weight' => 'required|numeric|min:0|max:100',
            'startDate' => ['nullable', 'date'],
            'endDate' => ['nullable', 'date', 'after_or_equal:startDate'],
            'description' => 'nullable|string|max:5000',
        ];

        if ($projectStart) {
            $rules['startDate'][] = "after_or_equal:{$projectStart}";
            $rules['endDate'][] = "after_or_equal:{$projectStart}";
        }

        if ($projectEnd) {
            $rules['startDate'][] = "before_or_equal:{$projectEnd}";
            $rules['endDate'][] = "before_or_equal:{$projectEnd}";
        }

        return $rules;
    }

    protected function messages(): array
    {
        $projectStartLabel = $this->project->start_date ? $this->project->start_date->format('d/m/Y') : 'N/A';
        $projectEndLabel = $this->project->end_date ? $this->project->end_date->format('d/m/Y') : 'N/A';

        return [
            'name.required' => 'Tên giai đoạn là bắt buộc.',
            'name.max' => 'Tên giai đoạn không được vượt quá 255 ký tự.',
            'weight.required' => 'Trọng số là bắt buộc.',
            'weight.numeric' => 'Trọng số phải là số.',
            'weight.min' => 'Trọng số không được nhỏ hơn 0.',
            'weight.max' => 'Trọng số không được lớn hơn 100.',
            'startDate.after_or_equal' => "Ngày bắt đầu giai đoạn phải sau hoặc bằng ngày bắt đầu dự án ({$projectStartLabel}).",
            'startDate.before_or_equal' => "Ngày bắt đầu giai đoạn phải trước hoặc bằng ngày kết thúc dự án ({$projectEndLabel}).",
            'endDate.after_or_equal' => 'Ngày kết thúc phải sau hoặc bằng ngày bắt đầu giai đoạn.',
            'endDate.before_or_equal' => "Ngày kết thúc giai đoạn phải trước hoặc bằng ngày kết thúc dự án ({$projectEndLabel}).",
        ];
    }

    public function save(): void
    {
        $this->validate();
        try {
            $phaseService = app(PhaseService::class);
            $attributes = [
                'name' => $this->name,
                'weight' => $this->weight,
                'start_date' => $this->startDate ?: null,
                'end_date' => $this->endDate ?: null,
                'description' => $this->description ?: null,
            ];

            if ($this->mode === 'edit' && $this->editingPhaseId !== null) {
                $phase = $phaseService->findForEdit(auth()->user(), $this->editingPhaseId);
                $phaseService->update(auth()->user(), $phase, $attributes);

                // session()->flash('success', 'Giai đoạn đã được cập nhật thành công!');
                $this->dispatch('toast', message: 'Giai đoạn đã được cập nhật thành công!', type: 'success');
            } else {
                $phaseService->create(auth()->user(), $this->project, $attributes);

                // session()->flash('success', 'Giai đoạn đã được thêm thành công!');
                $this->dispatch('toast', message: 'Giai đoạn đã được thêm thành công!', type: 'success');
            }

            $this->showFormModal = false;
            $this->resetFormModal();

            $this->dispatch('phase-saved');
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());
            $this->dispatch('toast', message: 'Có lỗi xảy ra: '.$e->getMessage(), type: 'error');
        } catch (\Exception $e) {
            $this->dispatch('toast', message: 'Có lỗi xảy ra: '.$e->getMessage(), type: 'error');
        }
    }
};
?>

<div>
    <x-ui.modal wire:model="showFormModal" maxWidth="2xl" wire:key="phase-form-modal-{{ $editingPhaseId ?? 'create' }}">
        <x-slot name="header">
            @if (!empty($editingPhaseId))
                <x-ui.form.heading icon="edit" title="Cập nhật giai đoạn"
                    description="Chỉnh sửa thông tin giai đoạn và lưu thay đổi." />
            @else
                <x-ui.form.heading icon="add_task" title="Thêm giai đoạn mới"
                    description="Hoàn thiện thông tin dưới đây để thêm giai đoạn mới cho dự án." />
            @endif
        </x-slot>

        <form id="phase-form" wire:submit="save">
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                {{-- Tên giai đoạn --}}
                <div class="col-span-full">
                    <x-ui.input label="Tên giai đoạn" wire:model="name" placeholder="Ví dụ: Khảo sát & Phân tích"
                        required />
                    <x-ui.field-error field="name" />
                </div>

                {{-- Trọng số --}}
                <div class="col-span-full">
                    <x-ui.input label="Trọng số (%)" type="number" step="0.01" min="0" max="100"
                        wire:model="weight" placeholder="0 - 100" iconRight="percent" required />
                    <p class="text-2xs mt-1 italic text-slate-500">Tổng trọng số của tất cả các giai đoạn nên đạt 100%.
                    </p>
                    <x-ui.field-error field="weight" />
                </div>

                {{-- Ngày bắt đầu --}}
                <div>
                    <x-ui.datepicker label="Ngày bắt đầu" wire:model="startDate" />
                    <x-ui.field-error field="startDate" />
                </div>

                {{-- Ngày kết thúc --}}
                <div>
                    <x-ui.datepicker label="Ngày kết thúc (Dự kiến)" wire:model="endDate" />
                    <x-ui.field-error field="endDate" />
                </div>

                {{-- Mô tả --}}
                <div class="col-span-full">
                    <x-ui.textarea label="Mô tả chi tiết" wire:model="description"
                        placeholder="Nhập mô tả mục tiêu và nội dung của giai đoạn..." rows="3" />
                </div>
            </div>
        </form>

        <x-slot name="footer">
            <x-ui.button variant="secondary" wire:click="$set('showFormModal', false)">
                Hủy
            </x-ui.button>
            <x-ui.button type="submit" form="phase-form" variant="primary" icon="save" loading="save">
                {{ !empty($editingPhaseId) ? 'Cập nhật giai đoạn' : 'Thêm giai đoạn' }}
            </x-ui.button>
        </x-slot>
    </x-ui.modal>
</div>
