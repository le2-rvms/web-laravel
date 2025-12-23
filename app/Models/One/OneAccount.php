<?php

namespace App\Models\One;

use App\Attributes\ClassName;
use App\Enum\One\OaIsSyncRentalContract;
use App\Enum\One\OaProvince;
use App\Enum\One\OaType;
use App\Models\_\ModelTrait;
use App\Models\Vehicle\Vehicle;
use GuzzleHttp\Cookie\FileCookieJar;
use GuzzleHttp\Cookie\SetCookie;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

#[ClassName('122账号信息')]
/**
 * @property int                         $oa_id                      122账号序号
 * @property OaType|string               $oa_type                    账号类型；个人、公司
 * @property string                      $oa_name                    账号名称
 * @property null|string                 $oa_province
 * @property null|string                 $oa_cookie_string           cookie信息
 * @property null|Carbon                 $oa_cookie_refresh_at       cookie更新时间
 * @property bool|OaIsSyncRentalContract $oa_is_sync_rental_contract 是否同步租赁合同
 *                                                                   -
 * @property null|Collection|Vehicle[]   $Vehicles
 */
class OneAccount extends Model
{
    use ModelTrait;

    public const CREATED_AT = 'oa_created_at';
    public const UPDATED_AT = 'oa_updated_at';
    public const UPDATED_BY = 'oa_updated_by';

    protected $primaryKey = 'oa_id';

    protected $guarded = ['oa_id'];

    protected $appends = [
        'oa_type_label',
        'oa_province_value',
    ];

    protected $casts = [
        'oa_cookie_refresh_at'       => 'datetime:Y-m-d H:i:s',
        'oa_type'                    => OaType::class,
        'oa_is_sync_rental_contract' => OaIsSyncRentalContract::class,
    ];

    private static ?Filesystem $disk = null;

    public function initializeCookies(): FileCookieJar
    {
        if (null === static::$disk) {
            static::$disk = Storage::disk('local');
        }

        $cookieFilePath = static::$disk->path($this->getCookiePath());

        $directory = 'cookie';

        if (!static::$disk->exists($directory)) {
            static::$disk->makeDirectory($directory);
        }

        if (file_exists($cookieFilePath)) {
            return new FileCookieJar($cookieFilePath, true);
        }

        $cookieJar = new FileCookieJar($cookieFilePath, true);

        $url = $this->oa_province_value['url'];

        $domain = preg_replace('/^https?:\/\//', '', $url);

        $domainMap = [
            'JSESSIONID-L'    => $domain,
            'tmri_csfr_token' => $domain,
            '_uab_collina'    => $domain,
            'user'            => $domain,
            'accessToken'     => $domain,
            'userpub'         => $domain,
        ];

        foreach (explode(';', $this->oa_cookie_string) as $cookie) {
            [$name, $value] = array_map('trim', explode('=', $cookie, 2) + [null, null]);
            if ($name && $value) {
                $cookieJar->setCookie(new SetCookie([
                    'Name'   => $name,
                    'Value'  => $value,
                    'Domain' => $domainMap[$name] ?? '.122.gov.cn',
                    'Path'   => '/',
                ]));
            }
        }

        return $cookieJar;
    }

    public function deleteCookies(): void
    {
        if (null === static::$disk) {
            static::$disk = Storage::disk('local');
        }

        static::$disk->delete($this->getCookiePath());
    }

    public function getCookiePath()
    {
        return "cookie/{$this->oa_id}.json";
    }

    public static function indexQuery(): Builder
    {
        return DB::query()
            ->from('one_accounts', 'oa')
            ->select('oa.*')
            ->addSelect(
                DB::raw(OaType::toCaseSQL()),
                DB::raw("to_char(oa_cookie_refresh_at, 'YYYY-MM-DD HH24:MI:SS') as oa_cookie_refresh_at_"),
            )
        ;
    }

    public static function options(?\Closure $where = null, ?string $key = null): array
    {
        $key = static::getOptionKey($key);

        $value = static::query()
            ->when($where, fn ($query) => $query->where($where))
            ->orderByDesc('oa_id')
            ->get()
            ->map(function (self $account) {
                return [
                    'text'                  => $account->oa_name,
                    'value'                 => $account->oa_id,
                    'oa_type_label'         => $account->oa_type_label,
                    'oa_province'           => $account->oa_province,
                    'oa_province_label'     => $account->oa_province_value['name'] ?? null,
                    'oa_cookie_refresh_at_' => $account->oa_cookie_refresh_at,
                ];
            })
        ;

        return [$key => $value];
    }

    public function oaVehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class, 've_oa_id', 'oa_id');
    }

    protected function oaTypeLabel(): Attribute
    {
        return Attribute::make(
            get : fn () => $this->getAttribute('oa_type')?->label
        );
    }

    protected function oaProvinceValue(): Attribute
    {
        return Attribute::make(
            get : fn () => OaProvince::columnValues($this->getAttribute('oa_province')),
        );
    }
}
