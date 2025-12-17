<?php

namespace Database\Factories\Sale;

use App\Enum\Payment\SsDeleteOption;
use App\Enum\Sale\SsReturnStatus;
use App\Models\Sale\SaleSettlement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaleSettlement>
 */
class SaleSettlementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ss_deposit_amount'             => $deposit_amount = $this->faker->randomFloat(2, 0, 10000),
            'ss_received_deposit'           => $this->faker->randomFloat(2, 0, $deposit_amount),
            'ss_early_return_penalty'       => $this->faker->randomFloat(2, 0, 500),
            'ss_overdue_inspection_penalty' => $this->faker->randomFloat(2, 0, 300),
            'ss_overdue_rent'               => $this->faker->randomFloat(2, 0, 300),
            'ss_overdue_penalty'            => $this->faker->randomFloat(2, 0, 300),
            'ss_accident_depreciation_fee'  => $this->faker->randomFloat(2, 0, 300),
            'ss_insurance_surcharge'        => $this->faker->randomFloat(2, 0, 300),
            'ss_violation_withholding_fee'  => $this->faker->randomFloat(2, 0, 300),
            'ss_violation_penalty'          => $this->faker->randomFloat(2, 0, 300),
            'ss_repair_fee'                 => $this->faker->randomFloat(2, 0, 300),
            'ss_insurance_deductible'       => $this->faker->randomFloat(2, 0, 1000),
            'ss_forced_collection_fee'      => $this->faker->randomFloat(2, 0, 150),
            'ss_other_deductions'           => $this->faker->randomFloat(2, 0, 500),
            'ss_other_deductions_remark'    => null,
            'ss_refund_amount'              => $this->faker->randomFloat(2, 0, 1000),
            'ss_refund_details'             => $this->faker->randomFloat(2, 0, 1000),
            'ss_settlement_amount'          => $this->faker->randomFloat(2, -5000, 5000),
            'ss_deposit_return_amount'      => $this->faker->randomFloat(2, -5000, 5000),
            'ss_deposit_return_date'        => fake_current_period_d(),
            'ss_return_status'              => SsReturnStatus::label_key_random(),
            'ss_return_datetime'            => fake_current_period_dt(),
            'ss_additional_photos'          => fake_many_photos(),
            'ss_delete_option'              => SsDeleteOption::label_key_random(),
            //            'ss_remark'                  => $this->faker->optional()->paragraph(),
        ];
    }
}
