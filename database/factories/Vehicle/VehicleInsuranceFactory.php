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
            'vi_compulsory_policy_file'              => fake_one_photo(),
            'vi_compulsory_policy_photos'            => fake_many_photos(),
            'vi_compulsory_policy_addendum_file'     => fake_one_photo(),
            'vi_compulsory_plate_no'                 => $this->faker->regexify('[A-Z]{1}[A-Z0-9]{5}'),
            'vi_compulsory_policy_number'            => strtoupper($this->faker->bothify('??#####')),
            'vi_compulsory_start_date'               => fake_current_period_d(),
            'vi_compulsory_end_date'                 => fake_current_period_d(modify: '+3 months'),
            'vi_compulsory_premium'                  => $this->faker->randomFloat(2, 500, 5000),
            'vi_compulsory_insured_company'          => $this->faker->company(),
            'vi_compulsory_org_code'                 => strtoupper($this->faker->bothify('??#####')),
            'vi_compulsory_insurance_company'        => $this->faker->company(),
            'vi_carrier_liability_policy_file'       => fake_one_photo(),
            'vi_carrier_liability_policy_photos'     => fake_many_photos(),
            'vi_carrier_liability_plate_no'          => $this->faker->regexify('[A-Z]{1}[A-Z0-9]{5}'),
            'vi_carrier_liability_policy_number'     => strtoupper($this->faker->bothify('??#####')),
            'vi_carrier_liability_start_date'        => fake_current_period_d(),
            'vi_carrier_liability_end_date'          => fake_current_period_d(modify: '+3 months'),
            'vi_carrier_liability_premium'           => $this->faker->randomFloat(2, 500, 5000),
            'vi_carrier_liability_insured_company'   => $this->faker->company(),
            'vi_carrier_liability_org_code'          => strtoupper($this->faker->bothify('??#####')),
            'vi_carrier_liability_insurance_company' => $this->faker->company(),
            'vi_commercial_policy_file'              => fake_one_photo(),
            'vi_commercial_policy_photos'            => fake_many_photos(),
            'vi_commercial_policy_addendum_file'     => fake_one_photo(),
            'vi_commercial_plate_no'                 => $this->faker->regexify('[A-Z]{1}[A-Z0-9]{5}'),
            'vi_commercial_policy_number'            => strtoupper($this->faker->bothify('??#####')),
            'vi_commercial_start_date'               => fake_current_period_d(),
            'vi_commercial_end_date'                 => fake_current_period_d(modify: '+3 months'),
            'vi_commercial_premium'                  => $this->faker->randomFloat(2, 1000, 10000),
            'vi_commercial_insured_company'          => $this->faker->company(),
            'vi_commercial_org_code'                 => strtoupper($this->faker->bothify('??#####')),
            'vi_commercial_insurance_company'        => $this->faker->company(),
            'vi_is_company_borne'                    => $this->faker->boolean(),
            'vi_remark'                              => null,
        ];
    }
}
