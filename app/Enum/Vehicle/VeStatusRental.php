<?php

namespace App\Enum\Vehicle;

use App\Enum\EnumLikeBase;

class VeStatusRental extends EnumLikeBase
{
    public const string PENDING  = 'pending';
    public const string LISTED   = 'listed';
    public const string RESERVED = 'reserved';
    public const string RENTED   = 'rented';

    public const array LABELS = [
        self::PENDING  => '待上架', // 初始状态
        self::LISTED   => '已上架', // 通过整备功能上架车辆
        self::RESERVED => '已预定', // 租车合同还未付款
        self::RENTED   => '已租赁', // 付完头款
    ];
}
