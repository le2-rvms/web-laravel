<?php

namespace Database\Factories\Vehicle;

use App\Enum\VehicleModel\VmStatus;
use App\Models\Vehicle\VehicleModel;
use Database\Factories\UsesJsonFixture;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehicleModel>
 */
class VehicleModelFactory extends Factory
{
    use UsesJsonFixture;

    protected $model = VehicleModel::class;

    public function definition(): array
    {
        $data = $this->shiftShuffleFromPools();

        return [
            'vm_brand_name' => $data['vm_brand_name'],
            'vm_model_name' => $data['vm_model_name'],
            'vm_status'     => VmStatus::label_key_random(),
        ];
    }
}
