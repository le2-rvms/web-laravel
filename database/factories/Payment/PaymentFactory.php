<?php

namespace Database\Factories\Payment;

use App\Enum\Payment\PIsValid;
use App\Enum\Payment\PPayStatus;
use App\Enum\Payment\PPtId;
use App\Enum\SaleContract\ScStatus;
use App\Models\Payment\Payment;
use App\Models\Sale\SaleContract;
use Carbon\Carbon;
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

    public function mockDataPlan(array $payment, SaleContract $saleContract, Carbon $periodEnd): static
    {
        return $this->state(function () use ($payment, $saleContract, $periodEnd) {
            $amount     = (float) ($payment['p_should_pay_amount'] ?? 0);
            $dueDate    = Carbon::parse($payment['p_should_pay_date'] ?? $saleContract->sc_start_date);
            $attributes = [
                'p_pt_id'             => $payment['p_pt_id'],
                'p_should_pay_date'   => $dueDate->toDateString(),
                'p_should_pay_amount' => $amount,
                'p_pay_status'        => PPayStatus::UNPAID,
                'p_actual_pay_date'   => null,
                'p_actual_pay_amount' => null,
                'p_remark'            => $payment['p_remark'] ?? null,
                'p_is_valid'          => PIsValid::VALID,
            ];

            if (ScStatus::CANCELLED === $saleContract->sc_status->value) {
                return array_merge($attributes, ['p_pay_status' => PPayStatus::NO_NEED_PAY]);
            }

            $paid = $dueDate->lessThanOrEqualTo($periodEnd)
                ? fake()->boolean(86)
                : fake()->boolean(6);

            if (!$paid) {
                return $attributes;
            }

            $earliestPayDate = Carbon::parse($saleContract->sc_order_at ?? $saleContract->sc_start_date)->startOfDay();
            $actualPayDate   = $dueDate->copy()->addDays(fake()->numberBetween(-2, 7));
            if ($actualPayDate->lessThan($earliestPayDate)) {
                $actualPayDate = $earliestPayDate;
            }

            if ($dueDate->greaterThan($periodEnd) && $actualPayDate->greaterThan($periodEnd)) {
                $actualPayDate = $periodEnd->copy();
            }

            return array_merge($attributes, [
                'p_pay_status'        => PPayStatus::PAID,
                'p_actual_pay_date'   => $actualPayDate->toDateString(),
                'p_actual_pay_amount' => fake()->boolean(8) ? round($amount * fake()->randomFloat(2, 0.85, 0.99), 2) : $amount,
            ]);
        });
    }

    public function shortTermContractFee(SaleContract $saleContract, string $amountField, int $paymentType, Carbon $periodStart, Carbon $periodEnd): static
    {
        return $this->mockDataPlan([
            'p_pt_id'             => $paymentType,
            'p_should_pay_date'   => ($saleContract->sc_signed_at ?? $saleContract->sc_order_at ?? $periodStart)->toDateString(),
            'p_should_pay_amount' => (float) $saleContract->{$amountField},
            'p_remark'            => PPtId::LABELS[$paymentType] ?? null,
        ], $saleContract, $periodEnd);
    }
}
