<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $lastNames = ['Nguyen', 'Tran', 'Le', 'Pham', 'Hoang', 'Phan', 'Vu', 'Dang'];
        $middleNames = ['Minh', 'Gia', 'Hoang', 'Thanh', 'Huu', 'Ngoc', 'Duc', 'Bao'];
        $firstNames = ['Quan', 'Anh', 'Tuan', 'Linh', 'Huyen', 'Lan', 'Nam', 'Khanh'];

        $fullName = fake()->randomElement($lastNames).' '.fake()->randomElement($middleNames).' '.fake()->randomElement($firstNames);
        $emailPrefix = Str::slug(Str::ascii($fullName), '.');

        return [
            'employee_code' => 'NV'.str_pad((string) fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'name' => $fullName,
            'email' => fake()->unique()->numerify($emailPrefix.'##@taskxpro.vn'),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'avatar' => fake()->optional(0.6)->passthrough('https://ui-avatars.com/api/?name='.urlencode($fullName).'&color=7F9CF5&background=EBF4FF'),
            'phone' => '0'.fake()->numerify('#########'),
            'job_title' => fake()->randomElement([
                'Truong nhom du an',
                'Chuyen vien van hanh',
                'Ky su he thong',
                'Chuyen vien nghiep vu',
                'Dieu phoi vien',
            ]),
            'department_id' => null,
            'status' => fake()->randomElement(['active', 'active', 'active', 'on_leave']),
            'telegram_id' => fake()->optional(0.5)->numerify('chat_########'),
            'remember_token' => Str::random(10),
        ];
    }

    public function ceo(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'active',
            'job_title' => 'Tong giam doc',
        ]);
    }

    public function leader(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'active',
            'job_title' => fake()->randomElement([
                'Truong phong',
                'Project Leader',
                'Team Lead',
            ]),
        ]);
    }

    public function pic(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'active',
            'job_title' => fake()->randomElement([
                'Chuyen vien',
                'Nhan vien van hanh',
                'Ky su trien khai',
            ]),
        ]);
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'email_verified_at' => null,
        ]);
    }
}
