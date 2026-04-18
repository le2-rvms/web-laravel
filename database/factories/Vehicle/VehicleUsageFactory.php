<?php

namespace Database\Factories\Vehicle;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Vehicle\VehicleUsage>
 */
class VehicleUsageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'vu_remark' => null,
        ];
    }
}
