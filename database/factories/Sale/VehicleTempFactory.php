<?php

namespace Database\Factories\Sale;

use App\Enum\Sale\VtChangeStatus;
use App\Models\Sale\SaleContract;
use App\Models\Sale\VehicleTemp;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehicleTemp>
 */
class VehicleTempFactory extends Factory
{
    private const array REMARKS = [
        '原计划车辆还在整备，先安排同级车辆临时派车。',
        '客户预约时间临近，车辆调度中，待原车完成检查后再确认。',
        '原车辆证件资料待核验，临时安排备用车辆保障交付。',
        '客户临时调整取车时间，先保留备用车辆方案。',
        '原车辆需补充清洁和充电，临时派车记录用于调度跟进。',
        null,
    ];

    public function definition(): array
    {
        return [
            'vt_change_start_date' => fake_current_period_d(),
            'vt_change_end_date'   => fake_current_period_d(),
            'vt_change_status'     => VtChangeStatus::label_key_random(),
            'vt_additional_photos' => fake_many_photos(),
            'vt_remark'            => fake()->randomElement(self::REMARKS),
        ];
    }

    public function pendingReservation(SaleContract $saleContract): static
    {
        return $this->state([
            'vt_change_start_date' => $saleContract->sc_order_at->copy()->addDay()->toDateString(),
            'vt_change_end_date'   => $saleContract->sc_start_date->toDateString(),
            'vt_remark'            => fake()->randomElement([
                '待签约预约阶段车辆调度，先记录备用车辆方案。',
                '客户尚未签约，原车需确认档期，临时保留替换车辆。',
                '预约车辆交付前检查未完成，安排临时派车备选。',
                '待签约订单临近开始日期，先预留同级备用车。',
            ]),
        ]);
    }
}
