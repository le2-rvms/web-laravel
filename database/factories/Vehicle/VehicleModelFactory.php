<?php

namespace Database\Factories\Vehicle;

use App\Enum\Vehicle\VmVmStatus;
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
            'brand_name' => $data['brand_name'],
            'model_name' => $data['model_name'],
            'vm_status'  => VmVmStatus::label_key_random(),
        ];
    }
}
