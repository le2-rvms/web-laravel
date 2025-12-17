<?php

namespace Database\Factories\Vehicle;

use App\Enum\VehicleManualViolation\VvStatus;
use App\Models\Vehicle\VehicleManualViolation;
use Database\Factories\UsesJsonFixture;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehicleManualViolation>
 */
class VehicleManualViolationFactory extends Factory
{
    use UsesJsonFixture;

    protected $model = VehicleManualViolation::class;

    public function definition(): array
    {
        $data = $this->randomGroupFromPools();

        return [
            'vv_violation_datetime' => fake_current_period_dt(),
            'vv_violation_content'  => $data['violation_content'],
            'vv_location'           => $data['location'],
            'vv_fine_amount'        => $data['fine_amount'],
            'vv_penalty_points'     => $data['penalty_points'],
            'vv_status'             => VvStatus::label_key_random(),
            'vv_remark'             => $data['vv_remark'],
        ];
    }
}
