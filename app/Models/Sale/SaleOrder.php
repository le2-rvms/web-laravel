<?php

namespace App\Models\Sale;

use App\Attributes\ClassName;
use App\Attributes\ColumnDesc;
use App\Attributes\ColumnType;
use App\Enum\Payment\RpPayStatus;
use App\Enum\Payment\RpPtId;
use App\Enum\Sale\SoOrderStatus;
use App\Enum\Sale\SoPaymentDay_Month;
use App\Enum\Sale\SoPaymentDay_Week;
use App\Enum\Sale\SoPaymentDayType;
use App\Enum\Sale\SoRentalType;
use App\Enum\Sale\SoRentalType_Short;
use App\Enum\Sale\SoRentalType_ShortOnlyShort;
use App\Exceptions\ClientException;
use App\Http\Controllers\Admin\Sale\SaleOrderController;
use App\Models\_\Company;
use App\Models\_\ImportTrait;
use App\Models\_\ModelTrait;
use App\Models\Customer\Customer;
use App\Models\Payment\Payment;
use App\Models\Vehicle\Vehicle;
use App\Rules\PaymentDayCheck;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\TblWidth;

#[ClassName('租车订单')]
#[ColumnDesc('so_id')]
#[ColumnDesc('rental_type', required: true, enum_class: SoRentalType_Short::class)]
#[ColumnDesc('payment_day_type', required: true, enum_class: SoPaymentDayType::class)]
#[ColumnDesc('cu_id')]
#[ColumnDesc('contact_name', required: true)]
#[ColumnDesc('plate_no', required: true)]
#[ColumnDesc('contract_number', required: true, unique: true)]
#[ColumnDesc('rental_start', type: ColumnType::DATE, required: true)]
#[ColumnDesc('rental_days', required: true)]
#[ColumnDesc('installments', required: true)]
#[ColumnDesc('rental_end', type: ColumnType::DATE, required: true)]
#[ColumnDesc('deposit_amount', required: true)]
#[ColumnDesc('management_fee_amount', required: true)]
#[ColumnDesc('rent_amount', required: true)]
#[ColumnDesc('payment_day', required: true, desc: '填入1表示星期1或每月1号')]
#[ColumnDesc('total_rent_amount', required: true)]
#[ColumnDesc('insurance_base_fee_amount', )]
#[ColumnDesc('insurance_additional_fee_amount')]
#[ColumnDesc('other_fee_amount')]
#[ColumnDesc('total_amount', required: true)]
#[ColumnDesc('order_status', required: true, enum_class: SoOrderStatus::class)]
#[ColumnDesc('order_at', type: ColumnType::DATETIME)]
#[ColumnDesc('signed_at', type: ColumnType::DATETIME)]
#[ColumnDesc('canceled_at', type: ColumnType::DATETIME)]
#[ColumnDesc('completed_at', type: ColumnType::DATETIME)]
#[ColumnDesc('early_termination_at', type: ColumnType::DATETIME)]
/**
 * @property int                                    $so_id                               租车序号
 * @property SoRentalType|SoRentalType_Short|string $rental_type                         租车类型；长租或短租
 * @property string                                 $rental_type_label                   租车类型-中文;长租或短租
 * @property string                                 $rental_type_short_label             租车类型-短中文
 * @property null|SoPaymentDayType|string           $payment_day_type                    付款方式；例如月付预付、月付后付等
 * @property null|string                            $payment_day_type_label              付款类型-中文
 * @property int                                    $cu_id                               客户序号；指向客户表
 * @property int                                    $ve_id                               车辆序号；指向车辆表
 * @property string                                 $contract_number                     合同编号
 * @property int                                    $free_days                           免租天数
 * @property Carbon                                 $rental_start                        合同开始日期
 * @property Carbon                                 $rental_start__zh                    合同开始日期-中文
 * @property null|int                               $installments                        分期数
 * @property int                                    $rental_days                         租期天数;短租的属性
 * @property Carbon                                 $rental_end                          合同结束日期
 * @property Carbon                                 $rental_end__zh                      合同结束日期-中文
 * @property null|float                             $deposit_amount                      一次性押金
 * @property null|string                            $deposit_amount__zh                  一次性押金-中文大写
 * @property null|float                             $management_fee_amount               一次性管理费用
 * @property null|string                            $management_fee_amount__zh           一次性管理费用-中文大写
 * @property null|float                             $rent_amount                         每期租金金额
 * @property null|string                            $rent_amount__zh                     每期租金金额-中文大写
 * @property null|int                               $payment_day                         付款日
 * @property null|string                            $payment_day_label                   付款日-中文
 * @property null|float                             $total_rent_amount                   总计租金金额
 * @property null|string                            $total_rent_amount__zh               总计租金金额-中文大写
 * @property null|float                             $insurance_base_fee_amount           基础保险费金额
 * @property null|string                            $insurance_base_fee_amount__zh       基础保险费金额-中文大写
 * @property null|float                             $insurance_additional_fee_amount     附加保险费总金额
 * @property null|string                            $insurance_additional_fee_amount__zh 附加保险费总金额-中文大写
 * @property null|float                             $other_fee_amount                    其他费总金额
 * @property null|string                            $other_fee_amount__zh                其他费总金额-中文大写
 * @property null|string                            $total_amount                        总计金额
 * @property null|string                            $total_amount__zh                    总计金额-中文大写
 * @property mixed|SoOrderStatus                    $order_status                        合同状态；例如未签约、已签约、已完成等
 * @property null|string                            $order_status_label                  合同状态-中文
 * @property null|Carbon                            $order_at                            订单日时
 * @property null|Carbon                            $signed_at                           签约日时
 * @property null|Carbon                            $canceled_at                         取消日时
 * @property Carbon                                 $completed_at                        结算日时
 * @property Carbon                                 $early_termination_at                提前结算日时
 * @property callable                               $payments_phpword_func               计划收款表-表格; 生成 docx 文件使用
 * @property null|array                             $additional_photos                   附加照片
 * @property null|array                             $additional_file                     附加文件
 * @property null|string                            $cus_1                               自定义合同内容1
 * @property null|string                            $cus_2                               自定义合同内容2
 * @property null|string                            $cus_3                               自定义合同内容3
 * @property null|string                            $discount_plan                       优惠方案
 * @property null|string                            $so_remark                           订单备注
 *                                                                                       --
 * @property Customer                               $Customer
 * @property SaleSettlement                         $SaleSettlement
 * @property Vehicle                                $Vehicle
 * @property Collection<Payment>                    $Payments
 * @property SaleOrderExt                           $SaleOrderExt
 */
