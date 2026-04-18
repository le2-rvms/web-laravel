<?php

namespace App\Models\Sale;

use App\Attributes\ClassName;
use App\Attributes\ColumnDesc;
use App\Attributes\ColumnType;
use App\Enum\Payment\PPayStatus;
use App\Enum\Payment\PPtId;
use App\Enum\SaleContract\ScPaymentDay_Month;
use App\Enum\SaleContract\ScPaymentDay_Week;
use App\Enum\SaleContract\ScPaymentPeriod;
use App\Enum\SaleContract\ScRentalType;
use App\Enum\SaleContract\ScRentalType_Short;
use App\Enum\SaleContract\ScRentalType_ShortOnlyShort;
use App\Enum\SaleContract\ScStatus;
use App\Exceptions\ClientException;
use App\Http\Controllers\Admin\Sale\SaleContractController;
use App\Models\_\Company;
use App\Models\_\ImportTrait;
use App\Models\_\ModelTrait;
use App\Models\Customer\Customer;
use App\Models\Payment\Payment;
use App\Models\Vehicle\Vehicle;
use App\Rules\PaymentDayCheck;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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

#[ClassName('租车合同')]
#[ColumnDesc('sc_id')]
#[ColumnDesc('sc_rental_type', required: true, enum_class: ScRentalType_Short::class)]
#[ColumnDesc('sc_payment_period', required: true, enum_class: ScPaymentPeriod::class)]
#[ColumnDesc('sc_cu_id')]
#[ColumnDesc('cu_contact_name', required: true)]
#[ColumnDesc('cu_contact_phone', required: true)]
#[ColumnDesc('ve_plate_no', required: true)]
#[ColumnDesc('sc_no', required: true, unique: true)]
#[ColumnDesc('sc_group_no', required: true, )]
#[ColumnDesc('sc_group_seq', required: true, )]
#[ColumnDesc('sc_start_date', type: ColumnType::DATE, required: true)]
#[ColumnDesc('sc_rental_days', required: true)]
#[ColumnDesc('sc_installments', required: true)]
#[ColumnDesc('sc_end_date', type: ColumnType::DATE, required: true)]
#[ColumnDesc('sc_deposit_amount', required: true)]
#[ColumnDesc('sc_management_fee_amount', required: true)]
#[ColumnDesc('sc_rent_amount', required: true)]
#[ColumnDesc('sc_payment_day', required: true, desc: '填入1表示星期1或每月1号')]
#[ColumnDesc('sc_total_rent_amount', required: true)]
#[ColumnDesc('sc_insurance_base_fee_amount', )]
#[ColumnDesc('sc_insurance_additional_fee_amount')]
#[ColumnDesc('sc_other_fee_amount')]
#[ColumnDesc('sc_total_amount', required: true)]
#[ColumnDesc('sc_status', required: true, enum_class: ScStatus::class)]
#[ColumnDesc('sc_order_at', type: ColumnType::DATETIME)]
#[ColumnDesc('sc_signed_at', type: ColumnType::DATETIME)]
#[ColumnDesc('sc_canceled_at', type: ColumnType::DATETIME)]
#[ColumnDesc('sc_completed_at', type: ColumnType::DATETIME)]
#[ColumnDesc('sc_early_termination_at', type: ColumnType::DATETIME)]
/**
 * @property int                                    $sc_id                                  租车序号
 * @property ScRentalType|ScRentalType_Short|string $sc_rental_type                         租车类型；长租或短租
 * @property string                                 $sc_rental_type_label                   租车类型-中文;长租或短租
 * @property string                                 $sc_rental_type_short_label             租车类型-短中文
 * @property null|ScPaymentPeriod|string            $sc_payment_period                      付款周期；例如月付预付、月付后付等
 * @property null|string                            $sc_payment_period_label                付款类型-中文
 * @property int                                    $sc_cu_id                               客户序号；指向客户表
 * @property int                                    $sc_ve_id                               车辆序号；指向车辆表
 * @property null|int                               $sc_ve_id_temp                          临时车辆序号
 * @property null|int                               $sc_group_no                            ;续租分组No
 * @property int                                    $sc_group_seq                           ;分组内顺序
 * @property string                                 $sc_no                                  合同编号
 * @property int                                    $sc_free_days                           免租天数
 * @property Carbon                                 $sc_start_date                          合同开始日期
 * @property Carbon                                 $sc_start_date__zh                      合同开始日期-中文
 * @property null|int                               $sc_installments                        分期数
 * @property int                                    $sc_rental_days                         租期天数;短租的属性
 * @property Carbon                                 $sc_end_date                            合同结束日期
 * @property Carbon                                 $sc_end_date__zh                        合同结束日期-中文
 * @property null|float                             $sc_deposit_amount                      一次性押金
 * @property null|string                            $sc_deposit_amount__zh                  一次性押金-中文大写
 * @property null|float                             $sc_management_fee_amount               一次性管理费用
 * @property null|string                            $sc_management_fee_amount__zh           一次性管理费用-中文大写
 * @property null|float                             $sc_rent_amount                         每期租金金额
 * @property null|string                            $sc_rent_amount__zh                     每期租金金额-中文大写
 * @property null|int                               $sc_payment_day                         付款日
 * @property null|string                            $sc_payment_day_label                   付款日-中文
 * @property null|float                             $sc_total_rent_amount                   总计租金金额
 * @property null|string                            $sc_total_rent_amount__zh               总计租金金额-中文大写
 * @property null|float                             $sc_insurance_base_fee_amount           基础保险费金额
 * @property null|string                            $sc_insurance_base_fee_amount__zh       基础保险费金额-中文大写
 * @property null|float                             $sc_insurance_additional_fee_amount     附加保险费总金额
 * @property null|string                            $sc_insurance_additional_fee_amount__zh 附加保险费总金额-中文大写
 * @property null|float                             $sc_other_fee_amount                    其他费总金额
 * @property null|string                            $sc_other_fee_amount__zh                其他费总金额-中文大写
 * @property null|string                            $sc_total_amount                        总计金额
 * @property null|string                            $sc_total_amount__zh                    总计金额-中文大写
 * @property mixed|ScStatus                         $sc_status                              合同状态；例如未签约、已签约、已完成等
 * @property null|string                            $sc_status_label                        合同状态-中文
 * @property null|Carbon                            $sc_order_at                            合同生成日时
 * @property null|Carbon                            $sc_signed_at                           签约日时
 * @property null|Carbon                            $sc_canceled_at                         取消日时
 * @property Carbon                                 $sc_completed_at                        结算日时
 * @property Carbon                                 $sc_early_termination_at                提前结算日时
 * @property callable                               $sc_payments_phpword_func               计划收款表-表格; 生成 docx 文件使用
 * @property null|array                             $sc_additional_photos                   附加照片
 * @property null|array                             $sc_additional_file                     附加文件
 * @property null|string                            $sc_cus_1                               自定义合同内容1
 * @property null|string                            $sc_cus_2                               自定义合同内容2
 * @property null|string                            $sc_cus_3                               自定义合同内容3
 * @property null|string                            $sc_discount_plan                       优惠方案
 * @property null|string                            $sc_remark                              租车合同备注
 *                                                                                          --
 * @property Customer                               $Customer
 * @property SaleSettlement                         $SaleSettlement
 * @property Vehicle                                $Vehicle
 * @property Vehicle                                $VehicleReplace
 * @property Collection<Payment>                    $Payments
 * @property SaleContractExt                        $SaleContractExt
 * @property Collection<Payment>                    $UnpaidRentPayments
 */
