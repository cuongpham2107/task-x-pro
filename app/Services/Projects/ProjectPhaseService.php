<?php

namespace App\Services\Projects;

use App\Models\Phase;
use App\Models\PhaseTemplate;
use App\Models\Project;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class ProjectPhaseService
{
    /**
     * Tu dong sinh phase theo template cua loai project (BR-001).
     */
    public function createPhasesFromTemplate(Project $project): void
    {
        $templates = PhaseTemplate::query()
            ->where('project_type', $project->type)
            ->where('is_active', true)
            ->orderBy('order_index')
            ->get();

        if ($templates->isEmpty()) {
            throw ValidationException::withMessages([
                'type' => 'Chua cau hinh phase template cho loai project nay.',
            ]);
        }

        $phasePayloads = [];
        $cursorStart = $project->start_date !== null
            ? Carbon::parse($project->start_date)->startOfDay()
            : null;

        foreach ($templates as $template) {
            $startDate = $cursorStart?->toDateString();
            $endDate = null;

            if ($cursorStart !== null && $template->default_duration_days !== null) {
                $durationDays = max(1, (int) $template->default_duration_days);
                $phaseEnd = $cursorStart->copy()->addDays($durationDays - 1);
                $endDate = $phaseEnd->toDateString();
                $cursorStart = $phaseEnd->copy()->addDay()->startOfDay();
            }

            $phasePayloads[] = [
                'name' => $template->phase_name,
                'description' => $template->phase_description,
                'weight' => (float) $template->default_weight,
                'order_index' => (int) $template->order_index,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'is_template' => true,
            ];
        }

        $this->upsertPhases($project, $phasePayloads);
    }

    /**
     * Tao moi/cap nhat/xoa phase cho mot project theo payload tu form.
     */
    public function upsertPhases(Project $project, array $phasePayloads): void
    {
        if ($phasePayloads === []) {
            throw ValidationException::withMessages([
                'phases' => 'Project phai co it nhat 1 phase.',
            ]);
        }

        $this->assertValidPhaseWeightTotal($phasePayloads);

        $existingPhases = $project->phases()
            ->withCount('tasks')
            ->get()
            ->keyBy('id');

        $keptPhaseIds = collect();

        foreach ($phasePayloads as $index => $phasePayload) {
            $phaseId = isset($phasePayload['id']) && $phasePayload['id'] !== null
                ? (int) $phasePayload['id']
                : null;

            $name = trim((string) ($phasePayload['name'] ?? ''));
            if ($name === '') {
                throw ValidationException::withMessages([
                    "phases.{$index}.name" => 'Tên phase không được để trống.',
                ]);
            }

            $weight = round((float) ($phasePayload['weight'] ?? 0), 2);
            if ($weight <= 0) {
                throw ValidationException::withMessages([
                    "phases.{$index}.weight" => 'Trọng số phase phải lớn hơn 0.',
                ]);
            }

            $attributes = [
                'name' => $name,
                'description' => $phasePayload['description'] ?? null,
                'weight' => $weight,
                'order_index' => isset($phasePayload['order_index'])
                    ? (int) $phasePayload['order_index']
                    : ($index + 1),
                'start_date' => $phasePayload['start_date'] ?? null,
                'end_date' => $phasePayload['end_date'] ?? null,
                'is_template' => (bool) ($phasePayload['is_template'] ?? false),
            ];

            if ($phaseId === null) {
                $newPhase = $project->phases()->create([
                    ...$attributes,
                    'progress' => 0,
                    'status' => 'pending',
                ]);

                $keptPhaseIds->push($newPhase->id);

                continue;
            }

            $phase = $existingPhases->get($phaseId);
            if (! $phase instanceof Phase) {
                throw ValidationException::withMessages([
                    "phases.{$index}.id" => 'Phase không tồn tại trong project hiện tại.',
                ]);
            }

            $phase->fill($attributes);
            $phase->save();
            $keptPhaseIds->push($phase->id);
        }

        $phaseIdsToDelete = $existingPhases
            ->keys()
            ->diff($keptPhaseIds->unique()->values());

        foreach ($phaseIdsToDelete as $phaseIdToDelete) {
            $phase = $existingPhases->get($phaseIdToDelete);

            if (! $phase instanceof Phase) {
                continue;
            }

            if ($phase->tasks_count > 0) {
                throw ValidationException::withMessages([
                    'phases' => "Không thể xóa phase [{$phase->name}] vì đã có task.",
                ]);
            }

            $phase->delete();
        }
    }

    /**
     * Kiểm tra tổng trọng số phase phải bằng 100 theo BR-008.
     */
    private function assertValidPhaseWeightTotal(array $phasePayloads): void
    {
       
        $weightTotal = collect($phasePayloads)
            ->sum(function (array $phasePayload): float {
                return round((float) ($phasePayload['weight'] ?? 0), 2);
            });

        if (abs(round($weightTotal, 2) - 100.0) > 0.01) {
            throw ValidationException::withMessages([
                'phases' => 'Tổng trọng số của tất cả phase phải bằng 100.',
            ]);
        }
    }
}
