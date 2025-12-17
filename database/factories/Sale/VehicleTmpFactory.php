<?php

namespace Database\Factories\Sale;

use App\Enum\Sale\VtChangeStatus;
use App\Models\Sale\VehicleTmp;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehicleTmp>
 */
class VehicleTmpFactory extends Factory
{
    public function definition(): array
    {
        return [
            'vt_change_start_date' => fake_current_period_d(),
            'vt_change_end_date'   => fake_current_period_d(),
            'vt_change_status'     => VtChangeStatus::label_key_random(),
            'vt_additional_photos' => fake_many_photos(),
            'vt_remark'            => $this->faker->optional()->text(200),
        ];
    }
}
