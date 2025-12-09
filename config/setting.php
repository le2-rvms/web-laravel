<?php

use App\Models\_\Configuration;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminTeam;
use App\Models\Customer\Customer;
use App\Models\Customer\CustomerCompany;
use App\Models\Customer\CustomerIndividual;
use App\Models\One\OneAccount;
use App\Models\Payment\Payment;
use App\Models\Sale\BookingOrder;
use App\Models\Sale\BookingVehicle;
use App\Models\Sale\DocTpl;
use App\Models\Sale\SaleOrder;
use App\Models\Sale\SaleOrderTpl;
use App\Models\Sale\SaleSettlement;
use App\Models\Sale\VehicleReplacement;
use App\Models\Vehicle\ServiceCenter;
use App\Models\Vehicle\Vehicle;
use App\Models\Vehicle\VehicleAccident;
use App\Models\Vehicle\VehicleForceTake;
use App\Models\Vehicle\VehicleInspection;
use App\Models\Vehicle\VehicleInsurance;
use App\Models\Vehicle\VehicleMaintenance;
use App\Models\Vehicle\VehicleManualViolation;
use App\Models\Vehicle\VehicleModel;
use App\Models\Vehicle\VehicleRepair;
use App\Models\Vehicle\VehicleSchedule;

return [
    'dblog' => [
        'models' => [
            (AdminTeam::class)              => 'at_id',
            (Admin::class)                  => 'id',
            (Configuration::class)          => 'cfg_id',
            (DocTpl::class)                 => 'dt_id',
            (Customer::class)               => 'cu_id',
            (CustomerIndividual::class)     => 'cui_id',
            (CustomerCompany::class)        => 'cuc_id',
            (VehicleModel::class)           => 'vm_id',
            (Vehicle::class)                => 've_id',
            (VehicleAccident::class)        => 'va_id',
            (OneAccount::class)             => 'oa_id',
            (VehicleRepair::class)          => 'vr_id',
            (VehicleMaintenance::class)     => 'vm_id',
            (VehicleInspection::class)      => 'vi_id',
            (VehicleManualViolation::class) => 'vmv_id',
            (VehicleForceTake::class)       => 'vft_id',
            (VehicleReplacement::class)     => 'vr_id',
            (VehicleSchedule::class)        => 'vs_id',
            (VehicleInsurance::class)       => 'vi_id',
            (SaleOrder::class)              => 'so_id',
            (SaleOrderTpl::class)           => 'sot_id',
            (SaleSettlement::class)         => 'ss_id',
            (Payment::class)                => 'rp_id',
            (BookingVehicle::class)         => 'bv_id',
            (BookingOrder::class)           => 'bo_id',
            (ServiceCenter::class)          => 'sc_id',
        ],

        'schema' => 'table_log',

        'union' => [
            Customer::class => [
                [CustomerIndividual::class, 'cu_id'],
                [CustomerCompany::class, 'cu_id'],
            ],
        ],
    ],

    'mock' => [
        'enable' => (bool) env('MOCK_ENABLE', false),
    ],

    'gen' => [
        'month' => [
            'limit_size' => 200000,  // 每个表每月最大不超过20万
            'offset'     => env('GEN_MONTH_OFFSET', -12), // 月份修正
        ],

        'factor' => env('GEN_FACTOR', 3),
    ],

    'host_manual' => env('HOST_MANUAL'),

    'super_role' => [
        'name' => env('SUPER_ROLE_NAME', 'Super Admin'),
    ],

    'super_user' => [
        'email'    => env('SUPER_USER_EMAIL', ''),
        'name'     => env('SUPER_USER_NAME', '超级管理员'),
        'password' => env('SUPER_USER_PASSWORD', ''),
    ],

    'wecom' => [
        'corp_id'                      => env('WECOM_CORP_ID'),
        'app_delivery_agent_id'        => env('WECOM_APP_DELIVERY_AGENT_ID'),
        'app_delivery_secret'          => env('WECOM_APP_DELIVERY_SECRET'),
        'app_delivery_token_cache_key' => env('WECOM_APP_DELIVERY_TOKEN_CACHE_KEY', 'wecom:app_delivery:access_token'),
        'cache_ttl_buffer'             => (int) env('WECOM_TOKEN_TTL_BUFFER', 120), // 以秒为单位的安全缓冲，避免刚好过期
    ],
];
