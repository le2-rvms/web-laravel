<?php

namespace Database\Factories\Sale;

use App\Enum\Sale\ScPaymentDayType;
use App\Enum\Sale\ScRentalType;
use App\Enum\Sale\SctSctStatus;
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
            'rental_type'           => $rental_type = ScRentalType::label_key_random(),
            'payment_day_type'      => ($payment_day_type = ScRentalType::LONG_TERM == $rental_type ? ScPaymentDayType::label_key_random() : null),
            'contract_number'       => strtoupper($this->faker->unique()->bothify('CN##########')),
            'installments'          => $installments = $this->faker->numberBetween(1, 60),
            'deposit_amount'        => $this->faker->optional()->randomFloat(2, 100, 10000),
            'management_fee_amount' => $this->faker->optional()->randomFloat(2, 50, 5000),
            'rent_amount'           => $this->faker->randomFloat(2, 100, 5000),
            'payment_day'           => $this->faker->optional()->numberBetween(1, 28),
            'sct_status'            => SctSctStatus::label_key_random(),
        ];
    }
}
