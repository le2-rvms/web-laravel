<?php

namespace Database\Factories\Vehicle;

use App\Enum\Exist;
use App\Enum\VehicleInspection\ViInspectionType;
use App\Models\Vehicle\VehicleInspection;
use Carbon\Carbon;
use Database\Factories\UsesJsonFixture;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehicleInspection>
 */
class VehicleInspectionFactory extends Factory
{
    use UsesJsonFixture;

    protected $model = VehicleInspection::class;

    public function definition(): array
    {
        return [
            'vi_inspection_type'       => '',
            'vi_policy_copy'           => Exist::label_key_random(),
            'vi_driving_license'       => Exist::label_key_random(),
            'vi_operation_license'     => Exist::label_key_random(),
            'vi_vehicle_damage_status' => Exist::label_key_random(),
            'vi_inspection_datetime'   => fake_current_period_dt(),
            'vi_mileage'               => $this->faker->numberBetween(5000, 200000),
            'vi_damage_deduction'      => $this->faker->optional()->randomFloat(2, 0, 1000),
            'vi_remark'                => null,
            'vi_add_should_pay'        => $this->faker->boolean() ? 1 : 0,
            'vi_additional_photos'     => fake_many_photos(),
            //            'inspection_info'       => [['info_photos' => fake_many_photos(), 'description' => $this->faker->optional()->sentence()]],
        ];
    }

    public function saleContractDispatch(Carbon $dispatchAt, int $mileage): static
    {
        return $this->state([
            'vi_inspection_type'     => ViInspectionType::SC_DISPATCH,
            'vi_inspection_datetime' => $dispatchAt,
            'vi_mileage'             => $mileage,
            'vi_damage_deduction'    => null,
            'vi_add_should_pay'      => 0,
        ]);
    }

    public function saleContractReturn(Carbon $returnAt, int $mileage): static
    {
        return $this->state(function () use ($returnAt, $mileage) {
            $damageDeduction = fake()->boolean(12) ? fake()->randomFloat(2, 100, 1800) : null;

            return [
                'vi_inspection_type'     => ViInspectionType::SC_RETURN,
                'vi_inspection_datetime' => $returnAt,
                'vi_mileage'             => $mileage,
                'vi_damage_deduction'    => $damageDeduction,
                'vi_add_should_pay'      => null === $damageDeduction ? 0 : 1,
            ];
        });
    }

    public function configure()
    {
        return $this
            ->afterMaking(function (VehicleInspection $inspection) {
                $remark_array          = $this->randomGroupFromPools($inspection->vi_inspection_type, mt_rand(2, 5));
                $remark_array          = array_column($remark_array, 'vi_remark');
                $inspection->vi_remark = implode('', $remark_array);

                $inspection_info = [];
                foreach ($remark_array as $key => $remark) {
                    $inspection_info[] = ['info_photos' => fake_many_photos(), 'description' => $remark];
                }

                $inspection->vi_inspection_info = $inspection_info;
            })
        ;
    }
}
