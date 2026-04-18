<?php

namespace App\Enum\Payment;

use App\Enum\EnumLikeBase;
use App\Enum\SaleContract\ScRentalType;

class PPtId extends EnumLikeBase
{
    public const int RENT                             = 1;
    public const int DEPOSIT                          = 2;
    public const int MANAGEMENT_FEE                   = 3;
    public const int DOWN_PAYMENT                     = 4;
    public const int FINAL_PAYMENT                    = 5;
    public const int OTHER                            = 6;
    public const int REFUND_DEPOSIT                   = 7;
    public const int BASIC_INSURANCE_FEE              = 8;
    public const int ADDITIONAL_INSURANCE_FEE         = 9;
    public const int BREACH_PENALTY                   = 10;
    public const int REPAIR_FEE                       = 11;
    public const int INSURANCE_FEE                    = 12;
    public const int ANNUAL_INSPECTION_FEE            = 13;
    public const int INSURANCE_CLAIM_PAYMENT          = 14;
    public const int SERVICE_FEE                      = 15;
    public const int TRAFFIC_VIOLATION_FINE           = 16;
    public const int MAINTENANCE_FEE                  = 17;
    public const int SALARY_BONUS                     = 18;
    public const int LICENSE_FEE                      = 19;
    public const int SECURITY_DEPOSIT                 = 20;
    public const int PASS_PERMIT_FEE                  = 21;
    public const int INSURANCE_SURCHARGE              = 22;
    public const int VEHICLE_DEPRECIATION             = 23;
    public const int ASSISTANCE_GIFT_INSURANCE_COST   = 24;
    public const int CLAIM_DEPRECIATION_FEE           = 25;
    public const int LATE_FEE                         = 26;
    public const int VIOLATION_DEDUCTION_FEE          = 27;
    public const int FORCED_VEHICLE_RECOVERY_FEE      = 28;
    public const int VEHICLE_GAS_MODIFICATION_FEE     = 29;
    public const int TIRE_REPLACEMENT                 = 30;
    public const int LONG_TERM_RENT                   = 31;
    public const int SELF_OPERATED_RENT               = 32;
    public const int SHORT_TERM_RENT                  = 33;
    public const int DASHCAM_DEPOSIT                  = 34;
    public const int VEHICLE_DAMAGE                   = 35;
    public const int PARKING_FEE                      = 36;
    public const int FUEL_FEE                         = 37;
    public const int CHARGING_FEE                     = 38;
    public const int HIGHWAY_TOLL_FEE                 = 39;
    public const int CAR_WASH_FEE                     = 40;
    public const int PLATFORM_REBATE_DIDI             = 41;
    public const int PLATFORM_REBATE_GAODE_AND_OTHERS = 42;
    public const int VACANCY_PERIOD_AMOUNT            = 43;
    public const int PURCHASE_TAX                     = 44;
    public const int VEHICLE_AND_VESSEL_TAX           = 45;
    public const int INTEREST                         = 46;
    public const int MONTHLY_PAYMENT                  = 47;
    public const int FIRE_EXTINGUISHER                = 48;
    public const int DIESEL_HEATING                   = 49;
    public const int PARTS                            = 50;
    public const int LICENSE_PLATE_QUOTA              = 51;
    public const int TAX                              = 52;
    public const int COMMISSION                       = 53;
    public const int REFERRAL_FEE                     = 54;
    public const int NAVIGATION                       = 55;
    public const int VEHICLE_NETWORK_GPS              = 56;
    public const int DECORATION_UPHOLSTERY_FEE        = 57;
    public const int SHIPPING_FEE                     = 58;
    public const int INSURANCE_LOAN_INTEREST          = 59;
    public const int PARKING_SPACE_RENTAL_FEE         = 60;
    public const int COMMERCIAL_INSURANCE             = 61;
    public const int COMPULSORY_TRAFFIC_INSURANCE     = 62;
    public const int CARRIER_INSURANCE                = 63;
    public const int INSURANCE_REBATE                 = 64;
    public const int DRIVER_REWARD                    = 65;
    public const int BATTERY_RENTAL_FEE               = 66;
    public const int ACCIDENT_COMPENSATION            = 67;
    public const int VEHICLE_PURCHASE_PAYMENT         = 68;
    public const int VEHICLE_RETURN_SETTLEMENT_FEE    = 69;
    public const int PLATFORM_FLOW                    = 70;
    public const int ZHI_ZU_REN                       = 71;
    public const int PLATFORM_FEE                     = 72;
    public const int ROAD_PROTECTION_FEE              = 73;
    public const int RENT_DISCOUNT                    = 74;
    public const int RENT_SURCHARGE                   = 75;
    public const int ADVANCE_LOAN                     = 76;
    public const int DORMITORY_FEE                    = 77;
    public const int LOSS_OF_WORK_COMPENSATION        = 78;

