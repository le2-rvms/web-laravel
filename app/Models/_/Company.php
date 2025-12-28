<?php

namespace App\Models\_;

use App\Attributes\ClassName;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

#[ClassName('公司')]
/**
 * @property null|bool   $only_one
 * @property null|int    $cp_id                     公司ID
 * @property null|string $cp_name                   公司名称
 * @property null|string $cp_address                公司注册地址
 * @property null|float  $cp_longitude              公司地址经度
 * @property null|float  $cp_latitude               公司地址纬度
 * @property null|string $cp_phone                  公司车辆预订联系电话
 * @property null|string $cp_description            公司介绍
 * @property null|string $cp_rental_note            租车须知
 * @property null|string $cp_purchase_note          购车须知
 * @property null|string $cp_invoice_note           开票信息
 * @property null|string $cp_bank_name              公司开户银行
 * @property null|string $cp_bank_account_no        公司银行账号
 * @property null|string $cp_social_credit_code     公司统一社会信用代码
 * @property null|array  $cp_company_photo          公司照片
 * @property null|array  $cp_business_license_photo 公司营业执照
 * @property null|string $cp_wechat_notify_mobile   企业收款通知手机号
 * @property null|int    $cp_verify_status          公司上架认证状态
 */
class Company extends Model
{
    use ModelTrait;

    // 公司信息使用单表单例记录。
    public const CREATED_AT = 'cp_created_at';

    public const UPDATED_AT = 'cp_updated_at';
    public const UPDATED_BY = 'cp_updated_by';

    protected $primaryKey = 'cp_id';

    protected $guarded = ['cp_id'];

    public static function indexQuery(): Builder
    {
        return DB::query();
    }

    public static function options(?\Closure $where = null, ?string $key = null): array
    {
        return [];
    }

    protected function cpCompanyPhoto(): Attribute
    {
        // 统一走上传文件访问器。
        return $this->uploadFile();
    }

    protected function cpBusinessLicensePhoto(): Attribute
    {
        return $this->uploadFile();
    }
}
