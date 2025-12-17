<?php

namespace App\Models\Iot;

use App\Models\_\ModelTrait;
use App\Models\Vehicle\Vehicle;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

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

    public static function indexQuery(array $search = []): Builder
    {
        return DB::query()
            ->from('iot_device_bindings', 'db')
        ;
    }

    public function Device(): BelongsTo
    {
        return $this->belongsTo(IotDevice::class, 'd_id');
    }

    public function Vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 've_id', 've_id');
    }

    public static function options(?\Closure $where = null): array
    {
        return [];
    }

    protected function casts(): array
    {
        return [
            'db_start_at' => 'datetime:Y-m-d H:i:s',
            'db_end_at'   => 'datetime:Y-m-d H:i:s',
        ];
    }
}
