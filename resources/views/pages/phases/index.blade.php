<?php

use App\Enums\ProjectStatus;
use App\Models\Phase;
use App\Models\Project;
use App\Services\Phases\PhaseService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Quản lý giai đoạn')] class extends Component
{
    public Project $project;

    public bool $showDeleteModal = false;

    public ?int $pendingDeletePhaseId = null;

    public string $pendingDeletePhaseName = '';

    /** pending action: 'delete' | 'start' | 'complete' | null */
    public ?string $pendingPhaseAction = null;

    /** view mode: 'table' | 'gantt' */
    public string $viewMode = 'table';

    public function mount(Project $project): void
    {
        $this->project = $project;
    }

    #[On('phase-saved')]
    public function refreshPhases(): void
    {
        unset($this->phases);
    }

    public function openEditPhaseModal(int $phaseId): void
    {
        $this->dispatch('phase-edit-requested', phaseId: $phaseId);
    }

    public function openEditProjectModal(int $projectId): void
    {
        $this->dispatch('project-edit-requested', projectId: $projectId);
    }

    public function startProject(): void
    {
        Gate::forUser(auth()->user())->authorize('update', $this->project);

        $this->project->update(['status' => ProjectStatus::Running->value]);
        $this->dispatch('toast', message: 'Dự án đã được bắt đầu thành công!', type: 'success');
    }

    public function confirmDeletePhase(int $phaseId): void
    {
        $phase = Phase::query()->findOrFail($phaseId);
        Gate::forUser(auth()->user())->authorize('delete', $phase);

        $this->pendingDeletePhaseId = $phase->id;
        $this->pendingDeletePhaseName = $phase->name;
        $this->pendingPhaseAction = 'delete';
        $this->showDeleteModal = true;
    }

    public function closeDeleteModal(): void
    {
        $this->showDeleteModal = false;
        $this->pendingDeletePhaseId = null;
        $this->pendingDeletePhaseName = '';
    }

    public function deletePhase(): void
    {
        if ($this->pendingDeletePhaseId === null) {
            return;
        }

        try {
            $phase = Phase::query()->findOrFail($this->pendingDeletePhaseId);
            app(PhaseService::class)->delete(auth()->user(), $phase);

            $this->closeDeleteModal();
            unset($this->phases);
            $this->dispatch('toast', message: 'Giai đoạn đã được xóa thành công!', type: 'success');
        } catch (\Exception $e) {
            $this->dispatch('toast', message: 'Không thể xóa giai đoạn: '.$e->getMessage(), type: 'error');
        }
    }

    public function confirmStartStatus(int $phaseId): void
    {
        $phase = Phase::query()->findOrFail($phaseId);
        Gate::forUser(auth()->user())->authorize('update', $phase);

        $this->pendingDeletePhaseId = $phase->id;
        $this->pendingDeletePhaseName = $phase->name;
        $this->pendingPhaseAction = 'start';
        $this->showDeleteModal = true;
    }

    public function confirmCompleteStatus(int $phaseId): void
    {
        $phase = Phase::query()->findOrFail($phaseId);
        Gate::forUser(auth()->user())->authorize('update', $phase);

        $this->pendingDeletePhaseId = $phase->id;
        $this->pendingDeletePhaseName = $phase->name;
        $this->pendingPhaseAction = 'complete';
        $this->showDeleteModal = true;
    }

    public function performPendingPhaseAction(): void
    {
        if ($this->pendingDeletePhaseId === null || $this->pendingPhaseAction === null) {
            return;
        }

        try {
            $phase = Phase::query()->findOrFail($this->pendingDeletePhaseId);
            $action = $this->pendingPhaseAction;

            if ($action === 'delete') {
                app(PhaseService::class)->delete(auth()->user(), $phase);
                $message = 'Giai đoạn đã được xóa thành công!';
            } elseif ($action === 'start') {
                if ($phase->start_date === null || $phase->end_date === null) {
                    $this->dispatch('toast', message: 'Không thể bắt đầu: Giai đoạn chưa có ngày bắt đầu hoặc ngày kết thúc.', type: 'error');

                    return;
                }
                app(PhaseService::class)->updateStatus(auth()->user(), $phase, 'active');
                $message = 'Giai đoạn đã được chuyển sang trạng thái Đang hoạt động.';
            } elseif ($action === 'complete') {
                app(PhaseService::class)->updateStatus(auth()->user(), $phase, 'completed');
                $message = 'Giai đoạn đã được đánh dấu là Hoàn thành.';
            } else {
                return;
            }

            $this->closeDeleteModal();
            unset($this->phases);
            $this->dispatch('toast', message: $message, type: 'success');
        } catch (\Exception $e) {
            $this->dispatch('toast', message: 'Không thể thực hiện hành động: '.$e->getMessage(), type: 'error');
        }
    }

    public function updateOrder(array $phaseIds): void
    {
        try {
            app(PhaseService::class)->reorder(auth()->user(), $this->project, $phaseIds);
            unset($this->phases);
            $this->dispatch('toast', message: 'Thứ tự giai đoạn đã được cập nhật!', type: 'success');
        } catch (\Exception $e) {
            $this->dispatch('toast', message: 'Không thể cập nhật thứ tự: '.$e->getMessage(), type: 'error');
        }
    }

    public function switchView(string $mode): void
    {
        if (in_array($mode, ['table', 'gantt'], true)) {
            $this->viewMode = $mode;
        }
    }

    #[Computed]
    public function phases()
    {
        return app(PhaseService::class)->getForProject(auth()->user(), $this->project);
    }

    #[Computed]
    public function totalWeight(): float
    {
        return (float) $this->project->phases()->sum('weight');
    }

    #[Computed]
    public function projectStart(): string
    {
        $phases = $this->phases;
        $starts = $phases->map(fn ($p) => $p->start_date ? Carbon::parse($p->start_date) : null)->filter();

        return $starts->isEmpty() ? '---' : $starts->min()->format('d/m/Y');
    }

    #[Computed]
    public function projectEnd(): string
    {
        $phases = $this->phases;
        $ends = $phases->map(fn ($p) => $p->end_date ? Carbon::parse($p->end_date) : null)->filter();

        return $ends->isEmpty() ? '---' : $ends->max()->format('d/m/Y');
    }

    /**
     * Build all data needed by the Gantt partial.
     *
     * Returns:
     *   hasTimeline  bool
     *   projectStart string   d/m/Y
     *   projectEnd   string   d/m/Y
     *   totalDays    int
     *   months       array    [ label, year, left, width ]        (% floats)
     *   dayMarkers   array    [ left, label, isMonthStart ]       (% floats)
     *   weekLines    array    [ left ]                            (% floats)
     *   items        array    [ id, name, status, progress, weight,
     *                           hasDate, start, end, left, width, color ]
     *
     * NOTE: All positional values are plain floats applied via inline style.
     *       Never use Tailwind dynamic class names (e.g. left-[X%]) for runtime %.
     *
     * @return array<string, mixed>
     */
    #[Computed]
    public function ganttData(): array
    {
        $phases = $this->phases;

        $starts = $phases->map(fn ($p) => $p->start_date ? Carbon::parse($p->start_date) : null)->filter()->values();
        $ends = $phases->map(fn ($p) => $p->end_date ? Carbon::parse($p->end_date) : null)->filter()->values();

        if ($starts->isEmpty() || $ends->isEmpty()) {
            return ['hasTimeline' => false, 'items' => []];
        }

        $projectStart = $starts->min();
        $projectEnd = $ends->max();
        $totalDays = max(1, $projectStart->diffInDays($projectEnd) + 1);

        // ── Month columns ──────────────────────────────────────────────────────
        $months = [];
        $cursor = $projectStart->copy()->startOfMonth();

        while ($cursor->lte($projectEnd)) {
            $monthStart = $cursor->copy()->startOfMonth();
            $monthEnd = $cursor->copy()->endOfMonth();

            $from = $monthStart->lt($projectStart) ? $projectStart->copy() : $monthStart;
            $to = $monthEnd->gt($projectEnd) ? $projectEnd->copy() : $monthEnd;
            $days = max(1, $from->diffInDays($to) + 1);
            $offsetDays = $projectStart->diffInDays($from);

            $months[] = [
                'label' => $cursor->locale(app()->getLocale())->isoFormat('MMM'),
                'year' => $cursor->format('Y'),
                'left' => round(($offsetDays / $totalDays) * 100, 4),
                'width' => round(($days / $totalDays) * 100, 4),
            ];

            $cursor->addMonth();
        }

        // ── Day markers ──────────────────────────────────────────────────────────────────────────
        // Adaptive step: avoid rendering too many labels on wide ranges.
        $step = match (true) {
            $totalDays <= 31 => 1,
            $totalDays <= 62 => 2,
            $totalDays <= 93 => 3,
            $totalDays <= 186 => 7,
            default => 14,
        };

        // Build lookup: 'Y-m' => exact left% from $months array.
        // Month-start day markers reuse this value so "01" tick aligns
        // pixel-perfectly with the month column border — no rounding drift.
        $monthLeftMap = [];
        $mapCursor = $projectStart->copy()->startOfMonth();
        foreach ($months as $m) {
            $monthLeftMap[$mapCursor->format('Y-m')] = $m['left'];
            $mapCursor->addMonth();
        }

        $dayMarkers = [];
        $dayCursor = $projectStart->copy();

        for ($i = 0; $i < $totalDays; $i++) {
            $isMonthStart = $dayCursor->day === 1;

            if ($isMonthStart || $i % $step === 0) {
                // Month-start: reuse EXACT left% from month column header.
                // Other days: compute from loop index normally.
                $left = $isMonthStart ? $monthLeftMap[$dayCursor->format('Y-m')] ?? round(($i / $totalDays) * 100, 4) : round(($i / $totalDays) * 100, 4);

                $dayMarkers[] = [
                    'left' => $left,
                    'label' => $dayCursor->format('d'),
                    'isMonthStart' => $isMonthStart,
                ];
            }

            $dayCursor->addDay();
        }

        // ── Week grid lines ────────────────────────────────────────────────────
        $weekLines = [];
        for ($d = 7; $d < $totalDays; $d += 7) {
            $weekLines[] = round(($d / $totalDays) * 100, 4);
        }

        // ── Phase items ────────────────────────────────────────────────────────
        $items = [];
        foreach ($phases as $phase) {
            if (! $phase->start_date || ! $phase->end_date) {
                $items[] = [
                    'id' => $phase->id,
                    'name' => $phase->name,
                    'status' => $phase->status,
                    'progress' => (int) ($phase->progress ?? 0),
                    'weight' => (float) $phase->weight,
                    'hasDate' => false,
                    'start' => null,
                    'end' => null,
                    'left' => 0.0,
                    'width' => 0.0,
                    'color' => 'pending',
                ];

                continue;
            }

            $s = Carbon::parse($phase->start_date);
            $e = Carbon::parse($phase->end_date);
            $offset = $projectStart->diffInDays($s);
            $duration = max(1, $s->diffInDays($e) + 1);

            $items[] = [
                'id' => $phase->id,
                'name' => $phase->name,
                'status' => $phase->status,
                'progress' => (int) ($phase->progress ?? 0),
                'weight' => (float) $phase->weight,
                'hasDate' => true,
                'start' => $s->format('d/m/Y'),
                'end' => $e->format('d/m/Y'),
                'left' => round(($offset / $totalDays) * 100, 4),
                'width' => round(($duration / $totalDays) * 100, 4),
                'color' => $phase->status, // 'completed' | 'active' | 'pending'
            ];
        }

        return [
            'hasTimeline' => true,
            'projectStart' => $projectStart->format('d/m/Y'),
            'projectEnd' => $projectEnd->format('d/m/Y'),
            'totalDays' => $totalDays,
            'months' => $months,
            'dayMarkers' => $dayMarkers,
            'weekLines' => $weekLines,
            'items' => $items,
        ];
    }
};
?>

