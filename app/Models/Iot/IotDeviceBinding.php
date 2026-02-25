<?php

namespace App\Models\Iot;

use App\Attributes\ClassName;
use App\Models\_\ModelTrait;
use App\Models\Admin\Admin;
use App\Models\Vehicle\Vehicle;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ClassName('设备绑定', '记录')]
/**
 * @property int         $db_id           绑定ID
 * @property int         $db_terminal_id  设备编号
 * @property int         $db_ve_id        车辆ID
 * @property Carbon      $db_start_at     绑定开始时间
 * @property null|Carbon $db_end_at       绑定结束时间
 * @property bool        $db_is_finished  绑定是否完结
 * @property null|string $db_note         安装备注信息
 * @property int         $db_processed_by 操作人员
 * @property IotDevice   $IotDevice
 */
class IotDeviceBinding extends Model
{
    use ModelTrait;

    public const string CREATED_AT = 'db_created_at';
    public const string UPDATED_AT = 'db_updated_at';
    public const string UPDATED_BY = 'db_updated_by';

    protected $primaryKey = 'db_id';

    protected $guarded = ['db_id'];

    protected $appends = [
        'db_is_finished',
    ];

    public static function indexQuery(): Builder
    {
        return static::query()
            // 统一别名便于 join 与筛选。
            ->from('iot_device_bindings', 'db')
            ->leftJoin('vehicles as ve', 'db.db_ve_id', '=', 've.ve_id')
            ->leftJoin('vehicle_models as vm', 've.ve_vm_id', '=', 'vm.vm_id')
            ->select('db.*')
            ->addSelect([
                've_plate_no'   => 've.ve_plate_no',
                'vm_brand_name' => 'vm.vm_brand_name',
                'vm_model_name' => 'vm.vm_model_name',
            ])
//            ->with('GpsDevice')
        ;
    }

    public function MqttAccount(): BelongsTo
    {
        // 绑定设备信息。
        return $this->belongsTo(IotMqttAccount::class, 'db_d_id', 'd_id');
    }

    public function IotDevice(): BelongsTo
    {
        // 绑定设备信息。
        return $this->belongsTo(IotDevice::class, 'db_terminal_id', 'terminal_id');
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

    protected function dbIsFinished(): Attribute
    {
        return Attribute::make(
            get: function () {
                $startAt = $this->getAttribute('db_start_at');
                $endAt   = $this->getAttribute('db_end_at');

                if ($endAt && now()->greaterThanOrEqualTo($endAt)) {
                    return true;
                }

                if ($startAt && now()->lessThan($startAt)) {
                    return true;
                }

                return false;
            }
        );
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
