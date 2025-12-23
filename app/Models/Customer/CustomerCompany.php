<?php

namespace App\Models\Customer;

use App\Attributes\ClassName;
use App\Models\_\ModelTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

#[ClassName('企业客户')]
/**
 * @property int         $cuc_id                      企业客户序号
 * @property int         $cuc_cu_id                   客户序号；外键关联到 customers.cu_id
 * @property null|string $cuc_unified_credit_code     公司统一信用代码
 * @property null|mixed  $cuc_business_license_photo  营业执照;存储文件路径或URL
 * @property null|string $cuc_registration_address    注册地址
 * @property null|string $cuc_office_address          办公地址
 * @property null|Carbon $cuc_establishment_date      成立日期
 * @property null|int    $cuc_number_of_employees     员工人数
 * @property null|string $cuc_industry                所属行业
 * @property null|float  $cuc_annual_revenue          年收入
 * @property null|string $cuc_legal_representative    法定代表人
 * @property null|string $cuc_contact_person_position 联系人职位
 * @property null|string $cuc_tax_registration_number 税务登记号码
 * @property null|string $cuc_business_scope          经营范围
 * @property Customer    $Customer
 */
class CustomerCompany extends Model
{
    use ModelTrait;

    public const CREATED_AT = 'cuc_created_at';
    public const UPDATED_AT = 'cuc_updated_at';
    public const UPDATED_BY = 'cuc_updated_by';

    protected $primaryKey = 'cuc_id';

    protected $guarded = ['cuc_id'];

    public function Customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'cuc_cu_id', 'cu_id');
    }

    public static function indexQuery(): Builder
    {
        return DB::query();
    }

    public static function options(?\Closure $where = null, ?string $key = null): array
    {
        return [];
    }
}
