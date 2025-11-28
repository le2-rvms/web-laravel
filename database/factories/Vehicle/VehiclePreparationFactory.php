<?php

namespace Database\Factories\Vehicle;

use App\Enum\YesNo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Vehicle\VehiclePreparation>
 */
class VehiclePreparationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'annual_check_is'   => YesNo::YES,
            'annual_check_dt'   => fake_current_period_dt(),
            'insured_check_is'  => YesNo::YES,
            'insured_check_dt'  => fake_current_period_dt(),
            'vehicle_check_is'  => YesNo::YES,
            'vehicle_check_dt'  => fake_current_period_dt(),
            'document_check_is' => YesNo::YES,
            'document_check_dt' => fake_current_period_dt(),
        ];
    }
}
