<?php

namespace App\Models\Customer;

use App\Attributes\ClassName;
use App\Attributes\ColumnDesc;
use App\Enum\Customer\CuiGender;
use App\Models\_\ModelTrait;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

#[ClassName('个人客户')]
#[ColumnDesc('cui_name')]
#[ColumnDesc('cui_gender', enum_class: CuiGender::class)]
#[ColumnDesc('cui_date_of_birth')]
#[ColumnDesc('cui_id_number')]
#[ColumnDesc('cui_id_address')]
#[ColumnDesc('cui_id_expiry_date')]
#[ColumnDesc('cui_driver_license_number')]
#[ColumnDesc('cui_driver_license_category')]
#[ColumnDesc('cui_driver_license_expiry_date')]
#[ColumnDesc('cui_emergency_relationship')]
#[ColumnDesc('cui_emergency_contact_name')]
#[ColumnDesc('cui_emergency_id_number')]
#[ColumnDesc('cui_emergency_contact_phone')]
/**
 * @property int            $cui_id                         个人客户序号
 * @property int            $cui_cu_id                      客户序号；外键customers.cu_id
 * @property null|string    $cui_name                       姓名
 * @property null|CuiGender $cui_gender                     性别
 * @property null|Carbon    $cui_date_of_birth              出生日期
 * @property null|mixed     $cui_id1_photo                  身份证人脸照片
 * @property null|mixed     $cui_id2_photo                  身份证国徽照片
 * @property null|string    $cui_id_number                  身份证号码
 * @property null|string    $cui_id_address                 身份证地址
 * @property null|Carbon    $cui_id_expiry_date             身份证有效期
 * @property null|mixed     $cui_driver_license1_photo      驾驶证照片
 * @property null|mixed     $cui_driver_license2_photo      驾驶证副本照片
 * @property null|string    $cui_driver_license_number      驾驶证号码
 * @property null|string    $cui_driver_license_category    驾驶证类别
 * @property null|Carbon    $cui_driver_license_expiry_date 驾驶证有效期
 * @property null|string    $cui_emergency_relationship     紧急联系人关系
 * @property null|string    $cui_emergency_contact_name     紧急联系人姓名
 * @property null|string    $cui_emergency_id_number        紧急联系人身份证号
 * @property null|string    $cui_emergency_contact_phone    紧急联系人电话
 * @property Customer       $Customer
 * @property null|string    $cui_gender_label               性别-中文
 */
class CustomerIndividual extends Model
{
    use ModelTrait;

    public const CREATED_AT = 'cui_created_at';
    public const UPDATED_AT = 'cui_updated_at';
    public const UPDATED_BY = 'cui_updated_by';

    protected $primaryKey = 'cui_id';

    protected $guarded = ['cui_id'];

    protected $appends = [
        'cui_gender_label',
    ];

    protected $casts = [
        'cui_gender' => CuiGender::class,
    ];

    public function Customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'cui_cu_id', 'cu_id');
    }

    public static function indexQuery(): Builder
    {
        return DB::query();
    }

    public static function options(?\Closure $where = null, ?string $key = null): array
    {
        return [];
    }

    protected function cuiGenderLabel(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->getAttribute('cui_gender')?->label ?? '',
        );
    }

    protected function cuiId1Photo(): Attribute
    {
        return $this->uploadFile();
    }

    protected function cuiId2Photo(): Attribute
    {
        return $this->uploadFile();
    }

    protected function cuiDriverLicense1Photo(): Attribute
    {
        return $this->uploadFile();
    }

    protected function cuiDriverLicense2Photo(): Attribute
    {
        return $this->uploadFile();
    }
}
