<?php

namespace Database\Factories\Vehicle;

use App\Enum\Vehicle\VsInspectionType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Vehicle\VehicleSchedule>
 */
class VehicleScheduleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'inspection_type'      => VsInspectionType::label_key_random(),
            'inspector'            => $this->faker->name(),
            'inspection_date'      => fake_current_period_d(),
            'inspection_amount'    => $this->faker->randomFloat(2, 50, 5000),
            'next_inspection_date' => fake_current_period_d(modify: '+3 month'),
            'additional_photos'    => fake_many_photos(),
            'vs_remark'            => null,
        ];
    }
}
