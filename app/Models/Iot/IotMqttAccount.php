<?php

namespace App\Models\Iot;

use App\Attributes\ClassName;
use App\Models\_\ModelTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[ClassName('')]
/**
 * @property int         $act_id        账号ID
 * @property null|string $clientid      设备序号
 * @property string      $user_name     账号名称
 * @property null|string $password_hash 账号密码哈希值
 * @property null|string $certificate   账号证书
 * @property null|string $salt          用于生成密码哈希的盐
 * @property null|int    $is_superuser  是否为超级用户;1表示是，0表示否
 * @property null        $device_name   设备名称;没有使用
 * @property null        $product_key   产品ID
 */
class IotMqttAccount extends Model
{
    use ModelTrait;

    // 设备信息存于独立 IoT 数据库。
    public const CREATED_AT = 'act_created_at';
    public const UPDATED_AT = 'act_updated_at';
    public const UPDATED_BY = 'act_updated_by';

    protected $connection = 'timescaledb';

    protected $table = 'mqtt_accounts';

    protected $primaryKey = 'act_id';

    protected $guarded = ['act_id'];

    public static function indexQuery(): Builder
    {
        return static::query();
    }

    public static function optionsQuery(): Builder
    {
        return static::query();
    }
}
