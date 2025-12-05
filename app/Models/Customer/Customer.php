<?php

namespace App\Models\Customer;

use App\Attributes\ClassName;
use App\Attributes\ColumnDesc;
use App\Attributes\ColumnType;
use App\Enum\Customer\CuCuType;
use App\Enum\Customer\CuiCuiGender;
use App\Exceptions\ClientException;
use App\Models\_\ImportTrait;
use App\Models\_\ModelTrait;
use App\Models\Admin\Admin;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\HasApiTokens;

#[ClassName('客户', '信息')]
#[ColumnDesc('cu_type', required: true, enum_class: CuCuType::class)]
#[ColumnDesc('contact_name', required: true, )]
#[ColumnDesc('contact_phone', required: true, unique: true, )]
#[ColumnDesc('contact_email', unique: true)]
#[ColumnDesc('contact_wechat', )]
#[ColumnDesc('contact_live_city', )]
#[ColumnDesc('contact_live_address', )]
#[ColumnDesc('cu_cert_no')]
#[ColumnDesc('cu_cert_valid_to', type: ColumnType::DATE)]
#[ColumnDesc('cu_remark', )]
/**
 * @property int                       $cu_id                客户序号
 * @property CuCuType|string           $cu_type              客户类型
 * @property string                    $contact_name         联系人姓名
 * @property string                    $contact_phone        联系电话
 * @property null|string               $contact_email        联系人邮箱
 * @property null|string               $contact_wechat       联系人微信号
 * @property null|string               $contact_live_city    现住城市
 * @property null|string               $contact_live_address 现住地址
 * @property null|int                  $sales_manager        负责销售
 * @property null|int                  $driver_manager       负责驾管
 * @property null|string               $cu_cert_no           人证号
 * @property null|array<string>        $cu_cert_photo        人证照片
 * @property null|Carbon               $cu_cert_valid_to     人证到期日期
 * @property null|array<array<string>> $cu_additional_photos 顾客附加照片
 * @property null|string               $cu_remark            顾客备注
 *                                                           -
 * @property null|CustomerIndividual   $CustomerIndividual
 * @property null|CustomerCompany      $CustomerCompany
 * @property null|Admin                $SalesManager
 * @property null|Admin                $DriverManager
 *                                                           -
 */
class Customer extends Authenticatable
{
    use ModelTrait;

    use HasApiTokens;

    use ImportTrait;

    protected $primaryKey = 'cu_id';

    protected $guarded = ['cu_id'];

    protected $casts = [
        'cu_type' => CuCuType::class,
    ];

    protected $appends = [
        'cu_full_label',
        'cu_type_label',
    ];

    public static function options(?\Closure $where = null): array
    {
        $key   = preg_replace('/^.*\\\/', '', get_called_class()).'Options';
        $value = static::query()->toBase()
            ->select(DB::raw("CONCAT(contact_name,' | ',contact_phone) as text,cu_id as value"))
            ->get()
        ;

        return [$key => $value];
    }

    public static function plateNoKv(?string $contact_phone = null)
    {
        static $kv = null;

        if (null === $kv) {
            $kv = DB::query()
                ->from('customers')
                ->select('cu_id', 'contact_phone')
                ->pluck('cu_id', 'contact_phone')
                ->toArray()
            ;
        }

        if ($contact_phone) {
            return $kv[$contact_phone] ?? null;
        }

        return $kv;
    }

    public function CustomerIndividual(): HasOne
    {
        return $this->hasOne(CustomerIndividual::class, 'cu_id', 'cu_id')->withDefault();
    }

    public function CustomerCompany(): HasOne
    {
        return $this->hasOne(CustomerCompany::class, 'cu_id', 'cu_id')->withDefault();
    }

    public static function indexQuery(array $search = []): Builder
    {
        return DB::query()
            ->from('customers', 'cu')
            ->leftJoin('customer_companies as cuc', function (JoinClause $join) {
                $join->on('cuc.cu_id', '=', 'cu.cu_id')
                    ->where('cu.cu_type', '=', CuCuType::COMPANY)
                ;
            })
            ->leftJoin('customer_individuals as cui', function (JoinClause $join) {
                $join->on('cui.cu_id', '=', 'cu.cu_id')
                    ->where('cu.cu_type', '=', CuCuType::INDIVIDUAL)
                ;
            })
            ->leftjoin('admins as admin_sm', 'cu.sales_manager', '=', 'admin_sm.id')
            ->leftjoin('admins as admin_dm', 'cu.driver_manager', '=', 'admin_dm.id')
            ->select('cuc.*', 'cui.*', 'cu.*') // cu.* 在最后，这样可以让空值在前
            ->addSelect(
                DB::raw(CuCuType::toCaseSQL()),
                DB::raw(CuiCuiGender::toCaseSQL()),
                'admin_sm.name as sales_manager_name',
                'admin_dm.name as driver_manager_name',
            )
        ;
    }

