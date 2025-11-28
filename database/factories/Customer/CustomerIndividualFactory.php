<?php

namespace Database\Factories\Customer;

use App\Enum\Customer\CuiCuiGender;
use App\Models\Customer\CustomerIndividual;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerIndividual>
 */
class CustomerIndividualFactory extends Factory
{
    public function definition(): array
    {
        return [
            'cui_name'                       => $this->faker->name(),
            'cui_gender'                     => CuiCuiGender::label_key_random(),
            'cui_date_of_birth'              => $this->faker->date('Y-m-d', '2000-01-01'),
            'cui_id1_photo'                  => fake_one_photo(),
            'cui_id2_photo'                  => fake_one_photo(),
            'cui_id_number'                  => $this->faker->regexify('[1-9]\d{5}(19|20)\d{2}(0[1-9]|1[0-2])(0[1-9]|[12]\d|3[01])\d{3}[\dXx]'),
            'cui_id_address'                 => $this->faker->address(),
            'cui_id_expiry_date'             => $this->faker->date('Y-m-d', '+10 years'),
            'cui_driver_license1_photo'      => fake_one_photo(),
            'cui_driver_license2_photo'      => fake_one_photo(),
            'cui_driver_license_number'      => $this->faker->bothify('#########??#####'),
            'cui_driver_license_category'    => $this->faker->randomElement(['A1', 'A2', 'B1', 'B2', 'C1', 'C2', 'D', 'E']),
            'cui_driver_license_expiry_date' => $this->faker->date('Y-m-d', '+10 years'),
            'cui_emergency_contact_name'     => $this->faker->name(),
            'cui_emergency_contact_phone'    => $this->faker->phoneNumber(),
            'cui_emergency_relationship'     => $this->faker->name(),
        ];
    }
}
