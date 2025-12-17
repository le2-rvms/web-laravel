<?php

namespace Database\Factories\Vehicle;

use App\Enum\VehicleViolation\VvPaymentStatus;
use App\Enum\VehicleViolation\VvProcessStatus;
use App\Models\Vehicle\VehicleViolation;
use Database\Factories\UsesJsonFixture;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehicleViolation>
 */
class VehicleViolationFactory extends Factory
{
    use UsesJsonFixture;

    protected $model = VehicleViolation::class;

    public function definition(): array
    {
        $data = $this->randomGroupFromPools();

        return [
            'vv_decision_number'    => strtoupper($this->faker->unique()->bothify('#############')),
            'vv_violation_datetime' => fake_current_period_dt(),
            'vv_violation_content'  => $data['violation_content'],
            'vv_location'           => $data['location'],
            'vv_fine_amount'        => $data['fine_amount'],
            'vv_penalty_points'     => $data['penalty_points'],
            'vv_process_status'     => VvProcessStatus::label_key_random(),
            'vv_payment_status'     => VvPaymentStatus::label_key_random(),
            'vv_remark'             => $data['vv_remark'],
        ];
    }
}
