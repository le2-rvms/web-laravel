<?php

namespace Database\Factories\Sale;

use App\Enum\Sale\VrReplacementStatus;
use App\Enum\Sale\VrReplacementType;
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
            'replacement_type'       => $replacement_type = VrReplacementType::label_key_random(),
            'replacement_date'       => VrReplacementType::PERMANENT === $replacement_type ? fake_current_period_d() : null,
            'replacement_start_date' => VrReplacementType::TEMPORARY === $replacement_type ? fake_current_period_d() : null,
            'replacement_end_date'   => VrReplacementType::TEMPORARY === $replacement_type ? fake_current_period_d() : null,
            'replacement_status'     => VrReplacementStatus::label_key_random(),
            'additional_photos'      => fake_many_photos(),
            'vr_remark'              => $this->faker->optional()->text(200),
        ];
    }
}
