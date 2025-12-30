<?php

namespace App\Models\Iot;

use App\Models\_\ModelTrait;
use App\Models\Vehicle\Vehicle;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int         $db_id        绑定ID
 * @property int         $d_id         设备ID
 * @property int         $ve_id        车辆ID
 * @property Carbon      $db_start_at  绑定开始时间
 * @property null|Carbon $db_end_at    绑定结束时间
 * @property null|string $db_note      安装备注信息
 * @property int         $processed_by 操作人员
 */
class IotDeviceBinding extends Model
{
    use ModelTrait;

    protected $primaryKey = 'db_id';

    protected $guarded = ['db_id'];

    public static function indexQuery(): Builder
    {
        return static::query()
            // 统一别名便于 join 与筛选。
            ->from('iot_device_bindings', 'db')
        ;
    }

    public function Device(): BelongsTo
    {
        // 绑定设备信息。
        return $this->belongsTo(IotDevice::class, 'd_id');
    }

    public function Vehicle(): BelongsTo
    {
        // 绑定车辆信息。
        return $this->belongsTo(Vehicle::class, 've_id', 've_id');
    }

    public static function optionsQuery(): Builder
    {
        return static::query();
    }

    protected function casts(): array
    {
        return [
            // 统一时间格式输出。
            'db_start_at' => 'datetime:Y-m-d H:i:s',
            'db_end_at'   => 'datetime:Y-m-d H:i:s',
        ];
    }
}
