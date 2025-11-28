<?php

namespace Database\Factories\Vehicle;

use App\Models\Vehicle\VehicleInsurance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehicleInsurance>
 */
class VehicleInsuranceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'compulsory_policy_file'              => fake_one_photo(),
            'compulsory_policy_photos'            => fake_many_photos(),
            'compulsory_policy_addendum_file'     => fake_one_photo(),
            'compulsory_plate_no'                 => $this->faker->regexify('[A-Z]{1}[A-Z0-9]{5}'),
            'compulsory_policy_number'            => strtoupper($this->faker->bothify('??#####')),
            'compulsory_start_date'               => fake_current_period_d(),
            'compulsory_end_date'                 => fake_current_period_d(modify: '+3 months'),
            'compulsory_premium'                  => $this->faker->randomFloat(2, 500, 5000),
            'compulsory_insured_company'          => $this->faker->company(),
            'compulsory_org_code'                 => strtoupper($this->faker->bothify('??#####')),
            'compulsory_insurance_company'        => $this->faker->company(),
            'carrier_liability_policy_file'       => fake_one_photo(),
            'carrier_liability_policy_photos'     => fake_many_photos(),
            'carrier_liability_plate_no'          => $this->faker->regexify('[A-Z]{1}[A-Z0-9]{5}'),
            'carrier_liability_policy_number'     => strtoupper($this->faker->bothify('??#####')),
            'carrier_liability_start_date'        => fake_current_period_d(),
            'carrier_liability_end_date'          => fake_current_period_d(modify: '+3 months'),
            'carrier_liability_premium'           => $this->faker->randomFloat(2, 500, 5000),
            'carrier_liability_insured_company'   => $this->faker->company(),
            'carrier_liability_org_code'          => strtoupper($this->faker->bothify('??#####')),
            'carrier_liability_insurance_company' => $this->faker->company(),
            'commercial_policy_file'              => fake_one_photo(),
            'commercial_policy_photos'            => fake_many_photos(),
            'commercial_policy_addendum_file'     => fake_one_photo(),
            'commercial_plate_no'                 => $this->faker->regexify('[A-Z]{1}[A-Z0-9]{5}'),
            'commercial_policy_number'            => strtoupper($this->faker->bothify('??#####')),
            'commercial_start_date'               => fake_current_period_d(),
            'commercial_end_date'                 => fake_current_period_d(modify: '+3 months'),
            'commercial_premium'                  => $this->faker->randomFloat(2, 1000, 10000),
            'commercial_insured_company'          => $this->faker->company(),
            'commercial_org_code'                 => strtoupper($this->faker->bothify('??#####')),
            'commercial_insurance_company'        => $this->faker->company(),
            'is_company_borne'                    => $this->faker->boolean(),
            'vi_remark'                           => null,
        ];
    }
}
