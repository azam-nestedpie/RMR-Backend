<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        $chars = 'abcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $uid = substr(str_shuffle(str_repeat($chars, 3)), 0, 28);

        return [
            'firebase_uid' => $uid,
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'email' => $this->faker->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'company_name' => $this->faker->company(),
            'position' => $this->faker->jobTitle(),
            'is_blocked' => false,
            'is_deleted' => false,
        ];
    }

    public function migrated(): static
    {
        return $this->state(function () {
            $uid = substr(str_shuffle('abcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 28);

            return [
                'firebase_uid' => $uid,
                'password' => null,
            ];
        });
    }

    public function withPassword(string $password = 'password'): static
    {
        return $this->state(['password' => Hash::make($password)]);
    }

    public function blocked(): static
    {
        return $this->state(['is_blocked' => true]);
    }

    public function deleted(): static
    {
        return $this->state(['is_deleted' => true]);
    }
}
