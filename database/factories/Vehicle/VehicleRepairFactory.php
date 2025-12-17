<?php

namespace Database\Factories\Vehicle;

use App\Enum\VehicleRepair\VrCustodyVehicle;
use App\Enum\VehicleRepair\VrPickupStatus;
use App\Enum\VehicleRepair\VrRepairAttribute;
use App\Enum\VehicleRepair\VrRepairStatus;
use App\Enum\VehicleRepair\VrSettlementMethod;
use App\Enum\VehicleRepair\VrSettlementStatus;
use App\Models\Vehicle\VehicleRepair;
use Database\Factories\UsesJsonFixture;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehicleRepair>
 */
class VehicleRepairFactory extends Factory
{
    use UsesJsonFixture;

    protected $model = VehicleRepair::class;

    public function definition(): array
    {
        $data = $this->randomGroupFromPools();

        return [
            'vr_entry_datetime'     => fake_current_period_dt(),
            'vr_mileage'            => $this->faker->optional()->numberBetween(1000, 300000),
            'vr_repair_cost'        => $data['repair_cost'],
            'vr_delay_days'         => $this->faker->optional()->numberBetween(0, 30),
            'vr_repair_content'     => $data['repair_content'],
            'vr_departure_datetime' => fake_current_period_dt(),
            'vr_repair_status'      => VrRepairStatus::label_key_random(),
            'vr_settlement_status'  => VrSettlementStatus::label_key_random(),
            'vr_pickup_status'      => VrPickupStatus::label_key_random(),
            'vr_settlement_method'  => VrSettlementMethod::label_key_random(),
            'vr_custody_vehicle'    => VrCustodyVehicle::label_key_random(),
            'vr_repair_attribute'   => VrRepairAttribute::label_key_random(),
            'vr_remark'             => $data['vr_remark'],
            'vr_add_should_pay'     => $this->faker->boolean(),
            'vr_additional_photos'  => fake_many_photos(),
            'vr_repair_info'        => [],
        ];
    }
}
