<?php

namespace App\Services\Departments;

use App\Enums\DepartmentStatus;
use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class DepartmentMutationService
{
    /**
     * Tao phong ban moi.
     */
    public function create(User $actor, array $attributes): Department
    {
        Gate::forUser($actor)->authorize('create', Department::class);

        $department = Department::query()->create(
            $this->normalizedAttributes($attributes)
        );

        return $department->load('head:id,name,email,avatar');
    }

    /**
     * Cap nhat thong tin phong ban.
     */
    public function update(User $actor, Department $department, array $attributes): Department
    {
        Gate::forUser($actor)->authorize('update', $department);

        $department->fill($this->normalizedAttributes($attributes));
        $department->save();

        return $department->load('head:id,name,email,avatar');
    }

    /**
     * Xoa phong ban theo policy hien tai.
     */
    public function delete(User $actor, Department $department): void
    {
        Gate::forUser($actor)->authorize('delete', $department);
        $department->delete();
    }

    /**
     * Chuan hoa payload truoc khi ghi DB.
     *
     * @return array{
     *     code: string,
     *     name: string,
     *     head_user_id: ?int,
     *     status: string
     * }
     */
    private function normalizedAttributes(array $attributes): array
    {
        $status = trim((string) ($attributes['status'] ?? DepartmentStatus::Active->value));
        $headUserId = $attributes['head_user_id'] ?? null;

        if (! in_array($status, DepartmentStatus::values(), true)) {
            $status = DepartmentStatus::Active->value;
        }

        return [
            'code' => strtoupper(trim((string) ($attributes['code'] ?? ''))),
            'name' => trim((string) ($attributes['name'] ?? '')),
            'head_user_id' => $headUserId !== null && $headUserId !== ''
                ? (int) $headUserId
                : null,
            'status' => $status,
        ];
    }
}
