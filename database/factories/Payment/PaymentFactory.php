<?php

namespace Database\Factories\Payment;

use App\Enum\Payment\RpIsValid;
use App\Enum\Payment\RpPayStatus;
use App\Enum\Payment\RpPtId;
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
            'pt_id'             => RpPtId::label_key_random(),
            'should_pay_date'   => $should_pay_date = fake_current_period_d(),
            'should_pay_amount' => $amount          = $this->faker->randomNumber(4, true),
            'pay_status'        => RpPayStatus::label_key_random(),
            'actual_pay_date'   => $should_pay_date,
            'actual_pay_amount' => $this->faker->boolean(10) ? $amount * 0.9 : $amount,
            'rp_remark'         => null,
            'is_valid'          => RpIsValid::label_key_random(),
        ];
    }
}
