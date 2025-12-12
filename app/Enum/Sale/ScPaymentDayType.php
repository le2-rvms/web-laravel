<?php

namespace App\Enum\Sale;

use App\Enum\EnumLikeBase;

class ScPaymentDayType extends EnumLikeBase
{
    public const string MONTHLY_PREPAID      = 'monthly_prepaid';
    public const string MONTHLY_POSTPAID     = 'monthly_postpaid';
    public const string QUARTERLY_PREPAID    = 'quarterly_prepaid';
    public const string QUARTERLY_POSTPAID   = 'quarterly_postpaid';
    public const string WEEKLY_PREPAID       = 'weekly_prepaid';
    public const string WEEKLY_POSTPAID      = 'weekly_postpaid';
    public const string SEMI_ANNUAL_PREPAID  = 'semi_annual_prepaid';
    public const string SEMI_ANNUAL_POSTPAID = 'semi_annual_postpaid';
    public const string ANNUAL_PREPAID       = 'annual_prepaid';
    public const string ANNUAL_POSTPAID      = 'annual_postpaid';

    public const array LABELS = [
        self::WEEKLY_PREPAID       => '周付预付',
        self::WEEKLY_POSTPAID      => '周付后付',
        self::MONTHLY_PREPAID      => '月付预付',
        self::MONTHLY_POSTPAID     => '月付后付',
        self::QUARTERLY_PREPAID    => '季付预付',
        self::QUARTERLY_POSTPAID   => '季付后付',
        self::SEMI_ANNUAL_PREPAID  => '半年付预付',
        self::SEMI_ANNUAL_POSTPAID => '半年付后付',
        self::ANNUAL_PREPAID       => '年付预付',
        self::ANNUAL_POSTPAID      => '年付后付',
    ];

    public const array interval = [
        self::MONTHLY_PREPAID      => ['interval' => 1, 'interval_unit' => 'months', 'prepaid' => true],
        self::MONTHLY_POSTPAID     => ['interval' => 1, 'interval_unit' => 'months', 'prepaid' => false],
        self::QUARTERLY_PREPAID    => ['interval' => 3, 'interval_unit' => 'months', 'prepaid' => true],
        self::QUARTERLY_POSTPAID   => ['interval' => 3, 'interval_unit' => 'months', 'prepaid' => false],
        self::WEEKLY_PREPAID       => ['interval' => 1, 'interval_unit' => 'weeks', 'prepaid' => true],
        self::WEEKLY_POSTPAID      => ['interval' => 1, 'interval_unit' => 'weeks', 'prepaid' => false],
        self::SEMI_ANNUAL_PREPAID  => ['interval' => 6, 'interval_unit' => 'months', 'prepaid' => true],
        self::SEMI_ANNUAL_POSTPAID => ['interval' => 6, 'interval_unit' => 'months', 'prepaid' => false],
        self::ANNUAL_PREPAID       => ['interval' => 12, 'interval_unit' => 'months', 'prepaid' => true],
        self::ANNUAL_POSTPAID      => ['interval' => 12, 'interval_unit' => 'months', 'prepaid' => false],
    ];

    public const array payment_day_classes = [
        self::MONTHLY_PREPAID      => ScPaymentDay_Month::class,
        self::MONTHLY_POSTPAID     => ScPaymentDay_Month::class,
        self::QUARTERLY_PREPAID    => ScPaymentDay_Month::class,
        self::QUARTERLY_POSTPAID   => ScPaymentDay_Month::class,
        self::WEEKLY_PREPAID       => ScPaymentDay_Week::class,
        self::WEEKLY_POSTPAID      => ScPaymentDay_Week::class,
        self::SEMI_ANNUAL_PREPAID  => ScPaymentDay_Month::class,
        self::SEMI_ANNUAL_POSTPAID => ScPaymentDay_Month::class,
        self::ANNUAL_PREPAID       => ScPaymentDay_Month::class,
        self::ANNUAL_POSTPAID      => ScPaymentDay_Month::class,
    ];
}
