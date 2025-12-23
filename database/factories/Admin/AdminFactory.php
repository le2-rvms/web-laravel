<?php

namespace Database\Factories\Admin;

use App\Enum\Admin\AUserType;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminRole;
use Database\Factories\UsesJsonFixture;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<Admin>
 */
class AdminFactory extends Factory
{
    use UsesJsonFixture;

    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    protected $model = Admin::class;

    public function definition(): array
    {
        return [
            'name'              => $this->faker->name(),
            'email'             => $this->faker->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password'          => static::$password ??= Hash::make('password'),
            'remember_token'    => Str::random(10),
            'a_user_type'       => AUserType::COMMON,
            'a_expires_at'      => $this->faker->boolean(20) ? $this->faker->dateTimeBetween('now', '+1 years') : null,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Admin $admin) {
            // ...
        })->afterCreating(function (Admin $admin) {
            // ...
            while (true) {
                /** @var AdminRole $adminRole */
                $adminRole = $this->randomModelFromPools(AdminRole::class);

                $super_role_name = config('setting.super_role.name');
                if ($adminRole->name !== $super_role_name) {
                    break;
                }
            }

            $admin->assignRole($adminRole);
        });
    }
}
