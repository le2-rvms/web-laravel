<?php

namespace App\Enum\Vehicle;

use App\Enum\EnumLikeBase;

class VsInspectionType extends EnumLikeBase
{
    public const string VEHICLE = 'vehicle';

    public const string GAS_CYLINDER = 'gas_cylinder';

    public const string CERTIFICATE = 'certificate';

    public const string BUSINESS_LICENSE = 'business_license';

    public const array LABELS = [
        self::VEHICLE          => '车辆年检',
        self::GAS_CYLINDER     => '气罐年检',
        self::CERTIFICATE      => '车证年检',
        self::BUSINESS_LICENSE => '营业执照年检',
    ];
}
