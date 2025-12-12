<?php

namespace App\Enum\Sale;

use App\Enum\EnumLikeBase;
use App\Models\Payment\Payment;
use App\Models\Sale\SaleContract;
use App\Models\Sale\SaleSettlement;
use App\Models\Vehicle\VehicleInspection;

class DtDtType extends EnumLikeBase
{
    public const string SALE_CONTRACT   = SaleContract::class;
    public const string SALE_SETTLEMENT = SaleSettlement::class;

    public const string PAYMENT = Payment::class;

    public const string VEHICLE_INSPECTION = VehicleInspection::class;

    public const array LABELS = [
        self::SALE_CONTRACT      => '租车合同文件模板',
        self::SALE_SETTLEMENT    => '结算单文件模板',
        self::PAYMENT            => '财务收据文件模板',
        self::VEHICLE_INSPECTION => '验车单文件模板',
    ];

    public function getFieldsAndRelations(bool $kv = false, bool $valueOnly = false): array
    {
        $value = null;

        switch ($this->value) {
            case self::SALE_CONTRACT:
                $saleContract = new SaleContract();
                if ($valueOnly) {
                    $saleContract->setFieldMode(false);
                }
                $value = $saleContract->getFieldsAndRelations(['Customer', 'Customer.CustomerIndividual', 'Vehicle', 'Company', 'Vehicle.VehicleInsurances']);

                if ($kv) {
                    $key = self::SALE_CONTRACT.'Fields';

                    $value = [$key => $value];
                }

                break;

            case self::SALE_SETTLEMENT:
                $SaleSettlement = new SaleSettlement();
                if ($valueOnly) {
                    $SaleSettlement->setFieldMode(false);
                }
                $value = $SaleSettlement->getFieldsAndRelations(['SaleContract', 'SaleContract.Customer', 'SaleContract.Vehicle']);
                if ($kv) {
                    $key = self::SALE_SETTLEMENT.'Fields';

                    $value = [$key => $value];
                }

                break;

            case self::PAYMENT:
                $Payment = new Payment();
                if ($valueOnly) {
                    $Payment->setFieldMode(false);
                }
                $value = $Payment->getFieldsAndRelations(['PaymentAccount', 'PaymentType', 'SaleContract', 'SaleContract.Customer']);
                if ($kv) {
                    $key = self::PAYMENT.'Fields';

                    $value = [$key => $value];
                }

                break;

            case self::VEHICLE_INSPECTION:
                $VehicleInspection = new VehicleInspection();
                if ($valueOnly) {
                    $VehicleInspection->setFieldMode(false);
                }
                $value = $VehicleInspection->getFieldsAndRelations(['Vehicle', 'SaleContract', 'SaleContract.Customer']);
                if ($kv) {
                    $key = self::VEHICLE_INSPECTION.'Fields';

                    $value = [$key => $value];
                }
        }

        return $value;
    }
}
