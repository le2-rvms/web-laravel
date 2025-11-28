<?php

namespace Database\Factories\Sale;

use App\Enum\Sale\SoOrderStatus;
use App\Enum\Sale\SoPaymentDay_Month;
use App\Enum\Sale\SoPaymentDayType;
use App\Enum\Sale\SoRentalType;
use App\Models\Sale\SaleOrder;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaleOrder>
 */
class SaleOrderFactory extends Factory
{
    public function definition(): array
    {
        $rental_type = SoRentalType::label_key_random();

        $rental_start = fake_current_period_d();

        $free_days = $this->faker->numberBetween(1, 4);

        $rent_amount = $this->faker->randomFloat($nbMaxDecimals = 2, $min = 1000, $max = 5000);

        $management_fee_amount = $this->faker->optional()->randomFloat(2, 50, 5000);

        $deposit_amount = $this->faker->randomFloat(2, 100, 10000);

        if (SoRentalType::LONG_TERM === $rental_type) {
            $payment_day_type      = SoPaymentDayType::label_key_random();
            $payment_day_type_rule = SoPaymentDayType::interval[$payment_day_type]; // ['interval_unit']

            /** @var SoPaymentDay_Month $payment_day_class */
            $payment_day_class = SoPaymentDayType::payment_day_classes[$payment_day_type];
            $payment_day       = $payment_day_class::label_key_random();

            $installments = $this->faker->numberBetween(10, 60);

            $rental_end = Carbon::create($rental_start)->modify(sprintf('+ %d %s', $installments * $payment_day_type_rule['interval'], $payment_day_type_rule['interval_unit']));

            $rental_days = Carbon::parse($rental_start)->diffInDays($rental_end, true) + 1;

            $total_rent_amount = $installments * $rent_amount;

            $total_amount = $total_rent_amount + $deposit_amount + $management_fee_amount;
        } else {
            $rental_days = $this->faker->numberBetween(1, 30);

            $rental_end = Carbon::create($rental_start)->modify(sprintf('+ %d days', $rental_days));

            $total_rent_amount = ($rental_days - $free_days) * $rent_amount;

            $insurance_base_fee_amount       = $this->faker->randomFloat(0, 1000, 5000);
            $insurance_additional_fee_amount = $this->faker->randomFloat(0, 1000, 5000);
            $other_fee_amount                = $this->faker->randomFloat(0, 1000, 5000);

            $total_amount = $deposit_amount + $management_fee_amount + $total_rent_amount + $insurance_base_fee_amount + $insurance_additional_fee_amount + $other_fee_amount;
        }
        $order_status = SoOrderStatus::label_key_random();

        return [
            'rental_type'                     => $rental_type,
            'payment_day_type'                => $payment_day_type ?? null,
            'contract_number'                 => strtoupper($this->faker->unique()->bothify('CN##########')),
            'free_days'                       => $free_days,
            'rental_start'                    => $rental_start,
            'installments'                    => $installments ?? null,
            'rental_days'                     => $rental_days,
            'rental_end'                      => $rental_end,
            'deposit_amount'                  => $deposit_amount,
            'management_fee_amount'           => $management_fee_amount,
            'rent_amount'                     => $rent_amount,
            'payment_day'                     => $payment_day ?? null,
            'total_rent_amount'               => $total_rent_amount,
            'insurance_base_fee_amount'       => $insurance_base_fee_amount ?? null,
            'insurance_additional_fee_amount' => $insurance_additional_fee_amount ?? null,
            'other_fee_amount'                => $other_fee_amount ?? null,
            'total_amount'                    => $total_amount,
            'order_status'                    => $order_status,
            'order_at'                        => fake_current_period_dt(),
            'signed_at'                       => SoOrderStatus::SIGNED === $order_status ? fake_current_period_dt() : null,
            'canceled_at'                     => SoOrderStatus::CANCELLED === $order_status ? fake_current_period_dt() : null,
            'completed_at'                    => SoOrderStatus::COMPLETED === $order_status ? fake_current_period_dt() : null,
            'early_termination_at'            => SoOrderStatus::EARLY_TERMINATION === $order_status ? fake_current_period_dt() : null,
        ];
    }
}