<div>
    {{-- ─────────────────────────── Breadcrumbs ────────────────────────────── --}}
    <x-ui.breadcrumbs :items="[
        ['label' => 'Dự án', 'url' => route('projects.index'), 'icon' => 'folder'],
        ['label' => $project->name, 'url' => '#'],
        ['label' => 'Danh sách Giai đoạn'],
    ]" />

    {{-- ─────────────────────────── Page Header ────────────────────────────── --}}
    <div class="mb-4 flex items-center justify-between gap-6">
        <x-ui.heading title="Quản lý Giai đoạn Dự án"
            description="Sắp xếp lộ trình và phân bổ trọng số cho các cột mốc quan trọng của dự án.
                Tổng trọng số cần đạt 100% để đảm bảo tính nhất quán của tiến độ."
            class="mb-0" />

        <div class="flex items-center gap-3">
            {{-- View toggle --}}
            <div
                class="flex items-center gap-1 rounded-lg border border-slate-200 bg-slate-100 p-1 dark:border-slate-700 dark:bg-slate-800">
                <x-ui.button size="sm" :variant="$viewMode === 'table' ? 'primary' : 'ghost'" wire:click="switchView('table')">Bảng</x-ui.button>
                <x-ui.button size="sm" :variant="$viewMode === 'gantt' ? 'primary' : 'ghost'" wire:click="switchView('gantt')">Gantt</x-ui.button>
            </div>

            @can('update', $project)
                @if ($project->status === ProjectStatus::Init)
                    <x-ui.button icon="play_arrow" size="sm" variant="primary" wire:click="startProject">
                        Bắt đầu dự án
                    </x-ui.button>
                @endif

                <x-ui.button icon="edit" size="sm" variant="secondary"
                    wire:click="openEditProjectModal({{ $project->id }})">
                    Chỉnh sửa dự án
                </x-ui.button>
            @endcan

            @can('create', [App\Models\Phase::class, $project])
                <x-ui.button icon="add" size="sm" wire:click="$dispatch('phase-create-requested')">
                    Thêm giai đoạn
                </x-ui.button>
            @endcan
        </div>
    </div>

    {{-- ─────────────────────────── Stats Cards ────────────────────────────── --}}
    <div class="mb-8 grid grid-cols-1 gap-4 md:grid-cols-4">
        {{-- Total weight --}}
        <div
            class="flex flex-col gap-1 rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Tổng trọng số thiết lập</p>
            <div class="flex items-baseline gap-2">
                <span @class([
                    'text-2xl font-bold dark:text-white',
                    'text-green-600' => $this->totalWeight == 100,
                    'text-amber-600' => $this->totalWeight != 100,
                ])>{{ number_format($this->totalWeight, 0) }} / 100%</span>
                @if ($this->totalWeight == 100)
                    <span
                        class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-bold text-green-700 dark:bg-green-900/30 dark:text-green-400">Đã
                        cân đối</span>
                @else
                    <span
                        class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-bold text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">Chưa
                        cân đối</span>
                @endif
            </div>
        </div>
        {{-- Thời gian bắt đầu và kết thúc dự án --}}
        <div
            class="flex flex-col gap-1 rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Thời gian bắt đầu và kết thúc dự án</p>
            <div class="flex items-baseline gap-2">
                <span class="text-2xl font-bold text-slate-600 dark:text-white">{{ $this->projectStart }} -
                    {{ $this->projectEnd }}</span>
            </div>
        </div>
        {{-- Phase count --}}
        <div
            class="flex flex-col gap-1 rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Số lượng giai đoạn</p>
            <span class="text-2xl font-bold text-slate-600 dark:text-white">{{ $this->phases->count() }} Giai
                đoạn</span>
        </div>

        {{-- Project progress --}}
        <div
            class="flex flex-col gap-1 rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Tiến độ dự án hiện tại</p>
            <div class="flex items-center gap-3">
                <span class="text-2xl font-bold text-slate-600 dark:text-white">{{ $project->progress }}%</span>
                <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                    <div class="bg-primary h-full transition-all duration-500" style="width: {{ $project->progress }}%">
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- SortableJS (only needed for table view) --}}
    @if ($viewMode === 'table')
        <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
    @endif

    {{-- ─────────────────── Table / Gantt ──────────────────────────────────── --}}
    @if ($viewMode === 'table')
        @include('components.phase.table', [
            'phases' => $this->phases,
            'project' => $project,
        ])
    @else
        @include('components.phase.gantt', [
            'phases' => $this->phases,
            'ganttData' => $this->ganttData(),
            'project' => $project,
        ])
    @endif



    <livewire-phase.form :project="$project" />
    <livewire-project.form />

    {{-- ─────────────────── Confirmation Modal ────────────────────────────── --}}
    @if ($pendingPhaseAction !== null)
        <x-ui.modal wire:model="showDeleteModal" maxWidth="md">
            @php
                $action = $pendingPhaseAction ?? 'delete';
                $headerTitle = match ($action) {
                    'start' => 'Xác nhận bắt đầu giai đoạn',
                    'complete' => 'Xác nhận hoàn thành giai đoạn',
                    default => 'Xác nhận xóa giai đoạn',
                };
                $headerDesc = match ($action) {
                    'start' => 'Giai đoạn sẽ được chuyển sang trạng thái Đang hoạt động.',
                    'complete' => 'Giai đoạn sẽ được đánh dấu là Hoàn thành.',
                    default => 'Hành động này sẽ xóa vĩnh viễn giai đoạn và các dữ liệu liên quan.',
                };
                $confirmLabel = match ($action) {
                    'start' => 'Bắt đầu giai đoạn',
                    'complete' => 'Hoàn thành giai đoạn',
                    default => 'Xóa giai đoạn',
                };
                $confirmVariant = $action === 'delete' ? 'danger' : 'primary';
                $confirmIcon = $action === 'delete' ? 'delete' : ($action === 'start' ? 'play_arrow' : 'stop');
            @endphp

            <x-slot name="header">
                <x-ui.form.heading icon="warning" :title="$headerTitle" :description="$headerDesc" />
            </x-slot>

            <div class="space-y-3">
                <p class="text-center text-sm text-slate-600 dark:text-slate-300">
                    @if ($action === 'delete')
                        Bạn có chắc chắn muốn xóa giai đoạn này không?
                    @elseif ($action === 'start')
                        Bạn có chắc chắn muốn bắt đầu giai đoạn này không?
                    @else
                        Bạn có chắc chắn muốn đánh dấu giai đoạn này là hoàn thành không?
                    @endif
                </p>
                @if ($pendingDeletePhaseName !== '')
                    <p class="text-center text-sm font-semibold text-slate-600 dark:text-slate-100">
                        Giai đoạn: {{ $pendingDeletePhaseName }}
                    </p>
                @endif
            </div>

            <x-slot name="footer">
                <x-ui.button variant="secondary" wire:click="closeDeleteModal">Hủy</x-ui.button>
                <x-ui.button :variant="$confirmVariant" :icon="$confirmIcon" wire:click="performPendingPhaseAction"
                    loading="performPendingPhaseAction">{{ $confirmLabel }}</x-ui.button>
            </x-slot>
        </x-ui.modal>
    @endif
</div>
