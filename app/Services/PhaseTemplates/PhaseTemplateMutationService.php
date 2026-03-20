<?php

namespace App\Services\PhaseTemplates;

use App\Models\PhaseTemplate;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class PhaseTemplateMutationService
{
    /**
     * Tao phase template moi.
     */
    public function create(User $actor, array $attributes): PhaseTemplate
    {
        Gate::forUser($actor)->authorize('create', PhaseTemplate::class);

        return PhaseTemplate::query()->create(
            $this->normalizedAttributes($attributes)
        );
    }

    /**
     * Cap nhat phase template.
     */
    public function update(User $actor, PhaseTemplate $phaseTemplate, array $attributes): PhaseTemplate
    {
        Gate::forUser($actor)->authorize('update', $phaseTemplate);

        $phaseTemplate->fill($this->normalizedAttributes($attributes));
        $phaseTemplate->save();

        return $phaseTemplate->refresh();
    }

    /**
     * Xoa phase template.
     */
    public function delete(User $actor, PhaseTemplate $phaseTemplate): void
    {
        Gate::forUser($actor)->authorize('delete', $phaseTemplate);
        $phaseTemplate->delete();
    }

    /**
     * Chuan hoa payload truoc khi ghi DB.
     *
     * @return array{
     *     project_type: string,
     *     phase_name: string,
     *     phase_description: ?string,
     *     order_index: int,
     *     default_weight: float,
     *     default_duration_days: ?int,
     *     is_active: bool
     * }
     */
    private function normalizedAttributes(array $attributes): array
    {
        $durationDays = $attributes['default_duration_days'] ?? null;

        return [
            'project_type' => trim((string) ($attributes['project_type'] ?? '')),
            'phase_name' => trim((string) ($attributes['phase_name'] ?? '')),
            'phase_description' => $this->nullableTrimmedString($attributes['phase_description'] ?? null),
            'order_index' => (int) ($attributes['order_index'] ?? 1),
            'default_weight' => (int) ($attributes['default_weight'] ?? 0),
            'default_duration_days' => $durationDays !== null && $durationDays !== ''
                ? (int) $durationDays
                : null,
            'is_active' => (bool) ($attributes['is_active'] ?? true),
        ];
    }

    private function nullableTrimmedString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string !== '' ? $string : null;
    }
}
