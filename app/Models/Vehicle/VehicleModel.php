<?php

namespace App\Models\Vehicle;

use App\Attributes\ClassName;
use App\Enum\Vehicle\VeStatusService;
use App\Enum\VehicleModel\VmStatus;
use App\Models\_\ModelTrait;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

#[ClassName('车型')]
/**
 * @property int             $vm_id                车型序号
 * @property string          $vm_brand_name        品牌
 * @property string          $vm_model_name        车型
 * @property string          $vm_brand_model       品牌车型
 * @property string|VmStatus $vm_status            状态
 * @property null|array      $vm_additional_photos 附加照片；存储照片路径的 JSON 数组
 */
class VehicleModel extends Model
{
    use ModelTrait;

    public const CREATED_AT = 'vm_created_at';
    public const UPDATED_AT = 'vm_updated_at';
    public const UPDATED_BY = 'vm_updated_by';

    protected $primaryKey = 'vm_id';

    protected $guarded = ['vm_id'];

    protected $appends = [
        'vm_status_label',
    ];

    protected $attributes = [];

    public function Vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class, 've_vm_id', 'vm_id');
    }

    public static function options(?\Closure $where = null): array
    {
        $key   = preg_replace('/^.*\\\/', '', get_called_class()).'Options';
        $value = static::query()->toBase()
            ->select(DB::raw("(vm_brand_name || '-' || vm_model_name) as text,vm_id as value"))
            ->where('vm_status', '=', VmStatus::ENABLED)
            ->get()
        ;

        return [$key => $value];
    }

    public static function indexQuery(array $search = []): Builder
    {
        return DB::query()
            ->from('vehicle_models', 'vm')
            ->select(
                'vm.*',
                DB::raw(VmStatus::toCaseSQL()),
                DB::raw(VmStatus::toColorSQL()),
            )
            ->addSelect([
                'vm_vehicle_count_service'    => Vehicle::query()->selectRaw('count(*)')->whereColumn('vehicles.vm_id', 'vm.vm_id')->where('vehicles.ve_status_service', '=', VeStatusService::YES),
                'vm_vehicle_count_un_service' => Vehicle::query()->selectRaw('count(*)')->whereColumn('vehicles.vm_id', 'vm.vm_id')->where('vehicles.ve_status_service', '=', VeStatusService::NO),
            ])
        ;
    }

    public static function modelQuery(array $search = []): \Illuminate\Database\Eloquent\Builder
    {
        return static::query()
            ->withCount([
                'vehicles as vehicle_count_service'    => fn ($q) => $q->where('vm_status_service', VeStatusService::YES),
                'vehicles as vehicle_count_un_service' => fn ($q) => $q->where('vm_status_service', VeStatusService::NO),
            ])
        ;
    }

    public static function indexColumns(): array
    {
        return [
            'VehicleModel.vm_id'         => fn ($item) => $item->vm_id,
            'VehicleModel.vm_brand_name' => fn ($item) => $item->vm_brand_name,
            'VehicleModel.vm_model_name' => fn ($item) => $item->vm_model_name,
        ];
    }

    protected function casts(): array
    {
        return [
            'vm_status' => VmStatus::class,
        ];
    }

    protected function vmStatusLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('vm_status')?->label
        );
    }

    protected function vmAdditionalPhotos(): Attribute
    {
        return $this->uploadFileArray();
    }
}
