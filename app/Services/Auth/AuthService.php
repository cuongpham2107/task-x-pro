<?php

namespace App\Services\Auth;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;

class AuthService
{
    /**
     * Attempt to log the user in using credentials.
     */
    public function login(string $email, string $password, bool $remember = false): bool
    {
        return Auth::attempt(['email' => $email, 'password' => $password], $remember);
    }

    /**
     * Handle social login callback and return the user.
     */
    public function handleSocialCallback(string $driver): User
    {
        $socialUser = Socialite::driver($driver)->user();

        return $this->findOrCreateSocialUser($driver, $socialUser);
    }

    /**
     * Find or create a user based on social data.
     */
    public function findOrCreateSocialUser(string $driver, SocialiteUser $socialUser): User
    {
        $column = $driver === 'telegram' ? 'telegram_id' : "{$driver}_id";

        // Handle linking for authenticated users
        if (Auth::check()) {
            /** @var User $user */
            $user = Auth::user();

            // Check if this social ID is already linked to ANOTHER user
            $existingUser = User::query()->where($column, $socialUser->getId())
                ->where('id', '!=', $user->id)
                ->first();

            if ($existingUser) {
                throw new \Exception("Tài khoản {$driver} này đã được liên kết với một người dùng khác. Nếu đây là tài khoản của bạn, vui lòng liên hệ Admin để được hỗ trợ gộp tài khoản.");
            }

            if ($user->{$column} !== $socialUser->getId()) {
                $user->update([$column => $socialUser->getId()]);
            }

            return $user;
        }

        $user = User::query()->where($column, $socialUser->getId())
            ->orWhere('email', $socialUser->getEmail())
            ->first();

        if ($user) {
            if (! $user->{$column}) {
                $user->update([$column => $socialUser->getId()]);
            }

            return $user;
        }

        return User::create([
            'name' => $socialUser->getName() ?? $socialUser->getNickname() ?? $socialUser->getEmail(),
            'email' => $socialUser->getEmail() ?? $socialUser->getId().'@'.$driver.'.com',
            'password' => Hash::make('password'),
            $column => $socialUser->getId(),
            'status' => UserStatus::Pending,
        ]);
    }
}
