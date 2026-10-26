<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for the users table (used by tests and seeders).
 * The model has no HasFactory trait, so use UserFactory::new() directly.
 *
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'user_phone' => '059'.fake()->unique()->numerify('#######'),
            'user_full_name' => fake()->name(),
            'user_password' => null,
            'user_role' => 'user',
            'user_status' => 'active',
            'user_preferred_lang' => 'ar',
            'user_theme' => 'light',
        ];
    }

    public function storeOwner(): static
    {
        return $this->state(fn () => ['user_role' => 'store_owner']);
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['user_status' => 'suspended']);
    }

    public function deletedStatus(): static
    {
        return $this->state(fn () => ['user_status' => 'deleted']);
    }
}