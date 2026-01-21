<?php

namespace App\Models\Iot;

use App\Attributes\ClassName;
use App\Models\_\ModelTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

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

    // 设备信息存于独立 IoT 数据库。
    public const CREATED_AT = 'd_created_at';
    public const UPDATED_AT = 'd_updated_at';
    public const UPDATED_BY = 'd_updated_by';

    protected $connection = 'timescaledb';

    protected $primaryKey = 'd_id';

    protected $guarded = ['d_id'];

    public static function indexQuery(): Builder
    {
        return static::query();
    }

    public static function optionsQuery(): Builder
    {
        return static::query();
    }

    protected function username(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes) {
                // 优先使用存储的 username，缺省拼接 d_id 与设备编码。
                return $attributes['username'] ?? ($attributes['d_id'].'&'.$attributes['device_code']);
            },
        );
    }
}
