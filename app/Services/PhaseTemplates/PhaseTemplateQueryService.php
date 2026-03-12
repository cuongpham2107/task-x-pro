<?php

namespace App\Services\PhaseTemplates;

use App\Enums\ProjectType;
use App\Models\PhaseTemplate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class PhaseTemplateQueryService
{
    /**
     * Lay danh sach phase template cho man hinh index.
     */
    public function paginateForIndex(array $filters = [], int $perPage = 12): LengthAwarePaginator
    {
        $query = PhaseTemplate::query();

        $this->applyFilters($query, $filters);

        $sortBy = $filters['sort'] ?? 'order_index';
        $sortDir = $filters['dir'] ?? 'asc';
        $allowedSorts = ['order_index', 'phase_name', 'default_weight', 'default_duration_days'];
        $sortColumn = in_array($sortBy, $allowedSorts, true) ? $sortBy : 'order_index';
        $sortDirection = $sortDir === 'desc' ? 'desc' : 'asc';

        return $query
            ->orderBy('project_type')
            ->orderBy($sortColumn, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Lay chi tiet phase template cho man hinh sua.
     */
    public function findForEdit(int $templateId): PhaseTemplate
    {
        return PhaseTemplate::query()->findOrFail($templateId);
    }

    /**
     * @return array{
     *     total_templates: int,
     *     active_templates: int,
     *     project_types: int
     * }
     */
    public function summaryStats(): array
    {
        return [
            'total_templates' => PhaseTemplate::query()->count(),
            'active_templates' => PhaseTemplate::query()->where('is_active', true)->count(),
            'project_types' => PhaseTemplate::query()->distinct('project_type')->count('project_type'),
        ];
    }

    /**
     * @return array{
     *     project_type_labels: array<string, string>
     * }
     */
    public function formOptions(): array
    {
        return [
            'project_type_labels' => ProjectType::options(),
        ];
    }

    /**
     * Ap dung filter tim kiem cho danh sach phase template.
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('phase_name', 'like', "%{$search}%")
                    ->orWhere('phase_description', 'like', "%{$search}%");
            });
        }

        $projectType = trim((string) ($filters['project_type'] ?? ''));
        if ($projectType !== '') {
            $query->where('project_type', $projectType);
        }
    }
}
