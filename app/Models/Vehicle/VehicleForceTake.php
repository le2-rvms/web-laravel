<?php

namespace App\Models\Vehicle;

use App\Attributes\ClassName;
use App\Enum\Vehicle\VftStatus;
use App\Models\_\ModelTrait;
use App\Models\Customer\Customer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

#[ClassName('强制收车')]
/**
 * @property int                   $vft_id                序号
 * @property int                   $vft_ve_id             车牌号
 * @property int                   $vft_cu_id             客户姓名
 * @property Carbon                $vft_time              强制收车日期
 * @property null|string|VftStatus $vft_status            收车状态
 * @property null|mixed            $vft_additional_photos 附加照片
 * @property null|string           $vft_reason            原因
 */
class VehicleForceTake extends Model
{
    use ModelTrait;

    public const CREATED_AT = 'vft_created_at';
    public const UPDATED_AT = 'vft_updated_at';
    public const UPDATED_BY = 'vft_updated_by';

    protected $primaryKey = 'vft_id';

    protected $guarded = ['vft_id'];

    protected $attributes = [];

    protected $casts = [
        'vft_time'   => 'datetime:Y-m-d',
        'vft_status' => VftStatus::class,
    ];

    protected $appends = [
        'vft_status_label',
    ];

    public function Vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vft_ve_id', 've_id')
            ->with('VehicleModel')
        ;
    }

    public function Customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'vft_cu_id', 'cu_id');
    }

    public static function indexQuery(): Builder
    {
        return static::query()
            ->from('vehicle_force_takes', 'vft')
            ->leftJoin('vehicles as ve', 've.ve_id', '=', 'vft.vft_ve_id')
            ->leftJoin('vehicle_models as vm', 'vm.vm_id', '=', 've.ve_vm_id')
            ->leftJoin('customers as cu', 'cu.cu_id', '=', 'vft.vft_cu_id')
            ->select('vft.*', 'cu.cu_contact_name', 've.ve_plate_no', 'vm.vm_brand_name', 'vm.vm_model_name')
            ->addSelect(
                DB::raw(VftStatus::toCaseSQL()),
            )
        ;
    }

    public static function optionsQuery(): Builder
    {
        return static::query();
    }

    protected function vftStatusLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('vft_status')?->label
        );
    }

    protected function vftAdditionalPhotos(): Attribute
    {
        return $this->uploadFileArray();
    }
}
