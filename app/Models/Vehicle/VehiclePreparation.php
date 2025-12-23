<?php

namespace App\Models\Vehicle;

use App\Attributes\ClassName;
use App\Enum\Vehicle\VeStatusDispatch;
use App\Enum\Vehicle\VeStatusRental;
use App\Enum\Vehicle\VeStatusService;
use App\Enum\VehiclePreparation\VpAnnualCheckIs;
use App\Enum\VehiclePreparation\VpDocumentCheckIs;
use App\Enum\VehiclePreparation\VpInsuredCheckIs;
use App\Enum\VehiclePreparation\VpVehicleCheckIs;
use App\Enum\YesNo;
use App\Models\_\ModelTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

#[ClassName('车辆整备')]
/**
 * @property int         $vp_id                整备序号
 * @property int         $vp_ve_id             车辆序号，指向车辆表
 * @property int         $vp_annual_check_is   年审是否完备;1表示是，0表示否
 * @property null|Carbon $vp_annual_check_dt
 * @property int         $vp_insured_check_is  保险是否完备；1表示有，0表示无
 * @property null|Carbon $vp_insured_check_dt
 * @property int         $vp_vehicle_check_is  车况是否完备；1表示是，0表示否
 * @property null|Carbon $vp_vehicle_check_dt
 * @property int         $vp_document_check_is 证件是否完备；1表示是，0表示否
 * @property null|Carbon $vp_document_check_dt
 * @property Vehicle     $Vehicle
 */
class VehiclePreparation extends Model
{
    use ModelTrait;

    public const CREATED_AT = 'vp_created_at';
    public const UPDATED_AT = 'vp_updated_at';
    public const UPDATED_BY = 'vp_updated_by';

    protected $primaryKey = 'vp_id';

    protected $guarded = ['vp_id'];

    protected $casts = [
        'vp_annual_check_dt'   => 'datetime:Y-m-d H:i:s',
        'vp_document_check_dt' => 'datetime:Y-m-d H:i:s',
        'vp_insured_check_dt'  => 'datetime:Y-m-d H:i:s',
        'vp_vehicle_check_dt'  => 'datetime:Y-m-d H:i:s',
        'vp_annual_check_is'   => VpAnnualCheckIs::class,
        'vp_insured_check_is'  => VpInsuredCheckIs::class,
        'vp_vehicle_check_is'  => VpVehicleCheckIs::class,
        'vp_document_check_is' => VpDocumentCheckIs::class,
    ];

    public function Vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vp_ve_id', 've_id');
    }

    public static function indexQuery(): Builder
    {
        return DB::query()
            ->from('vehicle_preparations', 'vp')
            ->leftJoin('vehicles as ve', 've.ve_id', '=', 'vp.vp_ve_id')
            ->leftJoin('vehicle_models as vm', 've.ve_vm_id', '=', 'vm.vm_id')
            ->select('vp.*', 've.*', 'vm.vm_brand_name', 'vm.vm_model_name')
            ->addSelect(
                DB::raw(YesNo::toCaseSQL(true, 'vp.vp_annual_check_is')),
                DB::raw(YesNo::toCaseSQL(true, 'vp.vp_insured_check_is')),
                DB::raw(YesNo::toCaseSQL(true, 'vp.vp_vehicle_check_is')),
                DB::raw(YesNo::toCaseSQL(true, 'vp.vp_document_check_is')),
                DB::raw(VeStatusService::toCaseSQL()),
                DB::raw(VeStatusRental::toCaseSQL()),
                DB::raw(VeStatusDispatch::toCaseSQL()),
                DB::raw("to_char(vp_annual_check_dt, 'YYYY-MM-DD HH24:MI') as vp_annual_check_dt_"),
                DB::raw("to_char(vp_document_check_dt, 'YYYY-MM-DD HH24:MI') as vp_document_check_dt_"),
                DB::raw("to_char(vp_insured_check_dt, 'YYYY-MM-DD HH24:MI') as vp_insured_check_dt_"),
                DB::raw("to_char(vp_vehicle_check_dt, 'YYYY-MM-DD HH24:MI') as vp_vehicle_check_dt_"),
            )
        ;
    }

    public static function options(?\Closure $where = null, ?string $key = null): array
    {
        return [];
    }
}
