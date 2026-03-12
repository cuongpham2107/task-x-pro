<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
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
            'email' => $socialUser->getEmail(),
            'password' => Hash::make(Str::random(24)),
            $column => $socialUser->getId(),
            'status' => 'active',
        ]);
    }
}
