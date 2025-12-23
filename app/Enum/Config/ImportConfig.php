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
        $key   = preg_replace('/^.*\\\/', '', get_called_class()).'Options';
        $value = [];
        foreach (static::keys() as $model) {
            $value[] = [
                'text'  => trans_model($model).trans_model_suffix($model),
                'value' => $model,
            ];
        }

        return [$key => $value];
    }

    public static function kv(): array
    {
        $key   = preg_replace('/^.*\\\/', '', get_called_class()).'Kvs';
        $value = [];
        foreach (static::keys() as $model) {
            $value[$model] = trans_model($model).trans_model_suffix($model);
        }

        return [$key => $value];
    }
}