    public static function indexColumns(): array
    {
        return [
            'Customer.cu_id'                                    => fn ($item) => $item->cu_id,
            'Customer.cu_type'                                  => fn ($item) => $item->cu_type_label,
            'Customer.contact_name'                             => fn ($item) => $item->contact_name,
            'Customer.contact_phone'                            => fn ($item) => $item->contact_phone,
            'Customer.contact_email'                            => fn ($item) => $item->contact_email,
            'Customer.contact_wechat'                           => fn ($item) => $item->contact_wechat,
            'Customer.contact_live_city'                        => fn ($item) => $item->contact_live_city,
            'Customer.contact_live_address'                     => fn ($item) => $item->contact_live_address,
            'Customer.cu_remark'                                => fn ($item) => $item->cu_remark,
            'CustomerIndividual.cui_name'                       => fn ($item) => $item->cui_name,
            'CustomerIndividual.cui_gender'                     => fn ($item) => $item->cui_gender_label,
            'CustomerIndividual.cui_date_of_birth'              => fn ($item) => $item->cui_date_of_birth,
            'CustomerIndividual.cui_id_number'                  => fn ($item) => $item->cui_id_number,
            'CustomerIndividual.cui_id_address'                 => fn ($item) => $item->cui_id_address,
            'CustomerIndividual.cui_id_expiry_date'             => fn ($item) => $item->cui_id_expiry_date,
            'CustomerIndividual.cui_driver_license_number'      => fn ($item) => $item->cui_driver_license_number,
            'CustomerIndividual.cui_driver_license_category'    => fn ($item) => $item->cui_driver_license_category,
            'CustomerIndividual.cui_driver_license_expiry_date' => fn ($item) => $item->cui_driver_license_expiry_date,
            'CustomerIndividual.cui_emergency_contact_name'     => fn ($item) => $item->cui_emergency_contact_name,
            'CustomerIndividual.cui_emergency_contact_phone'    => fn ($item) => $item->cui_emergency_contact_phone,
            'CustomerIndividual.cui_emergency_relationship'     => fn ($item) => $item->cui_emergency_relationship,
            'CustomerCompany.cuc_unified_credit_code'           => fn ($item) => $item->cuc_unified_credit_code,
            'CustomerCompany.cuc_registration_address'          => fn ($item) => $item->cuc_registration_address,
            'CustomerCompany.cuc_office_address'                => fn ($item) => $item->cuc_office_address,
            'CustomerCompany.cuc_establishment_date'            => fn ($item) => $item->cuc_establishment_date,
            'CustomerCompany.cuc_number_of_employees'           => fn ($item) => $item->cuc_number_of_employees,
            'CustomerCompany.cuc_industry'                      => fn ($item) => $item->cuc_industry,
            'CustomerCompany.cuc_annual_revenue'                => fn ($item) => $item->cuc_annual_revenue,
            'CustomerCompany.cuc_legal_representative'          => fn ($item) => $item->cuc_legal_representative,
            'CustomerCompany.cuc_contact_person_position'       => fn ($item) => $item->cuc_contact_person_position,
            'CustomerCompany.cuc_tax_registration_number'       => fn ($item) => $item->cuc_tax_registration_number,
            'CustomerCompany.cuc_business_scope'                => fn ($item) => $item->cuc_business_scope,
        ];
    }

