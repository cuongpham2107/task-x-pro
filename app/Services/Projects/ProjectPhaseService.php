<?php

namespace App\Services\Projects;

use App\Models\Phase;
use App\Models\PhaseTemplate;
use App\Models\Project;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class ProjectPhaseService
{
    public function createPhasesFromTemplate(Project $project): void
    {
        $templates = PhaseTemplate::query()
            ->where('project_type', $project->type)
            ->where('is_active', true)
            ->orderBy('order_index')
            ->get();

        if ($templates->isEmpty()) {
            throw ValidationException::withMessages([
                'type' => 'Chưa cấu hình phase template cho loại project này ('.$project->type->label().').',
            ]);
        }

        $projectStart = $project->start_date !== null ? Carbon::parse($project->start_date)->startOfDay() : null;
        $projectEnd = $project->end_date !== null ? Carbon::parse($project->end_date)->startOfDay() : null;

        $durations = [];
        if ($projectStart && $projectEnd) {
            // Logic chia tỷ lệ (Scaling)
            $totalProjectDays = $projectStart->diffInDays($projectEnd) + 1;
            $totalTemplateDays = $templates->sum('default_duration_days');

            if ($totalTemplateDays > 0) {
                foreach ($templates as $template) {
                    $durations[] = (int) floor($template->default_duration_days * $totalProjectDays / $totalTemplateDays);
                }
            } else {
                // Chia đều nếu template không có ngày
                $count = $templates->count();
                foreach ($templates as $index) {
                    $durations[] = (int) floor($totalProjectDays / $count);
                }
            }

            // Xử lý phần dư (Remainder) để đảm bảo khớp khít ngày cuối
            $currentSum = array_sum($durations);
            $remainder = $totalProjectDays - $currentSum;
            for ($i = 0; $i < $remainder; $i++) {
                $durations[$i % count($durations)]++;
            }
        }

        $phasePayloads = [];
        $cursorStart = $projectStart ? $projectStart->copy() : null;

        foreach ($templates as $index => $template) {
            $startDate = $cursorStart?->toDateString();
            $endDate = null;

            if ($cursorStart !== null) {
                if ($projectEnd !== null) {
                    // Dùng duration đã tính ở trên
                    $durationDays = max(1, $durations[$index] ?? 1);
                    $phaseEnd = $cursorStart->copy()->addDays($durationDays - 1);

                    // Đảm bảo phase cuối cùng không bao giờ vượt quá hoặc ngắn hơn project end date
                    if ($index === $templates->count() - 1) {
                        $phaseEnd = $projectEnd->copy();
                    }

                    $endDate = $phaseEnd->toDateString();
                    $cursorStart = $phaseEnd->copy()->addDay()->startOfDay();
                } elseif ($template->default_duration_days !== null) {
                    // Logic cộng dồn cũ nếu không có Project End Date
                    $durationDays = max(1, (int) $template->default_duration_days);
                    $phaseEnd = $cursorStart->copy()->addDays($durationDays - 1);
                    $endDate = $phaseEnd->toDateString();
                    $cursorStart = $phaseEnd->copy()->addDay()->startOfDay();
                }
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
        if ($phasePayloads !== []) {
            $this->assertValidPhaseWeightTotal($phasePayloads);
        }

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
                'phases' => 'Tổng trọng số của tất cả phase template phải bằng 100%.',
            ]);
        }
    }
}
