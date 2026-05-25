<?php

namespace Database\Factories\Iot;

use App\Models\Iot\IotDeviceBinding;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IotDeviceBinding>
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

    public function activeMockBinding(): static
    {
        return $this->state(fn () => [
            'db_start_at' => now()->subYears(2)->toDateTimeString(),
            'db_end_at'   => null,
            'db_note'     => '演示数据：当前有效设备绑定',
        ]);
    }
}
