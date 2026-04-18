<?php

namespace Database\Factories\Sale;

use App\Enum\SaleContract\ScPaymentPeriod;
use App\Enum\SaleContract\ScRentalType;
use App\Enum\SaleContract\SctStatus;
use App\Models\Sale\SaleContractTpl;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaleContractTpl>
 */
class SaleContractTplFactory extends Factory
{
    public function definition(): array
    {
        return [
            'sc_rental_type'           => $rental_type = ScRentalType::label_key_random(),
            'sc_payment_period'        => ($sc_payment_period = ScRentalType::LONG_TERM == $rental_type ? ScPaymentPeriod::label_key_random() : null),
            'sc_no_prefix'             => strtoupper($this->faker->unique()->bothify('##')),
            'sc_installments'          => $installments = $this->faker->numberBetween(1, 60),
            'sc_deposit_amount'        => $this->faker->optional()->randomFloat(2, 100, 10000),
            'sc_management_fee_amount' => $this->faker->optional()->randomFloat(2, 50, 5000),
            'sc_rent_amount'           => $this->faker->randomFloat(2, 100, 5000),
            'sc_payment_day'           => $this->faker->optional()->numberBetween(1, 28),
            'sct_status'               => SctStatus::label_key_random(),
        ];
    }
}
