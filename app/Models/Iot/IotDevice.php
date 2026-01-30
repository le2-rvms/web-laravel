<?php

namespace App\Models\Iot;

use App\Attributes\ClassName;
use App\Models\_\ModelTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ClassName('')]
/**
 * @property int         $id          设备ID
 * @property null|string $terminal_id 设备的MQTT客户端序号
 * @property string      $name        设备编号
 */
class IotDevice extends Model
{
    use ModelTrait;

    // 设备信息存于独立 IoT 数据库。
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    protected $connection = 'timescaledb';

    protected $table = 'devices';

    protected $primaryKey = 'dev_id';

    protected $guarded = ['dev_id', 'company_code'];

    public static function indexQuery(): Builder
    {
        return static::query()->from('devices', 'dev')->with('IotDeviceProduct');
    }

    public static function optionsQuery(): Builder
    {
        return static::query();
    }

    public function IotDeviceProduct(): BelongsTo
    {
        return $this->belongsTo(IotDeviceProduct::class, 'product_key', 'product_key');
    }

    protected static function booted()
    {
        static::saving(function ($model) {
            $model->company_code = config('app.company_code');
        });
    }
}
