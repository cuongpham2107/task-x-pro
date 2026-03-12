<?php

namespace App\Services\Users;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

class UserService
{
    public function __construct(
        private readonly UserQueryService $queryService,
        private readonly UserMutationService $mutationService,
    ) {}

    /**
     * Lay du lieu danh sach user cho man hinh index.
     */
    public function paginateForIndex(?User $actor, array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        if ($actor) {
            Gate::forUser($actor)->authorize('viewAny', User::class);
        }

        return $this->queryService->paginateForIndex($filters, $perPage);
    }

    /**
     * Lay chi tiet user cho man hinh sua.
     */
    public function findForEdit(User $actor, int $userId): User
    {
        $targetUser = $this->queryService->findForEdit($userId);

        Gate::forUser($actor)->authorize('view', $targetUser);

        return $targetUser;
    }

    /**
     * Lay du lieu tong quan cho card thong ke user.
     *
     * @return array{
     *     total_users: int,
     *     active_users: int,
     *     on_leave_users: int,
     *     resigned_users: int
     * }
     */
    public function summaryStats(?User $actor): array
    {
        if ($actor) {
            Gate::forUser($actor)->authorize('viewAny', User::class);
        }

        return $this->queryService->summaryStats();
    }

    /**
     * Lay danh sach du an ma user tham gia (Leader/PIC/Co-PIC).
     */
    public function getParticipatingProjects(User $actor, int $userId, int $limit = 4): \Illuminate\Support\Collection
    {
        if ($actor->id !== $userId) {
            Gate::forUser($actor)->authorize('view', User::class);
        }

        return $this->queryService->getParticipatingProjects($userId, $limit);
    }

    /**
     * Lay danh sach task gan day cua user (PIC/Co-PIC).
     */
    public function getRecentTasks(User $actor, int $userId, int $limit = 5): \Illuminate\Support\Collection
    {
        if ($actor->id !== $userId) {
            Gate::forUser($actor)->authorize('view', User::class);
        }

        return $this->queryService->getRecentTasks($userId, $limit);
    }

    /**
     * Tra ve option cho form user.
     */
    public function formOptions(): array
    {
        return $this->queryService->formOptions();
    }

    /**
     * Tao user moi.
     */
    public function create(User $actor, array $attributes): User
    {
        return $this->mutationService->create($actor, $attributes);
    }

    /**
     * Cap nhat thong tin user.
     */
    public function update(User $actor, User $targetUser, array $attributes): User
    {
        return $this->mutationService->update($actor, $targetUser, $attributes);
    }

    /**
     * Xoa user.
     */
    public function delete(User $actor, User $targetUser): void
    {
        $this->mutationService->delete($actor, $targetUser);
    }
}
