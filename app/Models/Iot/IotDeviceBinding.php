<?php

namespace App\Models\Iot;

use App\Models\_\ModelTrait;
use App\Models\Admin\Admin;
use App\Models\Vehicle\Vehicle;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int         $db_id           绑定ID
 * @property int         $db_d_code       设备编号
 * @property int         $db_ve_id        车辆ID
 * @property Carbon      $db_start_at     绑定开始时间
 * @property null|Carbon $db_end_at       绑定结束时间
 * @property null|string $db_note         安装备注信息
 * @property int         $db_processed_by 操作人员
 */
class IotDeviceBinding extends Model
{
    use ModelTrait;

    public const string CREATED_AT = 'db_created_at';
    public const string UPDATED_AT = 'db_updated_at';
    public const string UPDATED_BY = 'db_updated_by';

    protected $primaryKey = 'db_id';

    protected $guarded = ['db_id'];

    public static function indexQuery(): Builder
    {
        return static::query()
            // 统一别名便于 join 与筛选。
            ->from('iot_device_bindings', 'db')
            ->leftJoin('vehicles as ve', 'db.db_ve_id', '=', 've.ve_id')
            ->leftJoin('vehicle_models as vm', 've.ve_vm_id', '=', 'vm.vm_id')
            ->with('GpsDevice')
        ;
    }

    public function IotDevice(): BelongsTo
    {
        // 绑定设备信息。
        return $this->belongsTo(IotDevice::class, 'db_d_id', 'd_id');
    }

    public function GpsDevice(): BelongsTo
    {
        // 绑定设备信息。
        return $this->belongsTo(GpsDevice::class, 'db_d_code', 'terminal_id');
    }

    public function Vehicle(): BelongsTo
    {
        // 绑定车辆信息。
        return $this->belongsTo(Vehicle::class, 'db_ve_id', 've_id');
    }

    public function ProcessedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'db_processed_by', 'id');
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
