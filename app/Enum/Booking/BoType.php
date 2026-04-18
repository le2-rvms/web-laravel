<?php

namespace App\Enum\Booking;

use App\Enum\EnumLikeBase;

class BoType extends EnumLikeBase
{
    public const string MONTHLY_RENT = 'monthly_rent';

    public const string WEEKLY_RENT = 'weekly_rent';
    public const string DAILY_RENT  = 'daily_rent';

    public const array LABELS = [
        self::MONTHLY_RENT => '月租',
        self::WEEKLY_RENT  => '周租',
        self::DAILY_RENT   => '日租',
    ];
}
