<?php

namespace App\Enum\Delivery;

use App\Enum\KvTrait;

class DcDcKeyDefault
{
    use KvTrait;

    public const array kv = [
        DcDcKey::PAYMENT => [
            'dc_title'    => '租金支付提醒', 'dc_tn' => 6, 'dc_provider' => DcDcProvider::WECOM_BOT,
            'dc_template' => <<<'TXT'
租金支付提醒
车牌号：{{ $saleContract['vehicle']['plate_no'] ?? null }}
本月应支付租金：{{ $should_pay_amount }}
付款时间：{{ $should_pay_date }}
TXT,
        ],
        DcDcKey::SETTLEMENT => [
            'dc_title'    => '合同到期提醒', 'dc_tn' => 30, 'dc_provider' => DcDcProvider::WECOM_BOT,
            'dc_template' => <<<'TXT'
合同到期提醒
车牌号：{{ $vehicle['plate_no'] }}
到期日期：{{ $rental_end }}
TXT,
        ],
        DcDcKey::VEHICLE_INSURANCE => [
            'dc_title'    => '车辆续保提醒', 'dc_tn' => 60, 'dc_provider' => DcDcProvider::WECOM_APP,
            'dc_template' => <<<'TXT'
车辆续保提醒
车牌号：{{ $vehicle['plate_no'] }}
到期日期：{{ $compulsory_end_date }}
TXT,
        ],
        DcDcKey::VEHICLE_SCHEDULE => [
            'dc_title'    => '年检提醒', 'dc_tn' => 60, 'dc_provider' => DcDcProvider::WECOM_APP,
            'dc_template' => <<<'TXT'
年检提醒
年检类型：{{ $inspection_type_label }}
车牌号：{{ $vehicle['plate_no'] }}
到期日期：{{ $next_inspection_date }}
TXT,
        ],
        DcDcKey::VEHICLE_VIOLATION => [
            'dc_title'    => '车辆违章处理提醒', 'dc_tn' => 0, 'dc_provider' => DcDcProvider::WECOM_BOT,
            'dc_template' => <<<'TXT'
车辆违章处理提醒
车牌号：{{ $plate_no }}
违章发生的日时：{{ $violation_datetime }}
违法行为：{{ $violation_content }}
违章发生地点：{{ $location }}
TXT,
        ],
    ];
}
