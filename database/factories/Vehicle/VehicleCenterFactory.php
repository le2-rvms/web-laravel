<?php

namespace Database\Factories\Vehicle;

use App\Enum\Vehicle\VcStatus;
use App\Models\Vehicle\VehicleCenter;
use Database\Factories\UsesJsonFixture;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehicleCenter>
 */
class VehicleCenterFactory extends Factory
{
    use UsesJsonFixture;

    protected $model = VehicleCenter::class;

    public function definition(): array
    {
        $data = $this->shiftShuffleFromPools();

        return [
            'vc_name'          => $data['vc_name'],
            'vc_address'       => $data['vc_address'],
            'vc_contact_name'  => $data['vc_contact_name'],
            'vc_contact_phone' => $data['vc_contact_mobile'],
            'vc_status'        => VcStatus::label_key_random(),
            'vc_note'          => $data['vc_note'],
            'vc_permitted'     => $this->faker->boolean(50) ? [$this->randomVehicleServiceAdminID(1)] : null,
        ];
    }
}
