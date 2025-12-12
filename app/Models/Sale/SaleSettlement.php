<?php

namespace App\Models\Sale;

use App\Attributes\ClassName;
use App\Enum\Payment\RsDeleteOption;
use App\Enum\Sale\ScPaymentDayType;
use App\Enum\Sale\ScRentalType;
use App\Enum\Sale\ScScStatus;
use App\Enum\Sale\SsReturnStatus;
use App\Models\_\ModelTrait;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

#[ClassName('结算')]
/**
 * @property int                       $ss_id                      结算序号
 * @property int                       $sc_id                      租车合同序号
 * @property null|float                $deposit_amount             合同押金
 * @property null|float                $received_deposit           实收押金
 * @property null|float                $early_return_penalty       提前退车违约金
 * @property null|float                $overdue_inspection_penalty 逾期年检违约金
 * @property null|float                $overdue_rent               逾期租金
 * @property null|float                $overdue_penalty            逾期违约金
 * @property null|float                $accident_depreciation_fee  出险加速折旧费
 * @property null|float                $insurance_surcharge        保险上浮费用
 * @property null|float                $violation_withholding_fee  违章代扣费用
 * @property null|float                $violation_penalty          违章违约金
 * @property null|float                $repair_fee                 还车定损/维修费
 * @property null|float                $insurance_deductible       保险绝对免赔
 * @property null|float                $forced_collection_fee      强制收车费
 * @property null|float                $other_deductions           其他扣款
 * @property null|string               $other_deductions_remark    其他扣款备注
 * @property null|float                $refund_amount              返还款；客户额外多付的费用
 * @property null|string               $refund_details             返还款明细
 * @property null|float                $settlement_amount          退车结算费
 * @property null|Carbon               $deposit_return_amount      应退押金金额
 * @property null|Carbon               $deposit_return_date        应退押金日期
 * @property null|int|SsReturnStatus   $return_status              退车结算状态
 * @property null|Carbon               $return_datetime            退车结算日时
 * @property null|mixed                $additional_photos          附加照片
 * @property null|mixed|RsDeleteOption $delete_option              是否删除应收款选项
 * @property null|string               $ss_remark                  结算备注
 * @property null|int                  $processed_by               处理人;id
 * @property null|int                  $approved_by                审核人
 * @property null|Carbon               $approved_at                审核时间
 *                                                                 -
 * @property SaleContract              $SaleContract
 */
class SaleSettlement extends Model
{
    use ModelTrait;

    public const array calcOpts = [
        'received_deposit'           => '-', // 实收押金
        'early_return_penalty'       => '',
        'overdue_inspection_penalty' => '',
        'overdue_rent'               => '',
        'overdue_penalty'            => '',
        'accident_depreciation_fee'  => '',
        'insurance_surcharge'        => '',
        'violation_withholding_fee'  => '',
        'violation_penalty'          => '',
        'repair_fee'                 => '',
        'insurance_deductible'       => '',
        'forced_collection_fee'      => '',
        'other_deductions'           => '',
        'refund_amount'              => '-', // 返还款
    ];

    //    public $incrementing = false;

    protected $attributes = [];

    protected $appends = [
    ];

    protected $casts = [
        'return_datetime'     => 'datetime:Y-m-d H:i',
        'deposit_return_date' => 'datetime:Y-m-d',
        'delete_option'       => RsDeleteOption::class,
        'return_status'       => SsReturnStatus::class,
    ];

    protected $primaryKey = 'ss_id';

    protected $guarded = ['ss_id'];

    public function SaleContract(): BelongsTo
    {
        return $this->belongsTo(SaleContract::class, 'sc_id', 'sc_id');
    }

    public static function indexQuery(array $search = []): Builder
    {
        $cu_id = $search['cu_id'] ?? null;
        $sc_id = $search['sc_id'] ?? null;

        return DB::query()
            ->from('sale_settlements', 'ss')
            ->leftJoin('sale_contracts as sc', 'sc.sc_id', '=', 'ss.sc_id')
            ->leftJoin('vehicles as ve', 've.ve_id', '=', 'sc.ve_id')
            ->leftJoin('vehicle_models as _vm', '_vm.vm_id', '=', 've.vm_id')
            ->leftJoin('customers as cu', 'cu.cu_id', '=', 'sc.cu_id')
            ->when($cu_id, function (Builder $query) use ($cu_id) {
                $query->where('cu.cu_id', '=', $cu_id);
            })
            ->when($sc_id, function (Builder $query) use ($sc_id) {
                $query->where('ss.sc_id', '=', $sc_id);
            })
            ->when(
                null === $cu_id && null === $sc_id,
                function (Builder $query) {
                    $query->orderByDesc('ss.ss_id');
                },
                function (Builder $query) {
                    $query->orderBy('ss.ss_id');
                }
            )
            ->select('ss.*', 'sc.*', 'cu.*', 've.*', '_vm.brand_name', '_vm.model_name')
            ->addSelect(
                DB::raw(SsReturnStatus::toCaseSQL()),
                DB::raw(SsReturnStatus::toColorSQL()),
                DB::raw(ScRentalType::toCaseSQL()),
                DB::raw(ScPaymentDayType::toCaseSQL()),
                DB::raw(ScScStatus::toCaseSQL()),
                DB::raw("to_char(ss.return_datetime, 'YYYY-MM-DD HH24:MI') as return_datetime_"),
            )
        ;
    }

    public static function indexColumns(): array
    {
        return [
            'Customer.contact_name'                     => fn ($item) => $item->contact_name,
            'Customer.contact_phone'                    => fn ($item) => $item->contact_phone,
            'Vehicle.plate_no'                          => fn ($item) => $item->plate_no,
            'VehicleModel.brand_model'                  => fn ($item) => $item->brand_name.'-'.$item->model_name,
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
            'SaleSettlement.return_status'              => fn ($item) => $item->return_status_label,
            'SaleSettlement.return_datetime'            => fn ($item) => $item->return_datetime_,
            'SaleSettlement.ss_remark'                  => fn ($item) => $item->ss_remark,
        ];
    }

    public static function options(?\Closure $where = null): array
    {
        return [];
    }

    protected function additionalPhotos(): Attribute
    {
        return $this->uploadFileArray();
    }
}
