<?php

namespace Database\Factories\Sale;

use App\Enum\Sale\VrReplacementStatus;
use App\Models\Sale\VehicleReplacement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehicleReplacement>
 */
class VehicleReplacementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'replacement_start_date' => fake_current_period_d(),
            'replacement_end_date'   => fake_current_period_d(),
            'replacement_status'     => VrReplacementStatus::label_key_random(),
            'additional_photos'      => fake_many_photos(),
            'vr_remark'              => $this->faker->optional()->text(200),
        ];
    }
}
