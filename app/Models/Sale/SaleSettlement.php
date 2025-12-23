<?php

namespace App\Models\Sale;

use App\Attributes\ClassName;
use App\Enum\Payment\SsDeleteOption;
use App\Enum\Sale\SsReturnStatus;
use App\Enum\SaleContract\ScPaymentPeriod;
use App\Enum\SaleContract\ScRentalType;
use App\Enum\SaleContract\ScStatus;
use App\Models\_\ModelTrait;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

#[ClassName('结算')]
/**
 * @property int                       $ss_id                         结算序号
 * @property int                       $ss_sc_id                      租车合同序号
 * @property null|float                $ss_deposit_amount             合同押金
 * @property null|float                $ss_received_deposit           实收押金
 * @property null|float                $ss_early_return_penalty       提前退车违约金
 * @property null|float                $ss_overdue_inspection_penalty 逾期年检违约金
 * @property null|float                $ss_overdue_rent               逾期租金
 * @property null|float                $ss_overdue_penalty            逾期违约金
 * @property null|float                $ss_accident_depreciation_fee  出险加速折旧费
 * @property null|float                $ss_insurance_surcharge        保险上浮费用
 * @property null|float                $ss_violation_withholding_fee  违章代扣费用
 * @property null|float                $ss_violation_penalty          违章违约金
 * @property null|float                $ss_repair_fee                 还车定损/维修费
 * @property null|float                $ss_insurance_deductible       保险绝对免赔
 * @property null|float                $ss_forced_collection_fee      强制收车费
 * @property null|float                $ss_other_deductions           其他扣款
 * @property null|string               $ss_other_deductions_remark    其他扣款备注
 * @property null|float                $ss_refund_amount              返还款；客户额外多付的费用
 * @property null|string               $ss_refund_details             返还款明细
 * @property null|float                $ss_settlement_amount          退车结算费
 * @property null|Carbon               $ss_deposit_return_amount      应退押金金额
 * @property null|Carbon               $ss_deposit_return_date        应退押金日期
 * @property null|int|SsReturnStatus   $ss_return_status              退车结算状态
 * @property null|Carbon               $ss_return_datetime            退车结算日时
 * @property null|mixed                $ss_additional_photos          附加照片
 * @property null|mixed|SsDeleteOption $ss_delete_option              是否删除应收款选项
 * @property null|string               $ss_remark                     结算备注
 * @property null|int                  $ss_processed_by               处理人;id
 * @property null|int                  $ss_approved_by                审核人
 * @property null|Carbon               $ss_approved_at                审核时间
 *                                                                    -
 * @property SaleContract              $SaleContract
 */
class SaleSettlement extends Model
{
    use ModelTrait;

    public const CREATED_AT = 'ss_created_at';
    public const UPDATED_AT = 'ss_updated_at';
    public const UPDATED_BY = 'ss_updated_by';

    public const array calcOpts = [
        'ss_received_deposit'           => '-', // 实收押金
        'ss_early_return_penalty'       => '',
        'ss_overdue_inspection_penalty' => '',
        'ss_overdue_rent'               => '',
        'ss_overdue_penalty'            => '',
        'ss_accident_depreciation_fee'  => '',
        'ss_insurance_surcharge'        => '',
        'ss_violation_withholding_fee'  => '',
        'ss_violation_penalty'          => '',
        'ss_repair_fee'                 => '',
        'ss_insurance_deductible'       => '',
        'ss_forced_collection_fee'      => '',
        'ss_other_deductions'           => '',
        'ss_refund_amount'              => '-', // 返还款
    ];

    protected $attributes = [];

    protected $appends = [
    ];

    protected $casts = [
        'ss_return_datetime'     => 'datetime:Y-m-d H:i',
        'ss_deposit_return_date' => 'datetime:Y-m-d',
        'ss_delete_option'       => SsDeleteOption::class,
        'ss_return_status'       => SsReturnStatus::class,
    ];

    protected $primaryKey = 'ss_id';

    protected $guarded = ['ss_id'];

    public function SaleContract(): BelongsTo
    {
        return $this->belongsTo(SaleContract::class, 'ss_sc_id', 'sc_id');
    }

