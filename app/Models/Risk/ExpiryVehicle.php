<?php

namespace App\Models\Risk;

use App\Enum\Vehicle\VeStatusService;
use App\Models\_\ModelTrait;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class ExpiryVehicle extends Model
{
    use ModelTrait;

    public static function indexQuery(): Builder
    {
        $days = 60;

        $targetDate = Carbon::today()->addDays($days)->toDateString();

        return DB::query()
            ->from('vehicles', 've')
            ->leftJoin('vehicle_models as vm', 've.ve_vm_id', '=', 'vm.vm_id')
            ->select('ve.*', 'vm.vm_brand_name', 'vm.vm_model_name')
            ->addSelect(
                DB::raw('trunc(EXTRACT(EPOCH FROM ve.ve_cert_valid_to - now() ) / 86400.0,0) as ve_cert_valid_interval'),
            )
            ->where('ve.ve_status_service', '=', VeStatusService::YES)
            ->where('ve.ve_cert_valid_to', '<=', $targetDate)
        ;
    }

    public static function options(?\Closure $where = null, ?string $key = null): array
    {
        return [];
    }
}
