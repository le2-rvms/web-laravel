<?php

namespace Database\Factories\Sale;

use App\Enum\SaleContract\ScPaymentDay_Month;
use App\Enum\SaleContract\ScPaymentPeriod;
use App\Enum\SaleContract\ScRentalType;
use App\Enum\SaleContract\ScStatus;
use App\Models\Sale\SaleContract;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaleContract>
 */
class SaleContractFactory extends Factory
{
    public function definition(): array
    {
        $rental_type = ScRentalType::label_key_random();

        $sc_start_date = fake_current_period_d();

        $free_days = $this->faker->numberBetween(1, 4);

        $rent_amount = $this->faker->randomFloat($nbMaxDecimals = 2, $min = 1000, $max = 5000);

        $management_fee_amount = $this->faker->optional()->randomFloat(2, 50, 5000);

        $deposit_amount = $this->faker->randomFloat(2, 100, 10000);

        if (ScRentalType::LONG_TERM === $rental_type) {
            $sc_payment_period      = ScPaymentPeriod::label_key_random();
            $sc_payment_period_rule = ScPaymentPeriod::interval[$sc_payment_period]; // ['interval_unit']

            /** @var ScPaymentDay_Month $payment_day_class */
            $payment_day_class = ScPaymentPeriod::payment_day_classes[$sc_payment_period];
            $payment_day       = $payment_day_class::label_key_random();

            $installments = $this->faker->numberBetween(10, 60);

            $sc_end_date = Carbon::create($sc_start_date)->modify(sprintf('+ %d %s', $installments * $sc_payment_period_rule['interval'], $sc_payment_period_rule['interval_unit']));

            $rental_days = Carbon::parse($sc_start_date)->diffInDays($sc_end_date, true) + 1;

            $total_rent_amount = $installments * $rent_amount;

            $total_amount = $total_rent_amount + $deposit_amount + $management_fee_amount;
        } else {
            $rental_days = $this->faker->numberBetween(1, 30);

            $sc_end_date = Carbon::create($sc_start_date)->modify(sprintf('+ %d days', $rental_days));

            $total_rent_amount = ($rental_days - $free_days) * $rent_amount;

            $insurance_base_fee_amount       = $this->faker->randomFloat(0, 1000, 5000);
            $insurance_additional_fee_amount = $this->faker->randomFloat(0, 1000, 5000);
            $other_fee_amount                = $this->faker->randomFloat(0, 1000, 5000);

            $total_amount = $deposit_amount + $management_fee_amount + $total_rent_amount + $insurance_base_fee_amount + $insurance_additional_fee_amount + $other_fee_amount;
        }
        $sc_status = ScStatus::label_key_random();

        return [
            'sc_rental_type'                     => $rental_type,
            'sc_payment_period'                  => $sc_payment_period ?? null,
            'sc_no'                              => strtoupper($this->faker->unique()->bothify('CN##########')),
            'sc_free_days'                       => $free_days,
            'sc_start_date'                      => $sc_start_date,
            'sc_installments'                    => $installments ?? null,
            'sc_rental_days'                     => $rental_days,
            'sc_end_date'                        => $sc_end_date,
            'sc_deposit_amount'                  => $deposit_amount,
            'sc_management_fee_amount'           => $management_fee_amount,
            'sc_rent_amount'                     => $rent_amount,
            'sc_payment_day'                     => $payment_day ?? null,
            'sc_total_rent_amount'               => $total_rent_amount,
            'sc_insurance_base_fee_amount'       => $insurance_base_fee_amount ?? null,
            'sc_insurance_additional_fee_amount' => $insurance_additional_fee_amount ?? null,
            'sc_other_fee_amount'                => $other_fee_amount ?? null,
            'sc_total_amount'                    => $total_amount,
            'sc_version'                         => 1,
            'sc_is_current_version'              => true,
            'sc_status'                          => $sc_status,
            'sc_order_at'                        => fake_current_period_dt(),
            'sc_signed_at'                       => ScStatus::SIGNED === $sc_status ? fake_current_period_dt() : null,
            'sc_canceled_at'                     => ScStatus::CANCELLED === $sc_status ? fake_current_period_dt() : null,
            'sc_completed_at'                    => ScStatus::COMPLETED === $sc_status ? fake_current_period_dt() : null,
            'sc_early_termination_at'            => ScStatus::EARLY_TERMINATION === $sc_status ? fake_current_period_dt() : null,
        ];
    }
}
