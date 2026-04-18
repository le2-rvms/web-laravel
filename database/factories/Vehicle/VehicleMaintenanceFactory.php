<?php

namespace Database\Factories\Vehicle;

use App\Enum\VehicleMaintenance\VmCustodyVehicle;
use App\Enum\VehicleMaintenance\VmPickupStatus;
use App\Enum\VehicleMaintenance\VmSettlementMethod;
use App\Enum\VehicleMaintenance\VmSettlementStatus;
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
            'vm_entry_datetime'        => fake_current_period_dt(),
            'vm_maintenance_amount'    => $data['maintenance_amount'],
            'vm_entry_mileage'         => $this->faker->numberBetween(0, 200000),
            'vm_next_maintenance_date' => fake_current_period_d(modify: '+3 months'),
            'vm_departure_datetime'    => fake_current_period_dt(),
            'vm_maintenance_mileage'   => $this->faker->numberBetween(0, 5000),
            'vm_settlement_status'     => VmSettlementStatus::label_key_random(),
            'vm_pickup_status'         => VmPickupStatus::label_key_random(),
            'vm_settlement_method'     => VmSettlementMethod::label_key_random(),
            'vm_custody_vehicle'       => VmCustodyVehicle::label_key_random(),
            'vm_remark'                => $data['vm_remark'],
            'vm_additional_photos'     => fake_many_photos(),
            'vm_maintenance_info'      => [],
        ];
    }
}
