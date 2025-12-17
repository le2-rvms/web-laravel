<?php

namespace App\Enum\VehicleInspection;

use App\Enum\EnumLikeBase;

class ViInspectionType extends EnumLikeBase
{
    public const string SC_DISPATCH = 'sc-dispatch';

    public const string SC_RETURN = 'sc-return';

    public const string VR_DISPATCH = 'vr-dispatch';
    public const string VR_RETURN   = 'vr-return';

    public const array LABELS = [
        self::SC_DISPATCH => '发车',
        self::SC_RETURN   => '退车',
        self::VR_DISPATCH => '临时车发车',
        self::VR_RETURN   => '临时车退车',
    ];
}
