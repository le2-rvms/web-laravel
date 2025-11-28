<?php

namespace Database\Factories\Customer;

use App\Enum\Customer\CuCuType;
use App\Models\Customer\Customer;
use Database\Factories\UsesJsonFixture;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    use UsesJsonFixture;

    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'cu_type'              => CuCuType::label_key_random(),
            'contact_name'         => $this->faker->unique()->name(),
            'contact_phone'        => $this->faker->unique()->phoneNumber(),
            'contact_email'        => $this->faker->unique()->safeEmail(),
            'contact_wechat'       => $this->faker->optional(0.8)->userName(),
            'contact_live_city'    => $this->faker->optional(0.8)->city(),
            'contact_live_address' => $this->faker->optional(0.8)->address(),
            'cu_remark'            => null,

            'sales_manager'  => $this->faker->boolean(70) ? $this->randomAdminID(1) : null,
            'driver_manager' => $this->faker->boolean(70) ? $this->randomAdminID(1) : null,
        ];
    }

    /**
     * 指定为个人客户.
     */
    public function individual(): static
    {
        return $this->state(fn () => [
            'cu_type' => CuCuType::INDIVIDUAL,
        ]);
    }

    /**
     * 指定为企业客户.
     */
    public function company(): static
    {
        return $this->state(fn () => [
            'cu_type' => CuCuType::COMPANY,
        ]);
    }
}