class SaleOrder extends Model
{
    use ModelTrait;

    use ImportTrait;

    protected $primaryKey = 'so_id';

    protected $guarded = ['so_id'];

    protected $casts = [
        'rental_start'         => 'date:Y-m-d',
        'rental_end'           => 'date:Y-m-d',
        'order_at'             => 'datetime:Y-m-d H:i',
        'signed_at'            => 'datetime:Y-m-d H:i',
        'canceled_at'          => 'datetime:Y-m-d H:i',
        'completed_at'         => 'datetime:Y-m-d H:i',
        'early_termination_at' => 'datetime:Y-m-d H:i',
        'rental_type'          => SoRentalType::class,
        'payment_day_type'     => SoPaymentDayType::class,
        'order_status'         => SoOrderStatus::class,
    ];

    protected $attributes = [];

    protected $appends = [
        'rental_type_label',
        'rental_type_short_label',
        'payment_day_type_label',
        'payment_day_label',
        'order_status_label',
        'so_full_label',
        'rental_start__zh',
        'rental_end__zh',
        'rent_amount__zh',
        'total_rent_amount__zh',
        'total_amount__zh',
        'deposit_amount__zh',
        'management_fee_amount__zh',
        'insurance_base_fee_amount__zh',
        'insurance_additional_fee_amount__zh',
        'other_fee_amount__zh',
    ];

