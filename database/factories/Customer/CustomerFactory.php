<?php

namespace Database\Factories\Customer;

use App\Enum\Customer\CuType;
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
            'cu_type'                 => CuType::label_key_random(),
            'cu_contact_name'         => $this->faker->unique()->name(),
            'cu_contact_phone'        => $this->faker->unique()->phoneNumber(),
            'cu_contact_email'        => $this->faker->unique()->safeEmail(),
            'cu_contact_wechat'       => $this->faker->optional(0.8)->userName(),
            'cu_contact_live_city'    => $this->faker->optional(0.8)->city(),
            'cu_contact_live_address' => $this->faker->optional(0.8)->address(),
            'cu_remark'               => null,

            'cu_sales_manager'  => $this->faker->boolean(70) ? $this->randomAdminID(1) : null,
            'cu_driver_manager' => $this->faker->boolean(70) ? $this->randomAdminID(1) : null,
        ];
    }

    /**
     * 指定为个人客户.
     */
    public function individual(): static
    {
        return $this->state(fn () => [
            'cu_type' => CuType::INDIVIDUAL,
        ]);
    }

    /**
     * 指定为企业客户.
     */
    public function company(): static
    {
        return $this->state(fn () => [
            'cu_type' => CuType::COMPANY,
        ]);
    }
}
