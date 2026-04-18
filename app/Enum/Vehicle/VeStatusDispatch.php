<?php

namespace App\Enum\Vehicle;

use App\Enum\EnumLikeBase;

class VeStatusDispatch extends EnumLikeBase
{
    public const string NOT_DISPATCHED = 'not_dispatched';

    //    public const string ALLOW_DISPATCH = 'allow_dispatch';

    public const string DISPATCHED = 'dispatched';

    public const array LABELS = [
        self::NOT_DISPATCHED => '未发车',
        //        self::ALLOW_DISPATCH => '允许发车',
        self::DISPATCHED => '已发车',
    ];
}
