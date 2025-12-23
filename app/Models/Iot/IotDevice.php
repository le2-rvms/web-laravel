<?php

namespace App\Models\Iot;

use App\Attributes\ClassName;
use App\Models\_\ModelTrait;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

#[ClassName('')]
/**
 * @property int         $d_id          设备ID
 * @property null|string $clientid      设备的MQTT客户端序号
 * @property string      $device_code   设备编号
 * @property null|string $password_hash 设备密码哈希值
 * @property null|string $certificate   设备证书
 * @property null|string $salt          用于生成密码哈希的盐
 * @property null|int    $is_superuser  是否为超级用户;1表示是，0表示否
 * @property null        $device_name   设备名称
 * @property null        $product_key   产品ID
 * @property string      $username      设备的MQTT用户名
 */
class IotDevice extends Model
{
    use ModelTrait;

    public const CREATED_AT = 'd_created_at';
    public const UPDATED_AT = 'd_updated_at';
    public const UPDATED_BY = 'd_updated_by';

    protected $connection = 'pgsql-iot';

    protected $primaryKey = 'd_id';

    protected $guarded = ['d_id'];

    public static function indexQuery(): Builder
    {
        return DB::query();
    }

    public static function options(?\Closure $where = null, ?string $key = null): array
    {
        return [];
    }

    protected function username(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes) {
                return $attributes['username'] ?? ($attributes['d_id'].'&'.$attributes['device_code']);
            },
        );
    }
}
