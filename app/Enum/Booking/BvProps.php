<?php

namespace App\Enum\Booking;

use App\Enum\KvTrait;

class BvProps
{
    use KvTrait;

    public const array kv = [
        'includes_insurance'   => '是否包保险',
        'includes_maintenance' => '是否包保养',
        'includes_repair'      => '是否包维修',
        'is_operational'       => '是否运营车',
        'lease_to_own'         => '是否以租代购',
        'is_new'               => '是否新车',
    ];
}
