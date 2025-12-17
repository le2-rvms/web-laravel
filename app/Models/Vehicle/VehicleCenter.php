<?php

namespace App\Models\Vehicle;

use App\Attributes\ColumnDesc;
use App\Enum\Vehicle\VcStatus;
use App\Models\_\ModelTrait;
use App\Models\Admin\Admin;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

#[ColumnDesc('vc_name', required: true)]
/**
 * @property int                  $vc_id               修理厂ID
 * @property string               $vc_name             修理厂名称
 * @property null|string          $vc_address          修理厂地址
 * @property null|string          $vc_contact_name     联系人
 * @property null                 $vc_contact_mobile
 * @property null|string          $vc_contact_phone    联系电话
 * @property string|VcStatus      $vc_status           状态
 * @property null|string          $vc_note             备注
 * @property null|array<int>      $vc_permitted        用户权限
 *                                                     -
 * @property VehicleRepair[]      $VehicleRepairs
 * @property VehicleMaintenance[] $VehicleMaintenances
 * @property VehicleAccident[]    $VehicleAccidents
 *                                                     -
 * @property null|string          $vc_status_label     状态-中文
 */
class VehicleCenter extends Model
{
    use ModelTrait;

    public const CREATED_AT = 'vc_created_at';
    public const UPDATED_AT = 'vc_updated_at';
    public const UPDATED_BY = 'vc_updated_by';

    protected $primaryKey = 'vc_id';

    protected $guarded = ['vc_id'];

    protected $appends = [
        'vc_status_label',
    ];

    protected $attributes = [
    ];

    protected $casts = [
        'vc_status'    => VcStatus::class,
        'vc_permitted' => 'array',
    ];

    public function VehicleRepairs(): HasMany
    {
        return $this->hasMany(VehicleRepair::class, 'vr_vc_id', 'vc_id');
    }

    public function VehicleMaintenances(): HasMany
    {
        return $this->hasMany(VehicleMaintenance::class, 'vm_vc_id', 'vc_id');
    }

    public function VehicleAccidents(): HasMany
    {
        return $this->hasMany(VehicleAccident::class, 'va_vc_id', 'vc_id');
    }

    public static function options(?\Closure $where = null): array
    {
        $key = preg_replace('/^.*\\\/', '', get_called_class())
            .'Options';

        /** @var Admin $admin */
        $admin = Auth::user();

        $value = DB::query()
            ->from('vehicle_centers', 'vc')
            ->where('vc.vc_status', VcStatus::ENABLED)
            ->when(!$admin->hasRole(config('setting.super_role.name')), function (Builder $query) use ($admin) {
                $query->whereRaw('vc_permitted @> ?', [json_encode([$admin->id])]);
            })
            ->select(DB::raw('vc_name as text,vc_id as value'))
            ->get()
        ;

        return [$key => $value];
    }

    public static function indexQuery(array $search = []): Builder
    {
        return DB::query()
            ->from('vehicle_centers', 'vc')
            ->select('vc.*')
            ->addSelect(
                DB::raw(VcStatus::toCaseSQL()),
                DB::raw(VcStatus::toColorSQL()),
            )
        ;
    }

    public static function nameKv(?string $name = null)
    {
        static $kv = null;

        if (null === $kv) {
            $kv = DB::query()
                ->from('vehicle_centers')
                ->select('vc_id', 'name')
                ->pluck('vc_id', 'name')
                ->toArray()
            ;
        }

        if ($name) {
            return $kv[$name] ?? null;
        }

        return $kv;
    }

    protected function vcStatusLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('vc_status')?->label
        );
    }
}
