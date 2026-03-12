<?php

namespace App\Containers\AppSection\User\Data\Factories;

use App\Containers\AppSection\Authorization\Enums\Role as RoleEnum;
use App\Containers\AppSection\Authorization\Models\Role;
use App\Containers\AppSection\User\Enums\Gender;
use App\Containers\AppSection\User\Models\User;
use App\Ship\Parents\Factories\Factory as ParentFactory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @template TModel of User
 *
 * @extends ParentFactory<TModel>
 */
final class UserFactory extends ParentFactory
{
    protected static string|null $password;
    /** @var class-string<TModel> */
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => self::$password ??= Hash::make('password'),
            'email_verified_at' => now(),
            'remember_token' => Str::random(10),
            'gender' => fake()->randomElement(['male', 'female', 'unspecified']),
            'birth' => fake()->date(),
        ];
    }

    public function superAdmin(): self
    {
        return $this->afterCreating(function (User $user) {
            foreach (array_keys(config('auth.guards')) as $guard) {
                if (config("auth.guards.{$guard}.provider") !== 'users') {
                    continue;
                }

                $user->assignRole(
                    Role::findByName(RoleEnum::SUPER_ADMIN->value, $guard),
                );
            }
        });
    }

    public function unverified(): self
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function gender(Gender $gender): self
    {
        return $this->state(fn (array $attributes) => [
            'gender' => $gender,
        ]);
    }
}
