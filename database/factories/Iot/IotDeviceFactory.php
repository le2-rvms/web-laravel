<?php

namespace Database\Factories\Iot;

use App\Models\Iot\IotDevice;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<IotDevice>
 */
class IotDeviceFactory extends Factory
{
    protected $model = IotDevice::class;

    public function definition(): array
    {
        $salt = Str::random(10);

        return [
            'device_code'   => $this->faker->userName(),
            'password_hash' => hash('sha256', 'password'.$salt),  // 默认密码 "password"
            'salt'          => $salt,
            'is_superuser'  => $this->faker->boolean() ? 1 : 0,
        ];
    }
}
