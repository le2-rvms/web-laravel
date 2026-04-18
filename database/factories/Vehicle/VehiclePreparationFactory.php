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
            'vp_annual_check_is'   => YesNo::YES,
            'vp_annual_check_dt'   => fake_current_period_dt(),
            'vp_insured_check_is'  => YesNo::YES,
            'vp_insured_check_dt'  => fake_current_period_dt(),
            'vp_vehicle_check_is'  => YesNo::YES,
            'vp_vehicle_check_dt'  => fake_current_period_dt(),
            'vp_document_check_is' => YesNo::YES,
            'vp_document_check_dt' => fake_current_period_dt(),
        ];
    }
}
