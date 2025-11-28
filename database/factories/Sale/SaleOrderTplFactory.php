<?php

namespace Database\Factories\Sale;

use App\Enum\Sale\SoPaymentDayType;
use App\Enum\Sale\SoRentalType;
use App\Enum\Sale\SotSotStatus;
use App\Models\Sale\SaleOrderTpl;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaleOrderTpl>
 */
class SaleOrderTplFactory extends Factory
{
    public function definition(): array
    {
        return [
            'so_type'               => $so_type = SoRentalType::label_key_random(),
            'payment_day_type'      => ($payment_day_type = SoRentalType::LONG_TERM == $so_type ? SoPaymentDayType::label_key_random() : null),
            'contract_number'       => strtoupper($this->faker->unique()->bothify('CN##########')),
            'installments'          => $installments = $this->faker->numberBetween(1, 60),
            'deposit_amount'        => $this->faker->optional()->randomFloat(2, 100, 10000),
            'management_fee_amount' => $this->faker->optional()->randomFloat(2, 50, 5000),
            'rent_amount'           => $this->faker->randomFloat(2, 100, 5000),
            'payment_day'           => $this->faker->optional()->numberBetween(1, 28),
            'sot_status'            => SotSotStatus::label_key_random(),
        ];
    }
}