    public const array LABELS = [
        self::RENT                             => '租金',
        self::DEPOSIT                          => '押金',
        self::MANAGEMENT_FEE                   => '管理费',
        self::DOWN_PAYMENT                     => '首付',
        self::FINAL_PAYMENT                    => '尾款',
        self::OTHER                            => '其他',
        self::REFUND_DEPOSIT                   => '退押金',
        self::BASIC_INSURANCE_FEE              => '基础保险费',
        self::ADDITIONAL_INSURANCE_FEE         => '附加保险费',
        self::BREACH_PENALTY                   => '违约金',
        self::REPAIR_FEE                       => '维修费',
        self::INSURANCE_FEE                    => '保险费',
        self::ANNUAL_INSPECTION_FEE            => '年检费',
        self::INSURANCE_CLAIM_PAYMENT          => '保险理赔款',
        self::SERVICE_FEE                      => '服务费',
        self::TRAFFIC_VIOLATION_FINE           => '违章罚款',
        self::MAINTENANCE_FEE                  => '保养费',
        self::SALARY_BONUS                     => '工资奖金',
        self::LICENSE_FEE                      => '牌照费',
        self::SECURITY_DEPOSIT                 => '保证金',
        self::PASS_PERMIT_FEE                  => '通行证费用',
        self::INSURANCE_SURCHARGE              => '保险上浮',
        self::VEHICLE_DEPRECIATION             => '车辆折旧',
        self::ASSISTANCE_GIFT_INSURANCE_COST   => '帮扶赠送保险成本',
        self::CLAIM_DEPRECIATION_FEE           => '出险折旧费',
        self::LATE_FEE                         => '滞纳金',
        self::VIOLATION_DEDUCTION_FEE          => '违章代扣费',
        self::FORCED_VEHICLE_RECOVERY_FEE      => '强制收车费',
        self::VEHICLE_GAS_MODIFICATION_FEE     => '车辆改气费',
        self::TIRE_REPLACEMENT                 => '轮胎更换',
        self::LONG_TERM_RENT                   => '长租租金',
        self::SELF_OPERATED_RENT               => '自营租金',
        self::SHORT_TERM_RENT                  => '短租租金',
        self::DASHCAM_DEPOSIT                  => '记录仪押金',
        self::VEHICLE_DAMAGE                   => '车损',
        self::PARKING_FEE                      => '停车费',
        self::FUEL_FEE                         => '燃油费',
        self::CHARGING_FEE                     => '充电费',
        self::HIGHWAY_TOLL_FEE                 => '高速过路费',
        self::CAR_WASH_FEE                     => '洗车费',
        self::PLATFORM_REBATE_DIDI             => '平台返佣-滴滴',
        self::PLATFORM_REBATE_GAODE_AND_OTHERS => '平台返佣-高德及其他',
        self::VACANCY_PERIOD_AMOUNT            => '空置期金额',
        self::PURCHASE_TAX                     => '购置税',
        self::VEHICLE_AND_VESSEL_TAX           => '车船税',
        self::INTEREST                         => '利息',
        self::MONTHLY_PAYMENT                  => '月供',
        self::FIRE_EXTINGUISHER                => '灭火器',
        self::DIESEL_HEATING                   => '柴暖',
        self::PARTS                            => '配件',
        self::LICENSE_PLATE_QUOTA              => '车牌指标',
        self::TAX                              => '税金',
        self::COMMISSION                       => '提成',
        self::REFERRAL_FEE                     => '转介绍费',
        self::NAVIGATION                       => '导航',
        self::VEHICLE_NETWORK_GPS              => '车联网(GPS)',
        self::DECORATION_UPHOLSTERY_FEE        => '装饰装潢费（脚垫贴膜）',
        self::SHIPPING_FEE                     => '运费',
        self::INSURANCE_LOAN_INTEREST          => '保险贷款利息',
        self::PARKING_SPACE_RENTAL_FEE         => '车位租赁费',
        self::COMMERCIAL_INSURANCE             => '商业险',
        self::COMPULSORY_TRAFFIC_INSURANCE     => '交强险',
        self::CARRIER_INSURANCE                => '承运险',
        self::INSURANCE_REBATE                 => '保险返点',
        self::DRIVER_REWARD                    => '司机奖励',
        self::BATTERY_RENTAL_FEE               => '电池租赁费',
        self::ACCIDENT_COMPENSATION            => '事故金',
        self::VEHICLE_PURCHASE_PAYMENT         => '购车款',
        self::VEHICLE_RETURN_SETTLEMENT_FEE    => '退车结算费',
        self::PLATFORM_FLOW                    => '平台流水',
        self::ZHI_ZU_REN                       => '蜘租人',
        self::PLATFORM_FEE                     => '平台费',
        self::ROAD_PROTECTION_FEE              => '路保',
        self::RENT_DISCOUNT                    => '减免租金',
        self::RENT_SURCHARGE                   => '租金上浮',
        self::ADVANCE_LOAN                     => '预支借款',
        self::DORMITORY_FEE                    => '宿舍费',
        self::LOSS_OF_WORK_COMPENSATION        => '误工费',
    ];

