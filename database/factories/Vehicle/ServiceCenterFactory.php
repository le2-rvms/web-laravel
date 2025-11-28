<?php

namespace Database\Factories\Vehicle;

use App\Enum\Vehicle\ScScStatus;
use App\Models\Vehicle\ServiceCenter;
use Database\Factories\UsesJsonFixture;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceCenter>
 */
class ServiceCenterFactory extends Factory
{
    use UsesJsonFixture;

    protected $model = ServiceCenter::class;

    public function definition(): array
    {
        $data = $this->shiftShuffleFromPools();

        return [
            'sc_name'             => $data['sc_name'],
            'sc_address'          => $data['sc_address'],
            'contact_name'        => $data['contact_name'],
            'contact_phone'       => $data['contact_mobile'],
            'sc_status'           => ScScStatus::label_key_random(),
            'sc_note'             => $data['sc_note'],
            'permitted_admin_ids' => $this->faker->boolean(50) ? [$this->randomVehicleServiceAdminID(1)] : null,
        ];
    }
}
