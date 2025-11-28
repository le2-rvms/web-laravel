<?php

namespace Database\Factories\Vehicle;

use App\Enum\Vehicle\VeStatusDispatch;
use App\Enum\Vehicle\VeStatusRental;
use App\Enum\Vehicle\VeStatusService;
use App\Enum\Vehicle\VeVeType;
use App\Models\Vehicle\Vehicle;
use App\Models\Vehicle\VehicleModel;
use Database\Factories\UsesJsonFixture;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    use UsesJsonFixture;

    protected $model = Vehicle::class;

    public function definition(): array
    {
        // 购置日期在过去 5 年内；有效期在购置后 1~5 年
        $purchaseAt = $this->faker->dateTimeBetween('-5 years', 'now');
        $validUntil = (clone $purchaseAt)->modify('+'.$this->faker->numberBetween(1, 5).' years');

        static $vehicleModelIds;

        if (!$vehicleModelIds) {
            $vehicleModelIds = VehicleModel::query()->pluck('vm_id');
        }

        // 车牌省份简称
        $provinces = '京津沪渝冀豫云辽黑湘皖鲁新苏浙赣鄂桂甘晋蒙陕吉闽贵粤青藏川宁琼';

        // 随机取一个省份字
        $province = mb_substr(
            $provinces,
            $this->faker->numberBetween(0, mb_strlen($provinces) - 1),
            1
        );
        // [A-Z]
        $letter = strtoupper($this->faker->randomLetter());

        // [A-Z0-9]{5} 这里是纯 ASCII，用 regexify 就没问题
        $tail = $this->faker->regexify('[A-Z0-9]{5}');

        return [
            'plate_no'        => $province.$letter.$tail,
            've_type'         => VeVeType::label_key_random(),
            'vm_id'           => $vehicleModelIds->random(), // 外键可空；如需生成有效外键，可在自定义 state 里赋值
            'status_service'  => VeStatusService::label_key_random(),
            'status_rental'   => VeStatusRental::label_key_random(),
            'status_dispatch' => VeStatusDispatch::label_key_random(),

            // jsonb 可空；给出一个简单对象，Eloquent 会自动 JSON 编码
            've_license_face_photo' => $this->faker->boolean(60) ? [
                'url'  => UploadedFile::fake()->image('photo.jpg', 640, 480),
                'hash' => $this->faker->sha1(),
            ] : null,
            've_license_back_photo' => $this->faker->boolean(60) ? [
                'url'  => UploadedFile::fake()->image('photo.jpg', 640, 480),
                'hash' => $this->faker->sha1(),
            ] : null,
            've_license_owner'            => $this->faker->name(),
            've_license_address'          => $this->faker->address(),
            've_license_usage'            => $this->faker->randomElement(['非营运', '营运', '租赁']),
            've_license_type'             => $this->faker->randomElement(['小型普通客车']),
            've_license_company'          => $this->faker->company(),
            've_license_vin_code'         => strtoupper(Str::random(17)), // VIN 通常 17 位大写字母数字（不严格排除 I/O/Q）
            've_license_engine_no'        => strtoupper($this->faker->bothify('??##########')), // 12 位左右
            've_license_purchase_date'    => $purchaseAt->format('Y-m-d'),
            've_license_valid_until_date' => $validUntil->format('Y-m-d'),
            've_mileage'                  => $this->faker->numberBetween(0, 300000),
            've_color'                    => $this->faker->safeColorName(),

            'vehicle_manager' => $this->faker->boolean(70) ? $this->randomAdminID(1) : null,

            // 车证信息
            've_cert_no'       => strtoupper(Str::random(20)),
            've_cert_valid_to' => $this->faker->dateTimeBetween('-2 years', '+2 years'),
        ];
    }
}
