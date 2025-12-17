<?php

namespace Database\Factories\Payment;

use App\Enum\Payment\PIsValid;
use App\Enum\Payment\PPayStatus;
use App\Enum\Payment\PPtId;
use App\Models\Payment\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'p_pt_id'             => PPtId::label_key_random(),
            'p_should_pay_date'   => $should_pay_date = fake_current_period_d(),
            'p_should_pay_amount' => $amount          = $this->faker->randomNumber(4, true),
            'p_pay_status'        => PPayStatus::label_key_random(),
            'p_actual_pay_date'   => $should_pay_date,
            'p_actual_pay_amount' => $this->faker->boolean(10) ? $amount * 0.9 : $amount,
            'p_remark'            => null,
            'p_is_valid'          => PIsValid::label_key_random(),
        ];
    }
}
