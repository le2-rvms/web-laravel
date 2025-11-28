<?php

namespace Database\Factories\Vehicle;

use App\Enum\Vehicle\VrCustodyVehicle;
use App\Enum\Vehicle\VrPickupStatus;
use App\Enum\Vehicle\VrRepairAttribute;
use App\Enum\Vehicle\VrRepairStatus;
use App\Enum\Vehicle\VrSettlementMethod;
use App\Enum\Vehicle\VrSettlementStatus;
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
            'entry_datetime'     => fake_current_period_dt(),
            'vr_mileage'         => $this->faker->optional()->numberBetween(1000, 300000),
            'repair_cost'        => $data['repair_cost'],
            'delay_days'         => $this->faker->optional()->numberBetween(0, 30),
            'repair_content'     => $data['repair_content'],
            'departure_datetime' => fake_current_period_dt(),
            'repair_status'      => VrRepairStatus::label_key_random(),
            'settlement_status'  => VrSettlementStatus::label_key_random(),
            'pickup_status'      => VrPickupStatus::label_key_random(),
            'settlement_method'  => VrSettlementMethod::label_key_random(),
            'custody_vehicle'    => VrCustodyVehicle::label_key_random(),
            'repair_attribute'   => VrRepairAttribute::label_key_random(),
            'vr_remark'          => $data['vr_remark'],
            'add_should_pay'     => $this->faker->boolean(),
            'additional_photos'  => fake_many_photos(),
            'repair_info'        => [],
        ];
    }
}
