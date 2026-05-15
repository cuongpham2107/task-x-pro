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

        // Assign members if provided
        if (array_key_exists('member_ids', $attributes)) {
            $memberIds = array_filter(array_map('intval', (array) $attributes['member_ids']));
            if (! empty($memberIds)) {
                User::query()->whereIn('id', $memberIds)->update(['department_id' => $department->id]);
            }
        }

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

        // If member_ids provided, sync department assignment
        if (array_key_exists('member_ids', $attributes)) {
            $memberIds = array_filter(array_map('intval', (array) $attributes['member_ids']));

            // Detach users previously assigned to this department but not in new list
            User::query()
                ->where('department_id', $department->id)
                ->whereNotIn('id', $memberIds ?: [0])
                ->update(['department_id' => null]);

            // Assign selected users to this department
            if (! empty($memberIds)) {
                User::query()->whereIn('id', $memberIds)->update(['department_id' => $department->id]);
            }
        }

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