class SaleContract extends Model
{
    use ModelTrait;

    use ImportTrait;

    public const CREATED_AT = 'sc_created_at';
    public const UPDATED_AT = 'sc_updated_at';
    public const UPDATED_BY = 'sc_updated_by';

    protected $primaryKey = 'sc_id';

    protected $guarded = ['sc_id'];

    protected $casts = [
        'sc_start_date'           => 'date:Y-m-d',
        'sc_end_date'             => 'date:Y-m-d',
        'sc_order_at'             => 'datetime:Y-m-d H:i',
        'sc_signed_at'            => 'datetime:Y-m-d H:i',
        'sc_canceled_at'          => 'datetime:Y-m-d H:i',
        'sc_completed_at'         => 'datetime:Y-m-d H:i',
        'sc_early_termination_at' => 'datetime:Y-m-d H:i',
        'sc_rental_type'          => ScRentalType::class,
        'sc_payment_period'       => ScPaymentPeriod::class,
        'sc_status'               => ScStatus::class,
        //        'sc_group_no'             => 'integer',
        //        'sc_group_seq'            => 'integer',
    ];

    protected $attributes = [
    ];

    protected $appends = [
        'sc_rental_type_label',
        'sc_rental_type_short_label',
        'sc_payment_period_label',
        'sc_payment_day_label',
        'sc_status_label',
        'sc_full_label',
        'sc_start_date__zh',
        'sc_end_date__zh',
        'sc_rent_amount__zh',
        'sc_total_rent_amount__zh',
        'sc_total_amount__zh',
        'sc_deposit_amount__zh',
        'sc_management_fee_amount__zh',
        'sc_insurance_base_fee_amount__zh',
        'sc_insurance_additional_fee_amount__zh',
        'sc_other_fee_amount__zh',
    ];

