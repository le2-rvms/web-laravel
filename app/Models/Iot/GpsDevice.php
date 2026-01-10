<?php

namespace App\Models\Iot;

use App\Attributes\ClassName;
use App\Models\_\ModelTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[ClassName('')]
/**
 * @property int         $id          设备ID
 * @property null|string $terminal_id 设备的MQTT客户端序号
 * @property string      $name        设备编号
 */
class GpsDevice extends Model
{
    use ModelTrait;

    // 设备信息存于独立 IoT 数据库。
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    protected $connection = 'pgsql-iot';

    protected $primaryKey = 'id';

    protected $guarded = ['id'];

    public static function indexQuery(): Builder
    {
        return static::query();
    }

    public static function optionsQuery(): Builder
    {
        return static::query();
    }
}