    public function Customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'cu_id', 'cu_id');
    }

    public function Vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 've_id', 've_id')->with('VehicleModel');
    }

    public function Payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'so_id', 'so_id')->with('PaymentType');
    }

    public function SaleOrderExt(): HasOne
    {
        return $this->HasOne(SaleOrderExt::class, 'so_id', 'so_id');
    }

    /**
     * 签约时需要支付的.
     */
    public function SignPayments(): HasMany
    {
        return $this->hasMany(Payment::class, 'so_id', 'so_id')
            ->whereIn('pt_id', [RpPtId::DEPOSIT, RpPtId::MANAGEMENT_FEE])
            ->with('PaymentType')
        ;
    }

    /**
     * 未支付的租金列表.
     */
    public function UnpaidRentPayments(): HasMany
    {
        return $this->hasMany(Payment::class, 'so_id', 'so_id')
            ->whereIn('pt_id', [RpPtId::RENT])
            ->where('pay_status', '=', RpPayStatus::UNPAID)
            ->with('PaymentType')
        ;
    }

    public function SaleSettlement(): HasOne
    {
        return $this->hasOne(SaleSettlement::class, 'so_id', 'so_id');
    }

    public function check_order_status(array $order_statuses, Validator $validator): bool
    {
        if ($order_statuses && !in_array($this->order_status, $order_statuses)) {
            $validator->errors()->add('so_id', '租车状态不应该为：'.$this->order_status->label);

            return false;
        }

        return true;
    }

    public static function indexQuery(array $search = []): Builder
    {
        $cu_id = $search['cu_id'] ?? null;

        return DB::query()
            ->from('sale_orders', 'so')
            ->leftJoin('vehicles as ve', 've.ve_id', '=', 'so.ve_id')
            ->leftJoin('vehicle_models as _vm', '_vm.vm_id', '=', 've.vm_id')
            ->leftJoin('customers as cu', 'cu.cu_id', '=', 'so.cu_id')
            ->when($cu_id, function (Builder $query) use ($cu_id) {
                $query->where('cu.cu_id', '=', $cu_id);
            })
            ->orderByDesc('so.so_id')
            ->select('so.*', 'cu.*', 've.*', '_vm.brand_name', '_vm.model_name')
            ->addSelect(
                DB::raw(SoRentalType_Short::toCaseSQL()),
                DB::raw(SoPaymentDayType::toCaseSQL()),
                DB::raw(SoOrderStatus::toCaseSQL()),
                DB::raw(SoOrderStatus::toColorSQL()),
                DB::raw("to_char(so.order_at, 'YYYY-MM-DD HH24:MI:SS') as order_at_"),
                DB::raw("to_char(so.signed_at, 'YYYY-MM-DD HH24:MI:SS') as signed_at_"),
                DB::raw("to_char(so.canceled_at, 'YYYY-MM-DD HH24:MI:SS') as canceled_at_"),
                DB::raw("to_char(so.completed_at, 'YYYY-MM-DD HH24:MI:SS') as completed_at_"),
                DB::raw("to_char(so.early_termination_at, 'YYYY-MM-DD HH24:MI:SS') as early_termination_at_"),
            )
        ;
    }

    public static function indexColumns(): array
    {
        return [
            'SaleOrder.rental_type'                     => fn ($item) => $item->rental_type_label,
            'SaleOrder.payment_day_type'                => fn ($item) => $item->payment_day_type_label,
            'Customer.contact_name'                     => fn ($item) => $item->contact_name,
            'Customer.contact_phone'                    => fn ($item) => $item->contact_phone,
            'Vehicle.plate_no'                          => fn ($item) => $item->plate_no,
            'VehicleModel.brand_model'                  => fn ($item) => $item->brand_name.'-'.$item->model_name,
            'SaleOrder.contract_number'                 => fn ($item) => $item->contract_number,
            'SaleOrder.rental_start'                    => fn ($item) => $item->rental_start,
            'SaleOrder.installments'                    => fn ($item) => $item->installments,
            'SaleOrder.rental_end'                      => fn ($item) => $item->rental_end,
            'SaleOrder.deposit_amount'                  => fn ($item) => $item->deposit_amount,
            'SaleOrder.management_fee_amount'           => fn ($item) => $item->management_fee_amount,
            'SaleOrder.rent_amount'                     => fn ($item) => $item->rent_amount,
            'SaleOrder.payment_day'                     => fn ($item) => $item->payment_day,
            'SaleOrder.total_rent_amount'               => fn ($item) => $item->total_rent_amount,
            'SaleOrder.insurance_base_fee_amount'       => fn ($item) => $item->insurance_base_fee_amount,
            'SaleOrder.insurance_additional_fee_amount' => fn ($item) => $item->insurance_additional_fee_amount,
            'SaleOrder.other_fee_amount'                => fn ($item) => $item->other_fee_amount,
            'SaleOrder.total_amount'                    => fn ($item) => $item->total_amount,
            'SaleOrder.order_status'                    => fn ($item) => $item->order_status_label,
            'SaleOrder.order_at'                        => fn ($item) => $item->order_at_,
            'SaleOrder.signed_at'                       => fn ($item) => $item->signed_at_,
            'SaleOrder.canceled_at'                     => fn ($item) => $item->canceled_at_,
            'SaleOrder.completed_at'                    => fn ($item) => $item->completed_at_,
            'SaleOrder.early_termination_at'            => fn ($item) => $item->early_termination_at_,
        ];
    }

    public static function CustomerHasVeId(): Builder
    {
        $cu_id = auth()->id();

        return DB::query()
            ->from('sale_orders', 'so')
            ->where('so.cu_id', '=', $cu_id)
            ->whereIn('so.order_status', [SoOrderStatus::SIGNED])
            ->select('so.ve_id')
        ;
    }

    public static function options(?\Closure $where = null): array
    {
        $key = preg_replace('/^.*\\\/', '', get_called_class()).'Options';

        $value = DB::query()
            ->from('sale_orders', 'so')
            ->leftJoin('vehicles as ve', 've.ve_id', '=', 'so.ve_id')
            ->leftJoin('customers as cu', 'cu.cu_id', '=', 'so.cu_id')
            ->where($where)
            ->orderBy('so.so_id', 'desc')
            ->select(
                DB::raw(sprintf(
                    "CONCAT(cu.contact_name,'|',%s,'|', ve.plate_no ,'|',  %s, %s ,'|', %s ) as text,so.so_id as value",
                    "(CONCAT(SUBSTRING(cu.contact_phone, 1, 0), '', SUBSTRING(cu.contact_phone, 8, 4)) )",
                    SoPaymentDayType::toCaseSQL(false),
                    SoRentalType_ShortOnlyShort::toCaseSQL(false),
                    SoOrderStatus::toCaseSQL(false)
                ))
            )
            ->get()->toArray()
        ;

        return [$key => $value];
    }

    public function getRentalStartZhAttribute(): ?string
    {
        $raw = $this->attributes['rental_start'] ?? null;
        if (!$raw) {
            return null;
        }

        return Carbon::parse($raw)
            ->locale('zh_CN')
            ->translatedFormat('Y年m月d日')
        ;
    }

    public function getRentalEndZhAttribute(): ?string
    {
        $raw = $this->attributes['rental_end'] ?? null;
        if (!$raw) {
            return null;
        }

        return Carbon::parse($raw)
            ->locale('zh_CN')
            ->translatedFormat('Y年m月d日')
        ;
    }

    public function getRentAmountZhAttribute(): string
    {
        return money_format_zh($this->getAttribute('rent_amount'));
    }

    public function getTotalRentAmountZhAttribute()
    {
        // 简单相乘计算总租金
        return money_format_zh($this->getAttribute('total_rent_amount'));
    }

    public function getTotalAmountZhAttribute()
    {
        // 简单相乘计算总租金
        return money_format_zh($this->getAttribute('total_amount'));
    }

    public function getDepositAmountZhAttribute(): string
    {
        return money_format_zh($this->getAttribute('deposit_amount'));
    }

    public function getManagementFeeAmountZhAttribute(): string
    {
        return money_format_zh($this->getAttribute('management_fee_amount') ?? '0');
    }

    public function getInsuranceBaseFeeAmountZhAttribute(): string
    {
        return money_format_zh($this->getAttribute('insurance_base_fee_amount') ?? '0');
    }

    public function getInsuranceAdditionalFeeAmountZhAttribute(): string
    {
        return money_format_zh($this->getAttribute('insurance_additional_fee_amount') ?? '0');
    }

    public function getOtherFeeAmountZhAttribute(): string
    {
        return money_format_zh($this->getAttribute('other_fee_amount') ?? '0');
    }

    public function payments_phpword_func($tp, $rule_label)
    {
        $table = new Table([
            'borderSize'  => 2,
            'borderColor' => '000000',
            'width'       => 10000,
            'unit'        => TblWidth::TWIP,
        ]);

        $table->addRow();
        $table->addCell(5)->addText(
            '付款类型',
            $fStyle = ['bold' => true],
            $pStyle = ['alignment' => Jc::CENTER]
        );
        $table->addCell(10)->addText('应付日期', $fStyle, $pStyle);
        $table->addCell(5)->addText('应付金额', $fStyle, $pStyle);
        $table->addCell(20)->addText('备注', $fStyle, $pStyle);

        foreach ($this->Payments as $payment) {
            $table->addRow();
            $table->addCell()->addText($payment->PaymentType->pt_name, [], ['alignment' => Jc::CENTER]);
            $table->addCell()->addText($payment->should_pay_date.'('.$payment->day_of_week_name.')');
            $table->addCell()->addText($payment->should_pay_amount);
            $table->addCell()->addText($payment->rp_remark);
        }

        $tp->setComplexBlock($rule_label, $table);
    }

    public static function contractNumberKv(?string $contract_number = null)
    {
        static $kv = null;

        if (null === $kv) {
            $kv = DB::query()
                ->from('sale_orders')
                ->select('so_id', 'contract_number')
                ->pluck('so_id', 'contract_number')
                ->toArray()
            ;
        }

        if ($contract_number) {
            return $kv[$contract_number] ?? null;
        }

        return $kv;
    }

    public function Company(): BelongsTo
    {
        return $this->belongsTo(Company::class)->withDefault(
            Company::query()->firstOrNew()->toArray()
        );
    }

    public static function importColumns(): array
    {
        return [
            'rental_type'                     => [SaleOrder::class, 'rental_type'],
            'payment_day_type'                => [SaleOrder::class, 'payment_day_type'],
            'contact_name'                    => [Customer::class, 'contact_name'],
            'contact_phone'                   => [Customer::class, 'contact_phone'],
            'plate_no'                        => [Vehicle::class, 'plate_no'],
            'contract_number'                 => [SaleOrder::class, 'contract_number'],
            'rental_start'                    => [SaleOrder::class, 'rental_start'],
            'installments'                    => [SaleOrder::class, 'installments'],
            'rental_end'                      => [SaleOrder::class, 'rental_end'],
            'deposit_amount'                  => [SaleOrder::class, 'deposit_amount'],
            'management_fee_amount'           => [SaleOrder::class, 'management_fee_amount'],
            'rent_amount'                     => [SaleOrder::class, 'rent_amount'],
            'payment_day'                     => [SaleOrder::class, 'payment_day'],
            'total_rent_amount'               => [SaleOrder::class, 'total_rent_amount'],
            'insurance_base_fee_amount'       => [SaleOrder::class, 'insurance_base_fee_amount'],
            'insurance_additional_fee_amount' => [SaleOrder::class, 'insurance_additional_fee_amount'],
            'other_fee_amount'                => [SaleOrder::class, 'other_fee_amount'],
            'total_amount'                    => [SaleOrder::class, 'total_amount'],
            'order_status'                    => [SaleOrder::class, 'order_status'],
            'order_at'                        => [SaleOrder::class, 'order_at'],
            'signed_at'                       => [SaleOrder::class, 'signed_at'],
            'canceled_at'                     => [SaleOrder::class, 'canceled_at'],
            'completed_at'                    => [SaleOrder::class, 'completed_at'],
            'early_termination_at'            => [SaleOrder::class, 'early_termination_at'],
        ];
    }

    public static function importBeforeValidateDo(): \Closure
    {
        return function (&$item) {
            $item['ve_id']            = Vehicle::plateNoKv($item['plate_no'] ?? null);
            $item['cu_id']            = Customer::plateNoKv($item['contact_phone'] ?? null);
            $item['rental_type']      = SoRentalType_Short::searchValue($item['rental_type'] ?? null);
            $item['payment_day_type'] = SoPaymentDayType::searchValue($item['payment_day_type'] ?? null);
            $item['order_status']     = SoOrderStatus::searchValue($item['order_status'] ?? null);

            static::$fields['contract_number'][] = $item['contract_number'] ?? null;
        };
    }

    public static function importValidatorRule(array $item, array $fieldAttributes): void
    {
        $validator1 = \Illuminate\Support\Facades\Validator::make(
            $item,
            [
                'rental_type' => ['bail', 'required', Rule::in(SoRentalType::label_keys())],
            ],
            [],
            $fieldAttributes
        );
        if ($validator1->fails()) {
            throw new ValidationException($validator1);
        }

        $input1        = $validator1->validated();
        $rental_type   = $input1['rental_type'];
        $is_long_term  = SoRentalType::LONG_TERM === $rental_type;
        $is_short_term = SoRentalType::SHORT_TERM === $rental_type;

        $validator2 = \Illuminate\Support\Facades\Validator::make(
            $item,
            [
                'payment_day_type' => ['bail', Rule::requiredIf($is_long_term), Rule::excludeIf($is_short_term), 'string', Rule::in(SoPaymentDayType::label_keys())],
            ],
            [],
            $fieldAttributes
        );

        if ($validator2->fails()) {
            throw new ValidationException($validator2);
        }

        $input2 = $validator2->validated();

        $payment_day_type = $input2['payment_day_type'] ?? null;

        $rules = [
            'cu_id'           => ['bail', 'required', 'integer'],
            've_id'           => ['bail', 'required', 'integer'],
            'contract_number' => ['bail', 'required', 'string', 'max:50', Rule::unique(SaleOrder::class, 'contract_number')],
            'free_days'       => ['bail', 'nullable', 'int:4'],
            'rental_start'    => ['bail', 'required', 'date', 'before_or_equal:rental_end'],
            'installments'    => ['bail', Rule::requiredIf($is_long_term), Rule::excludeIf($is_short_term), 'integer', 'min:1'],
            'rental_end'      => ['bail', 'required', 'date', 'after_or_equal:rental_start'],

            'deposit_amount'                  => ['bail', 'required', 'decimal:0,2', 'gte:0'],
            'management_fee_amount'           => ['bail', 'nullable', 'decimal:0,2', 'gte:0'],
            'rent_amount'                     => ['bail', 'required', 'numeric', 'min:0'],
            'payment_day'                     => ['bail', Rule::requiredIf($is_long_term), Rule::excludeIf($is_short_term), 'integer', new PaymentDayCheck($payment_day_type)],
            'total_rent_amount'               => ['bail', Rule::requiredIf($is_short_term), Rule::excludeIf($is_long_term), 'numeric', 'min:0'],
            'insurance_base_fee_amount'       => ['bail', Rule::requiredIf($is_short_term), Rule::excludeIf($is_long_term), 'numeric', 'min:0'],
            'insurance_additional_fee_amount' => ['bail', Rule::requiredIf($is_short_term), Rule::excludeIf($is_long_term), 'numeric', 'min:0'],
            'other_fee_amount'                => ['bail', Rule::requiredIf($is_short_term), Rule::excludeIf($is_long_term), 'numeric', 'min:0'],
            'total_amount'                    => ['bail', Rule::requiredIf($is_short_term), Rule::excludeIf($is_long_term), 'numeric', 'min:0'],

            'cus_1'         => ['bail', 'nullable', 'max:255'],
            'cus_2'         => ['bail', 'nullable', 'max:255'],
            'cus_3'         => ['bail', 'nullable', 'max:255'],
            'discount_plan' => ['bail', 'nullable', 'max:255'],
            'so_remark'     => ['bail', 'nullable', 'max:255'],
        ];

        $validator = \Illuminate\Support\Facades\Validator::make($item, $rules, [], $fieldAttributes);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    public static function importAfterValidatorDo(): \Closure
    {
        return function () {
            // contract_number
            $contract_number = SaleOrder::query()->whereIn('contract_number', static::$fields['contract_number'])->pluck('contract_number')->toArray();
            if (count($contract_number) > 0) {
                throw new ClientException('以下合同编号已存在：'.join(',', $contract_number));
            }
        };
    }

    public static function importCreateDo(): \Closure
    {
        return function ($input) {
            $saleOrder = SaleOrder::query()->create($input);

            if (1 - 1) {
                $subRequest = Request::create(
                    '',      // 仅是占位 URI，不会真的去路由
                    'GET',
                    $input    // 你的业务参数
                );

                static $SaleOrderController = null;

                if (null === $SaleOrderController) {
                    $SaleOrderController = App::make(SaleOrderController::class);
                }

                $response = App::call(
                    [$SaleOrderController, 'paymentsOption'],
                    ['request' => $subRequest]
                );

                $payments = $response->original['data'];

                foreach ($payments as $payment) {
                    $saleOrder->Payments()->create($payment);
                }
            }
        };
    }

    protected function paymentDayTypeLabel(): Attribute
    {
        return Attribute::make(
            get : fn () => $this->getAttribute('payment_day_type')?->label
        );
    }

    protected function paymentDayLabel(): Attribute
    {
        return Attribute::make(
            get: function () {
                $payment_day_type = $this->getAttribute('payment_day_type');
                if (null === $payment_day_type) {
                    return null;
                }
                $map = str_starts_with('week', $payment_day_type) ? SoPaymentDay_Week::LABELS : SoPaymentDay_Month::LABELS;

                $payment_day = $this->getOriginal('payment_day');
                if (null === $payment_day) {
                    return null;
                }

                return $map[$payment_day] ?? '';
            }
        );
    }

    protected function orderStatusLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('order_status')?->label ?? null
        );
    }

    protected function rentalTypeLabel(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->getAttribute('rental_type')?->label
        );
    }

    protected function rentalTypeShortLabel(): Attribute
    {
        return Attribute::make(
            get : function ($value) {
                $rental_type = $this->getRawOriginal('rental_type');
                if (is_string($rental_type)) {
                    $rental_type = SoRentalType_Short::tryFrom($rental_type);
                }

                return $rental_type?->label;
            }
        );
    }

    protected function soFullLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => join(' | ', array_filter([
                $this->Customer?->getOriginal('contact_name'),
                substr($this->Customer?->getOriginal('contact_phone'), -4),
                $this->Vehicle?->getOriginal('plate_no'),
                $this->getOriginal('rental_type_short_label'),
                $this->getOriginal('payment_day_type_label'),
                $this->getOriginal('order_status_label'),
            ]))
        );
    }

    protected function additionalFile(): Attribute
    {
        return $this->uploadFile();
    }

    protected function additionalPhotos(): Attribute
    {
        return $this->uploadFileArray();
    }
}
