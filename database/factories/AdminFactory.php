<?php

namespace Database\Factories;

use App\Models\Admin;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Admin> */
class AdminFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= 'Yonetici12345',
            'role' => 'staff',
            'is_active' => true,
        ];
    }

    public function super(): static
    {
        return $this->state(['role' => 'super']);
    }
}
