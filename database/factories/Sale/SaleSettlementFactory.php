<?php

namespace Database\Factories\Sale;

use App\Enum\Payment\RsDeleteOption;
use App\Enum\Sale\SsReturnStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Sale\SaleSettlement>
 */
class SaleSettlementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'deposit_amount'             => $deposit_amount = $this->faker->randomFloat(2, 0, 10000),
            'received_deposit'           => $this->faker->randomFloat(2, 0, $deposit_amount),
            'early_return_penalty'       => $this->faker->randomFloat(2, 0, 500),
            'overdue_inspection_penalty' => $this->faker->randomFloat(2, 0, 300),
            'overdue_rent'               => $this->faker->randomFloat(2, 0, 300),
            'overdue_penalty'            => $this->faker->randomFloat(2, 0, 300),
            'accident_depreciation_fee'  => $this->faker->randomFloat(2, 0, 300),
            'insurance_surcharge'        => $this->faker->randomFloat(2, 0, 300),
            'violation_withholding_fee'  => $this->faker->randomFloat(2, 0, 300),
            'violation_penalty'          => $this->faker->randomFloat(2, 0, 300),
            'repair_fee'                 => $this->faker->randomFloat(2, 0, 300),
            'insurance_deductible'       => $this->faker->randomFloat(2, 0, 1000),
            'forced_collection_fee'      => $this->faker->randomFloat(2, 0, 150),
            'other_deductions'           => $this->faker->randomFloat(2, 0, 500),
            'other_deductions_remark'    => null,
            'refund_amount'              => $this->faker->randomFloat(2, 0, 1000),
            'refund_details'             => $this->faker->randomFloat(2, 0, 1000),
            'settlement_amount'          => $this->faker->randomFloat(2, -5000, 5000),
            'deposit_return_amount'      => $this->faker->randomFloat(2, -5000, 5000),
            'deposit_return_date'        => fake_current_period_d(),
            'return_status'              => SsReturnStatus::label_key_random(),
            'return_datetime'            => fake_current_period_dt(),
            'additional_photos'          => fake_many_photos(),
            'delete_option'              => RsDeleteOption::label_key_random(),
            //            'ss_remark'                  => $this->faker->optional()->paragraph(),
        ];
    }
}
