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

        return $user->load('department:id,name');
    }

    /**
     * Cap nhat thong tin user.
     */
    public function update(User $actor, User $targetUser, array $attributes): User
    {
        Gate::forUser($actor)->authorize('update', $targetUser);

        $targetUser->fill($this->normalizedAttributes($attributes, true));
        $targetUser->save();

        return $targetUser->load('department:id,name');
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

        $targetUser->delete();
    }

    /**
     * Chuan hoa payload truoc khi ghi DB.
     */
    private function normalizedAttributes(array $attributes, bool $isUpdate): array
    {
        $status = trim((string) ($attributes['status'] ?? UserStatus::Active->value));
        if (! in_array($status, UserStatus::values(), true)) {
            $status = UserStatus::Active->value;
        }

        $payload = [
            'name' => trim((string) ($attributes['name'] ?? '')),
            'email' => strtolower(trim((string) ($attributes['email'] ?? ''))),
            'phone' => $this->nullableTrimmedString($attributes['phone'] ?? null),
            'job_title' => $this->nullableTrimmedString($attributes['job_title'] ?? null),
            'department_id' => $attributes['department_id'] !== null && $attributes['department_id'] !== ''
                ? (int) $attributes['department_id']
                : null,
            'status' => $status,
            'telegram_id' => $this->nullableTrimmedString($attributes['telegram_id'] ?? null),
        ];

        if (isset($attributes['employee_code'])) {
            $payload['employee_code'] = trim((string) $attributes['employee_code']);
        }

        if (isset($attributes['avatar_path'])) {
            $payload['avatar'] = $attributes['avatar_path']; // Model uses 'avatar'
        }

        $password = (string) ($attributes['password'] ?? '');
        if ((! $isUpdate && $password !== '') || ($isUpdate && $password !== '')) {
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