    public function SalesManager(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'sales_manager', 'cu_id');
    }

    public function DriverManager(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'driver_manager', 'cu_id');
    }

    public static function importColumns(): array
    {
        return [
            'cu_type'                        => [Customer::class, 'cu_type'],
            'contact_name'                   => [Customer::class, 'contact_name'],
            'contact_phone'                  => [Customer::class, 'contact_phone'],
            'contact_email'                  => [Customer::class, 'contact_email'],
            'contact_wechat'                 => [Customer::class, 'contact_wechat'],
            'contact_live_city'              => [Customer::class, 'contact_live_city'],
            'contact_live_address'           => [Customer::class, 'contact_live_address'],
            'cu_cert_no'                     => [Customer::class, 'cu_cert_no'],
            'cu_cert_valid_to'               => [Customer::class, 'cu_cert_valid_to'],
            'cu_remark'                      => [Customer::class, 'cu_remark'],
            'cui_name'                       => [CustomerIndividual::class, 'cui_name'],
            'cui_gender'                     => [CustomerIndividual::class, 'cui_gender'],
            'cui_date_of_birth'              => [CustomerIndividual::class, 'cui_date_of_birth'],
            'cui_id_number'                  => [CustomerIndividual::class, 'cui_id_number'],
            'cui_id_address'                 => [CustomerIndividual::class, 'cui_id_address'],
            'cui_id_expiry_date'             => [CustomerIndividual::class, 'cui_id_expiry_date'],
            'cui_driver_license_number'      => [CustomerIndividual::class, 'cui_driver_license_number'],
            'cui_driver_license_category'    => [CustomerIndividual::class, 'cui_driver_license_category'],
            'cui_driver_license_expiry_date' => [CustomerIndividual::class, 'cui_driver_license_expiry_date'],
            'cui_emergency_relationship'     => [CustomerIndividual::class, 'cui_emergency_relationship'],
            'cui_emergency_contact_name'     => [CustomerIndividual::class, 'cui_emergency_contact_name'],
            'cui_emergency_id_number'        => [CustomerIndividual::class, 'cui_emergency_id_number'],
            'cui_emergency_contact_phone'    => [CustomerIndividual::class, 'cui_emergency_contact_phone'],
        ];
    }

    public static function importBeforeValidateDo(): \Closure
    {
        return function (&$item) {
            $item['cu_type']                   = CuCuType::searchValue($item['cu_type']);
            $item['cui_gender']                = CuiCuiGender::searchValue($item['cui_gender'] ?? null);
            static::$fields['contact_phone'][] = $item['contact_phone'] ?? null;
            static::$fields['contact_email'][] = $item['contact_email'] ?? null;
        };
    }

    public static function importValidatorRule(array $item, array $fieldAttributes): void
    {
        $rules = [
            // customer
            'cu_type'              => ['required', 'string', Rule::in(CuCuType::label_keys())],
            'contact_name'         => ['required', 'string', 'max:255'],
            'contact_phone'        => ['required', 'regex:/^\d{11}$/'],
            'contact_email'        => ['nullable', 'email'],
            'contact_wechat'       => ['nullable', 'string', 'max:255'],
            'contact_live_city'    => ['nullable', 'string', 'max:64'],
            'contact_live_address' => ['nullable', 'string', 'max:255'],
            'cu_cert_no'           => ['nullable', 'string', 'max:50'],
            'cu_cert_valid_to'     => ['nullable', 'date'],
            'cu_remark'            => ['nullable', 'string', 'max:255'],
            // customer_individuals
            'cui_name'                       => ['nullable', 'string', 'max:255'],
            'cui_gender'                     => ['nullable', Rule::in(CuiCuiGender::label_keys())],
            'cui_date_of_birth'              => ['nullable', 'date', 'before:today'],
            'cui_id_number'                  => ['nullable', 'regex:/^\d{17}[\dXx]$/'],
            'cui_id_address'                 => ['nullable', 'string', 'max:500'],
            'cui_id_expiry_date'             => ['nullable', 'date', 'after:date_of_birth'],
            'cui_driver_license_number'      => ['nullable', 'string', 'max:50'],
            'cui_driver_license_category'    => ['nullable', 'string', 'regex:/^[A-Z]\d+$/'],
            'cui_driver_license_expiry_date' => ['nullable', 'date'],
            'cui_emergency_contact_name'     => ['nullable', 'string', 'max:64'],
            'cui_emergency_contact_phone'    => ['nullable', 'regex:/^\d{7,15}$/'],
            'cui_emergency_relationship'     => ['nullable', 'string', 'max:64'],
        ];

        $validator = Validator::make($item, $rules, [], $fieldAttributes);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    public static function importAfterValidatorDo(): \Closure
    {
        return function () {
            // contact_phone
            $contact_phone = Customer::query()->whereIn('contact_phone', static::$fields['contact_phone'])->pluck('contact_phone')->toArray();
            if (count($contact_phone) > 0) {
                throw new ClientException('以下联系电话已经存在：'.join(',', $contact_phone));
            }

            // contact_email
            $contact_email = Customer::query()->whereIn('contact_email', static::$fields['contact_email'])->pluck('contact_email')->toArray();
            if (count($contact_email) > 0) {
                throw new ClientException('以下联系邮箱已经存在：'.join(',', $contact_email));
            }
        };
    }

    public static function importCreateDo(): \Closure
    {
        return function ($input) {
            $customer = Customer::query()->create($input);

            switch ($customer->cu_type) {
                case CuCuType::INDIVIDUAL:
                    $customer->CustomerIndividual()->updateOrCreate(
                        [
                            'cu_id' => $customer->cu_id,
                        ],
                        $input,
                    );

                    break;

                case CuCuType::COMPANY:
                    $customer->CustomerCompany()->updateOrCreate(
                        [
                            'cu_id' => $customer->cu_id,
                        ],
                        $input,
                    );

                    break;

                default:
                    break;
            }
        };
    }

    protected function cuTypeLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('cu_type')?->label
        );
    }

    protected function cuFullLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => join(' | ', [
                $this->getOriginal('contact_name'),
                $this->getOriginal('contact_phone'),
            ])
        );
    }

    protected function cuCertPhoto(): Attribute
    {
        return $this->uploadFile();
    }

    protected function cuAdditionalPhotos(): Attribute
    {
        return $this->uploadFileArray();
    }
}
