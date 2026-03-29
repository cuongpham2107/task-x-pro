<?php

namespace App\Services\Phases;

use App\Models\Phase;
use App\Models\PhaseTemplate;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Collection;

class PhaseQueryService
{
    /**
     * Get phases for a specific project.
     *
     * @return Collection<int, Phase>
     */
    public function getForProject(Project $project, ?User $actor = null): Collection
    {
        $query = $project->phases()->orderBy('order_index');

        if ($actor && ! $actor->hasAnyRole(['super_admin', 'ceo', 'leader'])) {
            // Check if user is a project leader for this project
            $isProjectLeader = $project->projectLeaders()
                ->where('user_id', $actor->id)
                ->exists();

            if (! $isProjectLeader && $project->created_by !== $actor->id) {
                // User can only see phases where they have tasks assigned
                $query->whereHas('tasks', function ($q) use ($actor) {
                    $q->where('tasks.pic_id', $actor->id)
                        ->orWhere('tasks.created_by', $actor->id)
                        ->orWhereHas('coPics', function ($cq) use ($actor) {
                            $cq->where('users.id', $actor->id);
                        });
                });
            }
        }

        return $query->get();
    }

    /**
     * Find a phase for editing.
     */
    public function findForEdit(int $phaseId): Phase
    {
        return Phase::query()->findOrFail($phaseId);
    }

    /**
     * Get options for the phase form.
     *
     * @return array{
     *     status_labels: array<string, string>
     * }
     */
    public function formOptions(): array
    {
        return [
            'status_labels' => \App\Enums\PhaseStatus::options(),
        ];
    }

    /**
     * Build phase payloads from active PhaseTemplate rows for a given project type.
     * Returns an empty array when no templates found.
     *
     * @return array<int, array<string, mixed>>
     */
    public function payloadsFromTemplates(string $projectType): array
    {
        $templates = PhaseTemplate::query()
            ->where('project_type', $projectType)
            ->where('is_active', true)
            ->orderBy('order_index')
            ->get();

        if ($templates->isEmpty()) {
            return [];
        }

        return $templates->map(function (PhaseTemplate $t) {
            return [
                'name' => $t->phase_name,
                'description' => $t->phase_description ?? null,
                'weight' => (float) $t->default_weight,
                'order_index' => (int) $t->order_index,
                'start_date' => null,
                'end_date' => null,
                'is_template' => true,
            ];
        })->values()->all();
    }
}
