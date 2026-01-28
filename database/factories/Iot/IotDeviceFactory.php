<?php

namespace Database\Factories\Iot;

use App\Models\Iot\MqttAccount;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MqttAccount>
 */
class IotDeviceFactory extends Factory
{
    protected $model = MqttAccount::class;

    public function definition(): array
    {
        $salt = Str::random(10);

        return [
            'user_name'     => $this->faker->userName(),
            'password_hash' => hash('sha256', 'password'.$salt),  // 默认密码 "password"
            'salt'          => $salt,
            'is_superuser'  => $this->faker->boolean() ? 1 : 0,
        ];
    }
}
