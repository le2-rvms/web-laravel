<?php

namespace App\Enum\Vehicle;

use App\Enum\EnumLikeBase;

class ViInspectionType extends EnumLikeBase
{
    public const string SO_DISPATCH = 'so-dispatch';

    public const string SO_RETURN = 'so-return';

    public const string VR_DISPATCH = 'vr-dispatch';
    public const string VR_RETURN   = 'vr-return';

    public const array LABELS = [
        self::SO_DISPATCH => '发车',
        self::SO_RETURN   => '退车',
        self::VR_DISPATCH => '换车发车',
        self::VR_RETURN   => '换车退车',
    ];
}
