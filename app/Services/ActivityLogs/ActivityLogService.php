<?php

namespace App\Services\ActivityLogs;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

class ActivityLogService
{
    public function __construct(
        private readonly ActivityLogQueryService $queryService,
    ) {}

    /**
     * Lay du lieu danh sach activity log cho man hinh index.
     */
    public function paginateForIndex(?User $actor, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        if ($actor) {
            Gate::forUser($actor)->authorize('viewAny', ActivityLog::class);
        }

        return $this->queryService->paginateForIndex($filters, $perPage);
    }

    /**
     * Tra ve option cho bo loc activity log.
     */
    public function filterOptions(?User $actor): array
    {
        if ($actor) {
            Gate::forUser($actor)->authorize('viewAny', ActivityLog::class);
        }

        return $this->queryService->filterOptions();
    }
}