    public static function indexQuery(): Builder
    {
        //        $cu_id = $search['cu_id'] ?? null;
        //        $sc_id = $search['sc_id'] ?? null;

        return DB::query()
            ->from('sale_settlements', 'ss')
            ->leftJoin('sale_contracts as sc', 'sc.sc_id', '=', 'ss.ss_sc_id')
            ->leftJoin('vehicles as ve', 've.ve_id', '=', 'sc.sc_ve_id')
            ->leftJoin('vehicle_models as _vm', '_vm.vm_id', '=', 've.ve_vm_id')
            ->leftJoin('customers as cu', 'cu.cu_id', '=', 'sc.sc_cu_id')
//            ->when($cu_id, function (Builder $query) use ($cu_id) {
//                $query->where('cu.cu_id', '=', $cu_id);
//            })
//            ->when($sc_id, function (Builder $query) use ($sc_id) {
//                $query->where('ss.ss_sc_id', '=', $sc_id);
//            })
//            ->when(
//                null === $cu_id && null === $sc_id,
//                function (Builder $query) {
//                    $query->orderByDesc('ss.ss_id');
//                },
//                function (Builder $query) {
//                    $query->orderBy('ss.ss_id');
//                }
//            )
            ->select('ss.*', 'sc.*', 'cu.*', 've.*', '_vm.vm_brand_name', '_vm.vm_model_name')
            ->addSelect(
                DB::raw(SsReturnStatus::toCaseSQL()),
                DB::raw(SsReturnStatus::toColorSQL()),
                DB::raw(ScRentalType::toCaseSQL()),
                DB::raw(ScPaymentPeriod::toCaseSQL()),
                DB::raw(ScStatus::toCaseSQL()),
                DB::raw("to_char(ss.ss_return_datetime, 'YYYY-MM-DD HH24:MI') as ss_return_datetime_"),
            )
        ;
    }

    public static function indexColumns(): array
    {
        return [
            'Customer.cu_contact_name'                  => fn ($item) => $item->cu_contact_name,
            'Customer.contact_phone'                    => fn ($item) => $item->contact_phone,
            'Vehicle.plate_no'                          => fn ($item) => $item->plate_no,
            'VehicleModel.brand_model'                  => fn ($item) => $item->vm_brand_name.'-'.$item->vm_model_name,
            'SaleSettlement.deposit_amount'             => fn ($item) => $item->deposit_amount,
            'SaleSettlement.received_deposit'           => fn ($item) => $item->received_deposit,
            'SaleSettlement.early_return_penalty'       => fn ($item) => $item->early_return_penalty,
            'SaleSettlement.overdue_inspection_penalty' => fn ($item) => $item->overdue_inspection_penalty,
            'SaleSettlement.overdue_rent'               => fn ($item) => $item->overdue_rent,
            'SaleSettlement.overdue_penalty'            => fn ($item) => $item->overdue_penalty,
            'SaleSettlement.accident_depreciation_fee'  => fn ($item) => $item->accident_depreciation_fee,
            'SaleSettlement.insurance_surcharge'        => fn ($item) => $item->insurance_surcharge,
            'SaleSettlement.violation_withholding_fee'  => fn ($item) => $item->violation_withholding_fee,
            'SaleSettlement.violation_penalty'          => fn ($item) => $item->violation_penalty,
            'SaleSettlement.repair_fee'                 => fn ($item) => $item->repair_fee,
            'SaleSettlement.insurance_deductible'       => fn ($item) => $item->insurance_deductible,
            'SaleSettlement.forced_collection_fee'      => fn ($item) => $item->forced_collection_fee,
            'SaleSettlement.other_deductions'           => fn ($item) => $item->other_deductions,
            'SaleSettlement.other_deductions_remark'    => fn ($item) => $item->other_deductions_remark,
            'SaleSettlement.refund_amount'              => fn ($item) => $item->refund_amount,
            'SaleSettlement.refund_details'             => fn ($item) => $item->refund_details,
            'SaleSettlement.settlement_amount'          => fn ($item) => $item->settlement_amount,
            'SaleSettlement.deposit_return_date'        => fn ($item) => $item->deposit_return_date,
            'SaleSettlement.ss_return_status'           => fn ($item) => $item->return_status_label,
            'SaleSettlement.return_datetime'            => fn ($item) => $item->return_datetime_,
            'SaleSettlement.ss_remark'                  => fn ($item) => $item->ss_remark,
        ];
    }

    public static function options(?\Closure $where = null, ?string $key = null): array
    {
        return [];
    }

    protected function ssAdditionalPhotos(): Attribute
    {
        return $this->uploadFileArray();
    }
}
