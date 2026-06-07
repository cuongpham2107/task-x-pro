<?php

namespace App\Services\Users;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UserMutationService
{
    /**
     * Tao user moi.
     */
    public function create(User $actor, array $attributes): User
    {
        Gate::forUser($actor)->authorize('create', User::class);

        $user = User::query()->create(
            $this->normalizedAttributes($attributes, false)
        );

        if (isset($attributes['role_ids'])) {
            $user->syncRoles(array_map('intval', (array) $attributes['role_ids']));
        }

        return $user->load(['department:id,name', 'roles:id,name']);
    }

    /**
     * Cap nhat thong tin user.
     */
    public function update(User $actor, User $targetUser, array $attributes): User
    {
        Gate::forUser($actor)->authorize('update', $targetUser);

        $targetUser->fill($this->normalizedAttributes($attributes, true, $targetUser));
        $targetUser->save();

        if (isset($attributes['role_ids'])) {
            $targetUser->syncRoles(array_map('intval', (array) $attributes['role_ids']));
        }

        return $targetUser->load(['department:id,name', 'roles:id,name']);
    }

    /**
     * Xoa user.
     */
    public function delete(User $actor, User $targetUser): void
    {
        Gate::forUser($actor)->authorize('delete', $targetUser);

        if ((int) $actor->id === (int) $targetUser->id) {
            throw ValidationException::withMessages([
                'delete' => 'Bạn không thể xóa chính tài khoản đang đăng nhập.',
            ]);
        }

        if ($this->hasBlockingReferences($targetUser)) {
            throw ValidationException::withMessages([
                'delete' => 'Không thể xóa người dùng này vì còn dữ liệu tham chiếu.',
            ]);
        }

        $targetUser->delete();
    }

    private function hasBlockingReferences(User $targetUser): bool
    {
        return $targetUser->createdProjects()->exists()
            || $targetUser->assignedProjectLeaders()->exists()
            || $targetUser->picTasks()->exists()
            || $targetUser->createdTasks()->exists()
            || $targetUser->uploadedTaskAttachments()->exists()
            || $targetUser->createdSlaConfigs()->exists()
            || $targetUser->approvalLogs()->exists()
            || $targetUser->uploadedDocuments()->exists()
            || $targetUser->uploadedDocumentVersions()->exists();
    }

    /**
     * Chuan hoa payload truoc khi ghi DB.
     */
    private function normalizedAttributes(array $attributes, bool $isUpdate, ?User $baseUser = null): array
    {
        $status = array_key_exists('status', $attributes)
            ? trim((string) $attributes['status'])
            : ($baseUser?->status?->value ?? UserStatus::Active->value);

        if (! in_array($status, UserStatus::values(), true)) {
            $status = $baseUser?->status?->value ?? UserStatus::Active->value;
        }

        $payload = [
            'name' => array_key_exists('name', $attributes)
                ? trim((string) $attributes['name'])
                : ($baseUser?->name ?? ''),
            'email' => array_key_exists('email', $attributes)
                ? strtolower(trim((string) $attributes['email']))
                : ($baseUser?->email ?? ''),
            'phone' => array_key_exists('phone', $attributes)
                ? $this->nullableTrimmedString($attributes['phone'])
                : $baseUser?->phone,
            'job_title' => array_key_exists('job_title', $attributes)
                ? $this->nullableTrimmedString($attributes['job_title'])
                : $baseUser?->job_title,
            'department_id' => array_key_exists('department_id', $attributes)
                ? ($attributes['department_id'] !== null && $attributes['department_id'] !== ''
                    ? (int) $attributes['department_id']
                    : null)
                : $baseUser?->department_id,
            'status' => $status,
            'telegram_id' => array_key_exists('telegram_id', $attributes)
                ? $this->nullableTrimmedString($attributes['telegram_id'])
                : $baseUser?->telegram_id,
        ];

        if (array_key_exists('employee_code', $attributes)) {
            $payload['employee_code'] = trim((string) $attributes['employee_code']);
        }

        if (array_key_exists('avatar_path', $attributes)) {
            $payload['avatar'] = $attributes['avatar_path']; // Model uses 'avatar'
        }

        $password = (string) ($attributes['password'] ?? '');
        if ($password !== '') {
            $payload['password'] = $password;
        }

        return $payload;
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
