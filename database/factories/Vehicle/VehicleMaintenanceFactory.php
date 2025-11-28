<?php

namespace Database\Factories\Vehicle;

use App\Enum\Vehicle\VmCustodyVehicle;
use App\Enum\Vehicle\VmPickupStatus;
use App\Enum\Vehicle\VmSettlementMethod;
use App\Enum\Vehicle\VmSettlementStatus;
use App\Models\Vehicle\VehicleMaintenance;
use Database\Factories\UsesJsonFixture;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehicleMaintenance>
 */
class VehicleMaintenanceFactory extends Factory
{
    use UsesJsonFixture;

    protected $model = VehicleMaintenance::class;

    public function definition(): array
    {
        $data = $this->randomGroupFromPools();

        return [
            'entry_datetime'        => fake_current_period_dt(),
            'maintenance_amount'    => $data['maintenance_amount'],
            'entry_mileage'         => $this->faker->numberBetween(0, 200000),
            'next_maintenance_date' => fake_current_period_d(modify: '+3 months'),
            'departure_datetime'    => fake_current_period_dt(),
            'maintenance_mileage'   => $this->faker->numberBetween(0, 5000),
            'settlement_status'     => VmSettlementStatus::label_key_random(),
            'pickup_status'         => VmPickupStatus::label_key_random(),
            'settlement_method'     => VmSettlementMethod::label_key_random(),
            'custody_vehicle'       => VmCustodyVehicle::label_key_random(),
            'vm_remark'             => $data['vm_remark'],
            'additional_photos'     => fake_many_photos(),
            'maintenance_info'      => [],
        ];
    }
}
