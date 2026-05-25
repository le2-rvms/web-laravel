<?php

namespace Database\Factories\Vehicle;

use App\Enum\Vehicle\VsInspectionType;
use App\Models\Vehicle\VehicleSchedule;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehicleSchedule>
 */
class VehicleScheduleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'vs_inspection_type'      => VsInspectionType::label_key_random(),
            'vs_inspector'            => $this->faker->name(),
            'vs_inspection_date'      => fake_current_period_d(),
            'vs_inspection_amount'    => $this->faker->randomFloat(2, 50, 5000),
            'vs_next_inspection_date' => fake_current_period_d(modify: '+3 month'),
            'vs_additional_photos'    => fake_many_photos(),
            'vs_remark'               => null,
        ];
    }

    public function inspectionCycle(Carbon $inspectionAt): static
    {
        return $this->state([
            'vs_inspection_date'      => $inspectionAt->toDateString(),
            'vs_next_inspection_date' => $inspectionAt->copy()->addMonths(fake()->randomElement([3, 6, 12]))->toDateString(),
        ]);
    }
}