    public function Customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'sc_cu_id', 'cu_id');
    }

    public function Vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'sc_ve_id', 've_id')->with('VehicleModel');
    }

    public function VehicleTemp(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'sc_ve_id_temp', 've_id')->with('VehicleModel');
    }

    public function Payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'p_sc_id', 'sc_id')->with('PaymentType');
    }

    public function SaleContractExt(): HasOne
    {
        return $this->hasOne(SaleContractExt::class, 'sce_sc_id', 'sc_id');
    }

    /**
     * 签约时需要支付的.
     */
    public function SignPayments(): HasMany
    {
        return $this->hasMany(Payment::class, 'p_sc_id', 'sc_id')
            ->whereIn('p_pt_id', [PPtId::DEPOSIT, PPtId::MANAGEMENT_FEE])
            ->with('PaymentType')
        ;
    }

    /**
     * 未支付的租金列表.
     */
    public function UnpaidRentPayments(): HasMany
    {
        return $this->hasMany(Payment::class, 'p_sc_id', 'sc_id')
            ->whereIn('p_pt_id', [PPtId::RENT])
            ->where('p_pay_status', '=', PPayStatus::UNPAID)
            ->with('PaymentType')
        ;
    }

    public function GroupContracts(): HasMany
    {
        return $this->hasMany(self::class, 'sc_group_no', 'sc_group_no')
            ->orderBy('sc_group_seq')
        ;
    }

    public function SaleSettlement(): HasOne
    {
        return $this->hasOne(SaleSettlement::class, 'ss_sc_id', 'sc_id');
    }

    public function check_status(array $sc_statuses, Validator $validator): bool
    {
        if ($sc_statuses && !in_array($this->sc_status, $sc_statuses)) {
            $validator->errors()->add('sc_id', '租车状态不应该为：'.$this->sc_status->label);

            return false;
        }

        return true;
    }

    public function latestGroupSeq(): int
    {
        return (int) (self::query()->where('sc_group_no', '=', $this->sc_group_no)->max('sc_group_seq') ?? 1);
    }

    public function isLatestInGroup(): bool
    {
        $latestGroupSeq = $this->latestGroupSeq();

        return $this->sc_group_seq >= $latestGroupSeq;
    }

    public static function indexQuery(): Builder
    {
        return static::query()
            ->from('sale_contracts', 'sc')
            ->leftJoin('vehicles as ve', 've.ve_id', '=', 'sc.sc_ve_id')
            ->leftJoin('vehicle_models as _vm', '_vm.vm_id', '=', 've.ve_vm_id')
            ->leftJoin('customers as cu', 'cu.cu_id', '=', 'sc.sc_cu_id')
            ->orderByDesc('sc.sc_id')
            ->select('sc.*', 'cu.*', 've.*', '_vm.vm_brand_name', '_vm.vm_model_name')
            ->addSelect(
                DB::raw(ScRentalType_Short::toCaseSQL()),
                DB::raw(ScPaymentPeriod::toCaseSQL()),
                DB::raw(ScStatus::toCaseSQL()),
                DB::raw(ScStatus::toColorSQL()),
                DB::raw("to_char(sc.sc_order_at, 'YYYY-MM-DD HH24:MI:SS') as sc_order_at_"),
                DB::raw("to_char(sc.sc_signed_at, 'YYYY-MM-DD HH24:MI:SS') as sc_signed_at_"),
                DB::raw("to_char(sc.sc_canceled_at, 'YYYY-MM-DD HH24:MI:SS') as sc_canceled_at_"),
                DB::raw("to_char(sc.sc_completed_at, 'YYYY-MM-DD HH24:MI:SS') as sc_completed_at_"),
                DB::raw("to_char(sc.sc_early_termination_at, 'YYYY-MM-DD HH24:MI:SS') as sc_early_termination_at_"),
            )
        ;
    }

    public static function indexColumns(): array
    {
        return [
            'SaleContract.sc_rental_type'                  => fn ($item) => $item->rental_type_label,
            'SaleContract.sc_payment_period'               => fn ($item) => $item->sc_payment_period_label,
            'Customer.cu_contact_name'                     => fn ($item) => $item->cu_contact_name,
            'Customer.cu_contact_phone'                    => fn ($item) => $item->contact_phone,
            'Vehicle.ve_plate_no'                          => fn ($item) => $item->ve_plate_no,
            'VehicleModel.brand_model'                     => fn ($item) => $item->vm_brand_name.'-'.$item->vm_model_name,
            'SaleContract.sc_no'                           => fn ($item) => $item->sc_no,
            'SaleContract.sc_start_date'                   => fn ($item) => $item->sc_start_date,
            'SaleContract.installments'                    => fn ($item) => $item->installments,
            'SaleContract.sc_end_date'                     => fn ($item) => $item->sc_end_date,
            'SaleContract.deposit_amount'                  => fn ($item) => $item->deposit_amount,
            'SaleContract.management_fee_amount'           => fn ($item) => $item->management_fee_amount,
            'SaleContract.rent_amount'                     => fn ($item) => $item->rent_amount,
            'SaleContract.payment_day'                     => fn ($item) => $item->payment_day,
            'SaleContract.total_rent_amount'               => fn ($item) => $item->total_rent_amount,
            'SaleContract.insurance_base_fee_amount'       => fn ($item) => $item->insurance_base_fee_amount,
            'SaleContract.insurance_additional_fee_amount' => fn ($item) => $item->insurance_additional_fee_amount,
            'SaleContract.other_fee_amount'                => fn ($item) => $item->other_fee_amount,
            'SaleContract.total_amount'                    => fn ($item) => $item->total_amount,
            'SaleContract.sc_status'                       => fn ($item) => $item->sc_status_label,
            'SaleContract.order_at'                        => fn ($item) => $item->order_at_,
            'SaleContract.signed_at'                       => fn ($item) => $item->signed_at_,
            'SaleContract.canceled_at'                     => fn ($item) => $item->canceled_at_,
            'SaleContract.completed_at'                    => fn ($item) => $item->completed_at_,
            'SaleContract.early_termination_at'            => fn ($item) => $item->early_termination_at_,
        ];
    }

    public static function CustomerHasVeId(): Builder
    {
        $cu_id = auth()->id();

        return static::query()
            ->from('sale_contracts', 'sc')
            ->where('sc.sc_cu_id', '=', $cu_id)
            ->whereIn('sc.sc_status', [ScStatus::SIGNED])
            ->select('sc.sc_ve_id')
        ;
    }

    public static function optionsQuery(): Builder
    {
        return static::query()
            ->from('sale_contracts', 'sc')
            ->leftJoin('vehicles as ve', 've.ve_id', '=', 'sc.sc_ve_id')
            ->leftJoin('customers as cu', 'cu.cu_id', '=', 'sc.sc_cu_id')
            ->orderBy('sc.sc_id', 'desc')
            ->select(
                DB::raw(sprintf(
                    "CONCAT(cu.cu_contact_name,'|',%s,'|', ve.ve_plate_no ,'|',  %s, %s ,'|', %s ) as text,sc.sc_id as value",
                    "(CONCAT(SUBSTRING(cu.cu_contact_phone, 1, 0), '', SUBSTRING(cu.cu_contact_phone, 8, 4)) )",
                    ScPaymentPeriod::toCaseSQL(false),
                    ScRentalType_ShortOnlyShort::toCaseSQL(false),
                    ScStatus::toCaseSQL(false)
                ))
            )
        ;
    }

    public static function optionsVeReplace(?\Closure $where = null): array
    {
        $key = static::getOptionKey();

        $query = static::query()
            ->from('sale_contracts', 'sc')
            ->leftJoin('vehicles as ve', 've.ve_id', '=', 'sc.sc_ve_id_temp')
            ->leftJoin('customers as cu', 'cu.cu_id', '=', 'sc.sc_cu_id')
            ->when($where, $where)
            ->orderBy('sc.sc_id', 'desc')
            ->select(
                DB::raw(sprintf(
                    "CONCAT(cu.cu_contact_name,'|',%s,'|', ve.ve_plate_no ,'|',  %s, %s ,'|', %s ) as text,sc.sc_id as value",
                    "(CONCAT(SUBSTRING(cu.cu_contact_phone, 1, 0), '', SUBSTRING(cu.cu_contact_phone, 8, 4)) )",
                    ScPaymentPeriod::toCaseSQL(false),
                    ScRentalType_ShortOnlyShort::toCaseSQL(false),
                    ScStatus::toCaseSQL(false)
                ))
            )
        ;

        $value = $query->get()->toArray();

        return [$key => $value];
    }

    public function getScStartDateZhAttribute(): ?string
    {
        $raw = $this->attributes['sc_start_date'] ?? null;
        if (!$raw) {
            return null;
        }

        return Carbon::parse($raw)
            ->locale('zh_CN')
            ->translatedFormat('Y年m月d日')
        ;
    }

    public function getScEndDateZhAttribute(): ?string
    {
        $raw = $this->attributes['sc_end_date'] ?? null;
        if (!$raw) {
            return null;
        }

        return Carbon::parse($raw)
            ->locale('zh_CN')
            ->translatedFormat('Y年m月d日')
        ;
    }

    public function getScRentAmountZhAttribute(): string
    {
        return money_format_zh($this->getAttribute('sc_rent_amount'));
    }

    public function getScTotalRentAmountZhAttribute()
    {
        // 简单相乘计算总租金
        return money_format_zh($this->getAttribute('sc_total_rent_amount'));
    }

    public function getScTotalAmountZhAttribute()
    {
        // 简单相乘计算总租金
        return money_format_zh($this->getAttribute('sc_total_amount'));
    }

    public function getScDepositAmountZhAttribute(): string
    {
        return money_format_zh($this->getAttribute('sc_deposit_amount'));
    }

    public function getScManagementFeeAmountZhAttribute(): string
    {
        return money_format_zh($this->getAttribute('sc_management_fee_amount') ?? '0');
    }

    public function getScInsuranceBaseFeeAmountZhAttribute(): string
    {
        return money_format_zh($this->getAttribute('sc_insurance_base_fee_amount') ?? '0');
    }

    public function getScInsuranceAdditionalFeeAmountZhAttribute(): string
    {
        return money_format_zh($this->getAttribute('sc_insurance_additional_fee_amount') ?? '0');
    }

    public function getScOtherFeeAmountZhAttribute(): string
    {
        return money_format_zh($this->getAttribute('sc_other_fee_amount') ?? '0');
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
            $table->addCell()->addText($payment->p_should_pay_date.'('.$payment->p_day_of_week_name.')');
            $table->addCell()->addText($payment->p_should_pay_amount);
            $table->addCell()->addText($payment->p_remark);
        }

        $tp->setComplexBlock($rule_label, $table);
    }

    public static function contractNumberKv(?string $sc_no = null)
    {
        static $kv = null;

        if (null === $kv) {
            $kv = static::query()
                ->from('sale_contracts')
                ->select('sc_id', 'sc_no')
                ->pluck('sc_id', 'sc_no')
                ->toArray()
            ;
        }

        return $kv[$sc_no] ?? null;
    }

    public function Company(): BelongsTo
    {
        return $this->belongsTo(Company::class)
            ->withDefault(
                Company::query()->firstOrNew()->toArray()
            )
        ;
    }

    public static function importColumns(): array
    {
        return [
            'sc_rental_type'                     => [SaleContract::class, 'sc_rental_type'],
            'sc_payment_period'                  => [SaleContract::class, 'sc_payment_period'],
            'cu_contact_name'                    => [Customer::class, 'cu_contact_name'],
            'cu_contact_phone'                   => [Customer::class, 'cu_contact_phone'],
            've_plate_no'                        => [Vehicle::class, 've_plate_no'],
            'sc_no'                              => [SaleContract::class, 'sc_no'],
            'sc_start_date'                      => [SaleContract::class, 'sc_start_date'],
            'sc_installments'                    => [SaleContract::class, 'sc_installments'],
            'sc_end_date'                        => [SaleContract::class, 'sc_end_date'],
            'sc_deposit_amount'                  => [SaleContract::class, 'sc_deposit_amount'],
            'sc_management_fee_amount'           => [SaleContract::class, 'sc_management_fee_amount'],
            'sc_rent_amount'                     => [SaleContract::class, 'sc_rent_amount'],
            'sc_payment_day'                     => [SaleContract::class, 'sc_payment_day'],
            'sc_total_rent_amount'               => [SaleContract::class, 'sc_total_rent_amount'],
            'sc_insurance_base_fee_amount'       => [SaleContract::class, 'sc_insurance_base_fee_amount'],
            'sc_insurance_additional_fee_amount' => [SaleContract::class, 'sc_insurance_additional_fee_amount'],
            'sc_other_fee_amount'                => [SaleContract::class, 'sc_other_fee_amount'],
            'sc_total_amount'                    => [SaleContract::class, 'sc_total_amount'],
            'sc_status'                          => [SaleContract::class, 'sc_status'],
            'sc_order_at'                        => [SaleContract::class, 'sc_order_at'],
            'sc_signed_at'                       => [SaleContract::class, 'sc_signed_at'],
            'sc_canceled_at'                     => [SaleContract::class, 'sc_canceled_at'],
            'sc_completed_at'                    => [SaleContract::class, 'sc_completed_at'],
            'sc_early_termination_at'            => [SaleContract::class, 'sc_early_termination_at'],
        ];
    }

    public static function importBeforeValidateDo(): \Closure
    {
        return function (&$item) {
            $item['sc_ve_id']          = Vehicle::plateNoKv($item['ve_plate_no'] ?? null);
            $item['sc_cu_id']          = Customer::contractPhoneKv($item['cu_contact_phone'] ?? null);
            $item['sc_rental_type']    = ScRentalType_Short::searchValue($item['sc_rental_type'] ?? null);
            $item['sc_payment_period'] = ScPaymentPeriod::searchValue($item['sc_payment_period'] ?? null);
            $item['sc_status']         = ScStatus::searchValue($item['sc_status'] ?? null);

            if (isset($item['sc_no'])) {
                static::$fields['sc_no'][] = $item['sc_no'];
            }
        };
    }

    public static function importValidatorRule(array $item, array $fieldAttributes): void
    {
        $validator1 = \Illuminate\Support\Facades\Validator::make(
            $item,
            [
                'sc_rental_type' => ['bail', 'required', Rule::in(ScRentalType::label_keys())],
            ],
            [],
            $fieldAttributes
        );
        if ($validator1->fails()) {
            throw new ValidationException($validator1);
        }

        $input1        = $validator1->validated();
        $rental_type   = $input1['sc_rental_type'];
        $is_long_term  = ScRentalType::LONG_TERM === $rental_type;
        $is_short_term = ScRentalType::SHORT_TERM === $rental_type;

        $validator2 = \Illuminate\Support\Facades\Validator::make(
            $item,
            [
                'sc_payment_period' => ['bail', Rule::requiredIf($is_long_term), Rule::excludeIf($is_short_term), 'string', Rule::in(ScPaymentPeriod::label_keys())],
            ],
            [],
            $fieldAttributes
        );

        if ($validator2->fails()) {
            throw new ValidationException($validator2);
        }

        $input2 = $validator2->validated();

        $sc_payment_period = $input2['sc_payment_period'] ?? null;

        $rules = [
            'sc_cu_id'        => ['bail', 'required', 'integer'],
            'sc_ve_id'        => ['bail', 'required', 'integer'],
            'sc_no'           => ['bail', 'required', 'string', 'max:50'],
            'sc_free_days'    => ['bail', 'nullable', 'int:4'],
            'sc_start_date'   => ['bail', 'required', 'date', 'before_or_equal:sc_end_date'],
            'sc_installments' => ['bail', Rule::requiredIf($is_long_term), Rule::excludeIf($is_short_term), 'integer', 'min:1'],
            'sc_end_date'     => ['bail', 'required', 'date', 'after_or_equal:sc_start_date'],

            'sc_deposit_amount'                  => ['bail', 'required', 'decimal:0,2', 'gte:0'],
            'sc_management_fee_amount'           => ['bail', 'nullable', 'decimal:0,2', 'gte:0'],
            'sc_rent_amount'                     => ['bail', 'required', 'numeric', 'min:0'],
            'sc_payment_day'                     => ['bail', Rule::requiredIf($is_long_term), Rule::excludeIf($is_short_term), 'integer', new PaymentDayCheck($sc_payment_period)],
            'sc_total_rent_amount'               => ['bail', Rule::requiredIf($is_short_term), Rule::excludeIf($is_long_term), 'numeric', 'min:0'],
            'sc_insurance_base_fee_amount'       => ['bail', Rule::requiredIf($is_short_term), Rule::excludeIf($is_long_term), 'numeric', 'min:0'],
            'sc_insurance_additional_fee_amount' => ['bail', Rule::requiredIf($is_short_term), Rule::excludeIf($is_long_term), 'numeric', 'min:0'],
            'sc_other_fee_amount'                => ['bail', Rule::requiredIf($is_short_term), Rule::excludeIf($is_long_term), 'numeric', 'min:0'],
            'sc_total_amount'                    => ['bail', Rule::requiredIf($is_short_term), Rule::excludeIf($is_long_term), 'numeric', 'min:0'],

            'sc_cus_1'         => ['bail', 'nullable', 'max:255'],
            'sc_cus_2'         => ['bail', 'nullable', 'max:255'],
            'sc_cus_3'         => ['bail', 'nullable', 'max:255'],
            'sc_discount_plan' => ['bail', 'nullable', 'max:255'],
            'sc_remark'        => ['bail', 'nullable', 'max:255'],
        ];

        $validator = \Illuminate\Support\Facades\Validator::make($item, $rules, [], $fieldAttributes);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    public static function importAfterValidatorDo(): \Closure
    {
        return function () {
            // sc_no
            $sc_no = SaleContract::query()->whereIn('sc_no', static::$fields['sc_no'])->pluck('sc_no')->toArray();
            if (count($sc_no) > 0) {
                throw new ClientException('以下合同编号已存在：'.join(',', $sc_no));
            }
        };
    }

    public static function importCreateDo(): \Closure
    {
        return function ($input) {
            $saleContract = SaleContract::query()->create($input);

            if (0) {
                $subRequest = Request::create(
                    '',      // 仅是占位 URI，不会真的去路由
                    'GET',
                    $input    // 你的业务参数
                );

                static $SaleContractController = null;

                if (null === $SaleContractController) {
                    $SaleContractController = App::make(SaleContractController::class);
                }

                $response = App::call(
                    [$SaleContractController, 'paymentsOption'],
                    ['request' => $subRequest]
                );

                $payments = $response->original['data'];

                foreach ($payments as $payment) {
                    $saleContract->Payments()->create($payment);
                }
            }
        };
    }

    protected function scPaymentPeriodLabel(): Attribute
    {
        return Attribute::make(
            get : fn () => $this->getAttribute('sc_payment_period')?->label
        );
    }

    protected function scPaymentDayLabel(): Attribute
    {
        return Attribute::make(
            get: function () {
                $sc_payment_period = $this->getAttribute('sc_payment_period');
                if (null === $sc_payment_period) {
                    return null;
                }
                $map = str_starts_with('week', $sc_payment_period) ? ScPaymentDay_Week::LABELS : ScPaymentDay_Month::LABELS;

                $payment_day = $this->getOriginal('sc_payment_day');
                if (null === $payment_day) {
                    return null;
                }

                return $map[$payment_day] ?? '';
            }
        );
    }

    protected function scStatusLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('sc_status')?->label ?? null
        );
    }

    protected function scRentalTypeLabel(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->getAttribute('sc_rental_type')?->label
        );
    }

    protected function scRentalTypeShortLabel(): Attribute
    {
        return Attribute::make(
            get : function ($value) {
                $rental_type = $this->getRawOriginal('sc_rental_type');
                if (is_string($rental_type)) {
                    $rental_type = ScRentalType_Short::tryFrom($rental_type);
                }

                return $rental_type?->label;
            }
        );
    }

    protected function scFullLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => join(' | ', array_filter([
                $this->Customer?->getOriginal('cu_contact_name'),
                substr($this->Customer?->getOriginal('cu_contact_phone'), -4),
                $this->Vehicle?->getOriginal('ve_plate_no'),
                $this->getOriginal('sc_rental_type_short_label'),
                $this->getOriginal('sc_payment_period_label'),
                $this->getOriginal('sc_status_label'),
            ]))
        );
    }

    protected function scAdditionalFile(): Attribute
    {
        return $this->uploadFile();
    }

    protected function scAdditionalPhotos(): Attribute
    {
        return $this->uploadFileArray();
    }
}
