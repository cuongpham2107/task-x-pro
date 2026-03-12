<?php

namespace App\Services\PhaseTemplates;

use App\Models\PhaseTemplate;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

class PhaseTemplateService
{
    public function __construct(
        private readonly PhaseTemplateQueryService $queryService,
        private readonly PhaseTemplateMutationService $mutationService,
    ) {}

    /**
     * Lay danh sach phase template cho man hinh index.
     */
    public function paginateForIndex(?User $actor, array $filters = [], int $perPage = 12): LengthAwarePaginator
    {
        if ($actor) {
            Gate::forUser($actor)->authorize('viewAny', PhaseTemplate::class);
        }

        return $this->queryService->paginateForIndex($filters, $perPage);
    }

    /**
     * Lay phase template cho man hinh sua.
     */
    public function findForEdit(User $actor, int $templateId): PhaseTemplate
    {
        $phaseTemplate = $this->queryService->findForEdit($templateId);

        Gate::forUser($actor)->authorize('view', $phaseTemplate);

        return $phaseTemplate;
    }

    /**
     * @return array{
     *     total_templates: int,
     *     active_templates: int,
     *     project_types: int
     * }
     */
    public function summaryStats(?User $actor): array
    {
        if ($actor) {
            Gate::forUser($actor)->authorize('viewAny', PhaseTemplate::class);
        }

        return $this->queryService->summaryStats();
    }

    /**
     * Tra ve du lieu option cho form phase template.
     */
    public function formOptions(?User $actor): array
    {
        if ($actor) {
            Gate::forUser($actor)->authorize('viewAny', PhaseTemplate::class);
        }

        return $this->queryService->formOptions();
    }

    /**
     * Tao phase template.
     */
    public function create(User $actor, array $attributes): PhaseTemplate
    {
        return $this->mutationService->create($actor, $attributes);
    }

    /**
     * Cap nhat phase template.
     */
    public function update(User $actor, PhaseTemplate $phaseTemplate, array $attributes): PhaseTemplate
    {
        return $this->mutationService->update($actor, $phaseTemplate, $attributes);
    }

    /**
     * Xoa phase template.
     */
    public function delete(User $actor, PhaseTemplate $phaseTemplate): void
    {
        $this->mutationService->delete($actor, $phaseTemplate);
    }
}