    public const array defaultRequiredTypes = [
        self::RENT,           // 租金
        self::DEPOSIT,        // 押金
        self::MANAGEMENT_FEE, // 管理费
        self::VEHICLE_RETURN_SETTLEMENT_FEE, // 结算的时候产生的退车结算费
        self::VEHICLE_DAMAGE, // 验车时候产生的车损
        self::MAINTENANCE_FEE, // 保养的时候产生的保养费
        self::REPAIR_FEE, // 维修的时候产生的维修费
        self::SERVICE_FEE,
        self::BASIC_INSURANCE_FEE,
        self::ADDITIONAL_INSURANCE_FEE,
        self::OTHER,
    ];

    public const array defaultActiveTypes = [
    ];

    public static function getFeeTypes($term): array
    {
        return match ($term) {
            ScRentalType::LONG_TERM => [
                'sc_deposit_amount'        => PPtId::DEPOSIT,
                'sc_management_fee_amount' => PPtId::MANAGEMENT_FEE,
            ],
            ScRentalType::SHORT_TERM => [
                'sc_deposit_amount'                  => PPtId::DEPOSIT,
                'sc_management_fee_amount'           => PPtId::MANAGEMENT_FEE,
                'sc_total_rent_amount'               => PPtId::SHORT_TERM_RENT,
                'sc_insurance_base_fee_amount'       => PPtId::BASIC_INSURANCE_FEE,
                'sc_insurance_additional_fee_amount' => PPtId::ADDITIONAL_INSURANCE_FEE,
                'sc_other_fee_amount'                => PPtId::OTHER,
            ],
        };
    }
}
