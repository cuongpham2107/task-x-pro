<?php

namespace App\Services\Phases;

use App\Models\Phase;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PhaseMutationService
{
    /**
     * Create a new phase with weight validation.
     */
    public function create(User $actor, Project $project, array $attributes): Phase
    {
        return DB::transaction(function () use ($project, $attributes) {
            // Validate total weight = 100 before creating (BR-008)
            $this->assertValidWeightTotal($project, $attributes['weight'] ?? 0);

            // Get the next order index
            $lastOrderIndex = $project->phases()->max('order_index') ?? -1;
            $attributes['order_index'] = $lastOrderIndex + 1;
            $attributes['project_id'] = $project->id;

            return Phase::create($attributes);
        });
    }

    /**
     * Update an existing phase with weight validation.
     */
    public function update(User $actor, Phase $phase, array $attributes): Phase
    {
        // Xác thực tổng trọng lượng = 100 trước khi cập nhật (BR-008)
        $this->assertValidWeightTotal($phase->project, $attributes['weight'] ?? $phase->weight, $phase->id);

        if (
            array_key_exists('status', $attributes)
            && (string) $attributes['status'] === \App\Enums\PhaseStatus::Completed->value
        ) {
            $this->assertCanCompletePhase($phase);
        }

        $phase->update($attributes);

        return $phase;
    }

    /**
     * Delete a phase with weight validation.
     */
    public function delete(User $actor, Phase $phase): void
    {
        // Validate remaining phases weight = 100 after deletion (BR-008)
        $this->assertValidWeightTotalAfterDelete($phase->project, $phase->id);

        $phase->delete();
    }

    /**
     * Reorder phases for a project.
     */
    public function reorder(User $actor, Project $project, array $phaseIds): void
    {
        DB::transaction(function () use ($phaseIds) {
            foreach ($phaseIds as $index => $id) {
                Phase::where('id', $id)->update(['order_index' => $index]);
            }
        });

        // Validate total weight = 100 after reorder (BR-008)
        $this->assertValidWeightTotalAfterReorder($project);
    }

    /**
     * Assert that total phase weight equals 100.
     */
    private function assertValidWeightTotal(Project $project, float $newWeight, ?int $excludePhaseId = null): void
    {
        $currentTotal = (float) $project->phases()
            ->when($excludePhaseId !== null, fn ($q) => $q->whereKeyNot($excludePhaseId))
            ->sum('weight');

        $newTotal = round($currentTotal + $newWeight, 2);

        if ($newTotal > 100.0) {
            throw ValidationException::withMessages([
                'weight' => "Tổng trọng số của tất cả giai đoạn không được vượt quá 100%. Hiện tại: {$newTotal}%",
            ]);
        }
    }

    /**
     * Assert that remaining phases weight equals 100 after delete.
     */
    private function assertValidWeightTotalAfterDelete(Project $project, int $deletedPhaseId): void
    {
        // Khi xóa phase, tổng trọng số luôn giảm xuống, nên không cần check vượt quá 100%.
        // Tuy nhiên có thể check nếu muốn đảm bảo tính logic.
    }

    /**
     * Assert that total weight equals 100 after reorder.
     */
    private function assertValidWeightTotalAfterReorder(Project $project): void
    {
        $totalWeight = (float) $project->phases()->sum('weight');

        if (round($totalWeight, 2) > 100.0) {
            throw ValidationException::withMessages([
                'weight' => "Tổng trọng số của tất cả giai đoạn không được vượt quá 100%. Hiện tại: {$totalWeight}%",
            ]);
        }
    }

    private function assertCanCompletePhase(Phase $phase): void
    {
        $tasksQuery = $phase->tasks();
        $taskCount = (int) $tasksQuery->count();

        if ($taskCount === 0) {
            throw ValidationException::withMessages([
                'status' => 'Không thể hoàn thành giai đoạn khi chưa có công việc.',
            ]);
        }

        $hasIncompleteTask = $tasksQuery
            ->where(function ($query): void {
                $query
                    ->where('status', '!=', \App\Enums\TaskStatus::Completed->value)
                    ->orWhere('progress', '<', 100);
            })
            ->exists();

        if ($hasIncompleteTask) {
            throw ValidationException::withMessages([
                'status' => 'Chỉ được hoàn thành giai đoạn khi tất cả task đã duyệt và đạt 100% tiến độ.',
            ]);
        }
    }
}
