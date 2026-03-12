<?php

namespace App\Services\SlaConfigs;

use App\Models\SlaConfig;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

class SlaConfigService
{
    public function __construct(
        private readonly SlaConfigQueryService $queryService,
        private readonly SlaConfigMutationService $mutationService,
    ) {}

    /**
     * Lay du lieu danh sach SLA cho man hinh index.
     */
    public function paginateForIndex(?User $actor, array $filters = [], int $perPage = 12): LengthAwarePaginator
    {
        if ($actor) {
            Gate::forUser($actor)->authorize('viewAny', SlaConfig::class);
        }

        return $this->queryService->paginateForIndex($filters, $perPage);
    }

    /**
     * Lay chi tiet SLA cho man hinh sua.
     */
    public function findForEdit(User $actor, int $slaConfigId): SlaConfig
    {
        $slaConfig = $this->queryService->findForEdit($slaConfigId);
        Gate::forUser($actor)->authorize('view', $slaConfig);

        return $slaConfig;
    }

    /**
     * Tra ve option cho form tao/sua SLA config.
     */
    public function formOptions(?User $actor): array
    {
        if ($actor) {
            Gate::forUser($actor)->authorize('viewAny', SlaConfig::class);
        }

        return $this->queryService->formOptions();
    }

    /**
     * Tao cau hinh SLA moi.
     */
    public function create(User $actor, array $attributes): SlaConfig
    {
        return $this->mutationService->create($actor, $attributes);
    }

    /**
     * Cap nhat cau hinh SLA.
     */
    public function update(User $actor, SlaConfig $slaConfig, array $attributes): SlaConfig
    {
        return $this->mutationService->update($actor, $slaConfig, $attributes);
    }

    /**
     * Xoa cau hinh SLA.
     */
    public function delete(User $actor, SlaConfig $slaConfig): void
    {
        $this->mutationService->delete($actor, $slaConfig);
    }
}
