<?php

namespace App\Models\Iot;

use App\Attributes\ClassName;
use App\Models\_\ModelTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ClassName('设备分组映射')]
/**
 * @property int    $id
 * @property string $device_id
 * @property int    $group_id
 */
class IotDeviceGroupMapping extends Model
{
    use ModelTrait;

    public const CREATED_AT = null;
    public const UPDATED_AT = null;

    protected $connection = 'timescaledb';

    protected $table = 'device_group_mappings';

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

    public function Group(): BelongsTo
    {
        return $this->belongsTo(IotDeviceGroup::class, 'group_id', 'group_id');
    }

    public function MqttAccount(): BelongsTo
    {
        return $this->belongsTo(IotMqttAccount::class, 'device_id', 'user_name');
    }
}
