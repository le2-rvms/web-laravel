<?php

namespace Database\Factories\Vehicle;

use App\Enum\Vehicle\VvPaymentStatus;
use App\Enum\Vehicle\VvProcessStatus;
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
            'decision_number'    => strtoupper($this->faker->unique()->bothify('#############')),
            'violation_datetime' => fake_current_period_dt(),
            'violation_content'  => $data['violation_content'],
            'location'           => $data['location'],
            'fine_amount'        => $data['fine_amount'],
            'penalty_points'     => $data['penalty_points'],
            'process_status'     => VvProcessStatus::label_key_random(),
            'payment_status'     => VvPaymentStatus::label_key_random(),
            'vv_remark'          => $data['vmv_remark'],
        ];
    }
}
