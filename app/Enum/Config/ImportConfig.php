<?php

namespace App\Enum\Config;

use App\Models\Customer\Customer;
use App\Models\Payment\Payment;
use App\Models\Sale\SaleContract;
use App\Models\Vehicle\Vehicle;
use App\Models\Vehicle\VehicleAccident;
use App\Models\Vehicle\VehicleInsurance;
use App\Models\Vehicle\VehicleMaintenance;
use App\Models\Vehicle\VehicleManualViolation;
use App\Models\Vehicle\VehicleRepair;
use App\Models\Vehicle\VehicleSchedule;

class ImportConfig
{
    private static array $keys = [
        Vehicle::class,
        Customer::class,
        VehicleSchedule::class,
        VehicleInsurance::class,
        SaleContract::class,
        Payment::class,  // 必须先导入 SaleContract
        VehicleRepair::class,
        VehicleMaintenance::class,
        VehicleAccident::class,
        VehicleManualViolation::class,
    ];

    public static function keys(): array
    {
        return static::$keys;
    }

    public static function options(): array
    {
        $result = [];
        foreach (static::keys() as $model) {
            $result[] = [
                'text'  => trans_model($model).trans_model_suffix($model),
                'value' => $model,
            ];
        }

        return [preg_replace('/^.*\\\/', '', get_called_class()).'Options' => $result];
    }

    public static function kv(): array
    {
        $result = [];
        foreach (static::keys() as $model) {
            $result[$model] = trans_model($model).trans_model_suffix($model);
        }

        return [preg_replace('/^.*\\\/', '', get_called_class()).'kv' => $result];
    }
}
