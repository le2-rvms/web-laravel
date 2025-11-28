<?php

namespace Database\Factories\Iot;

use App\Models\Iot\IotDeviceBinding;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Iot\IotDeviceBinding>
 */
class IotDeviceBindingFactory extends Factory
{
    protected $model = IotDeviceBinding::class;

    public function definition(): array
    {
        return [
            'db_start_at' => now()->subDays(2)->toDateTimeString(),
            'db_end_at'   => now()->subDay()->toDateTimeString(),
        ];
    }
}
