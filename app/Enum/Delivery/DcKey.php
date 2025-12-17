<?php

namespace App\Enum\Delivery;

use App\Enum\EnumLikeBase;

class DcKey extends EnumLikeBase
{
    // 租客支付租金提前提醒
    public const string PAYMENT = 'payment';

    // 租客合同到期提前提醒
    public const string SETTLEMENT = 'settlement';

    // 车辆续保提前提醒
    public const string VEHICLE_INSURANCE = 'vehicle_insurance';

    public const string VEHICLE_SCHEDULE = 'vehicle_schedule';

    public const string VEHICLE_VIOLATION = 'vehicle_violation';

    public const array LABELS = [
        self::PAYMENT           => '租金支付提醒',
        self::SETTLEMENT        => '合同到期提醒',
        self::VEHICLE_INSURANCE => '车辆续保提醒',
        self::VEHICLE_SCHEDULE  => '车辆年检提醒',
        self::VEHICLE_VIOLATION => '车辆违章处理提醒',
    ];
}
