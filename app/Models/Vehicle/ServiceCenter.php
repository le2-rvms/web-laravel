<?php

namespace App\Models\Vehicle;

use App\Attributes\ColumnDesc;
use App\Enum\Vehicle\ScScStatus;
use App\Models\_\ModelTrait;
use App\Models\Admin\Admin;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

#[ColumnDesc('sc_name', required: true)]
/**
 * @property int                  $sc_id               修理厂ID
 * @property string               $sc_name             修理厂名称
 * @property null|string          $sc_address          修理厂地址
 * @property null|string          $contact_name        联系人
 * @property null                 $contact_mobile
 * @property null|string          $contact_phone       联系电话
 * @property ScScStatus|string    $sc_status           状态
 * @property null|string          $sc_note             备注
 * @property null|array<int>      $permitted_admin_ids 用户权限
 *                                                     -
 * @property VehicleRepair[]      $VehicleRepairs
 * @property VehicleMaintenance[] $VehicleMaintenances
 * @property VehicleAccident[]    $VehicleAccidents
 *                                                     -
 * @property null|string          $sc_status_label     状态-中文
 */
class ServiceCenter extends Model
{
    use ModelTrait;

    protected $primaryKey = 'sc_id';

    protected $guarded = ['sc_id'];

    protected $appends = [
        'sc_status_label',
    ];

    protected $attributes = [
    ];

    protected $casts = [
        'status'              => ScScStatus::class,
        'permitted_admin_ids' => 'array',
    ];

    public function VehicleRepairs(): HasMany
    {
        return $this->hasMany(VehicleRepair::class, 'sc_id', 'sc_id');
    }

    public function VehicleMaintenances(): HasMany
    {
        return $this->hasMany(VehicleMaintenance::class, 'sc_id', 'sc_id');
    }

    public function VehicleAccidents(): HasMany
    {
        return $this->hasMany(VehicleAccident::class, 'sc_id', 'sc_id');
    }

    public static function options(?\Closure $where = null): array
    {
        $key = preg_replace('/^.*\\\/', '', get_called_class())
            .'Options';

        /** @var Admin $admin */
        $admin = Auth::user();

        $value = DB::query()
            ->from('service_centers', 'sc')
            ->where('sc.sc_status', ScScStatus::ENABLED)
            ->when(!$admin->hasRole(config('setting.super_role.name')), function (Builder $query) use ($admin) {
                $query->whereRaw('permitted_admin_ids @> ?', [json_encode([$admin->id])]);
            })
            ->select(DB::raw('sc_name as text,sc_id as value'))
            ->get()
        ;

        return [$key => $value];
    }

    public static function indexQuery(array $search = []): Builder
    {
        return DB::query()
            ->from('service_centers', 'sc')
            ->select('sc.*')
            ->addSelect(
                DB::raw(ScScStatus::toCaseSQL()),
                DB::raw(ScScStatus::toColorSQL()),
            )
        ;
    }

    public static function nameKv(?string $name = null)
    {
        static $kv = null;

        if (null === $kv) {
            $kv = DB::query()
                ->from('service_centers')
                ->select('sc_id', 'name')
                ->pluck('sc_id', 'name')
                ->toArray()
            ;
        }

        if ($name) {
            return $kv[$name] ?? null;
        }

        return $kv;
    }

    protected function scStatusLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('status')?->label
        );
    }
}
