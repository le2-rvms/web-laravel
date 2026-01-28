<?php

namespace App\Models\Iot;

use App\Attributes\ClassName;
use App\Models\_\ModelTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[ClassName('')]
class MqttClientSession extends Model
{
    use ModelTrait;

    // 设备信息存于独立 IoT 数据库。
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    protected $connection = 'timescaledb';

    protected $primaryKey = 'client_id';

    protected $guarded = ['client_id'];

    public static function indexQuery(): Builder
    {
        return static::query();
    }

    public static function optionsQuery(): Builder
    {
        return static::query();
    }
}
