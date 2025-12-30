<?php

namespace App\Models\Risk;

use App\Enum\Vehicle\VeStatusService;
use App\Models\_\ModelTrait;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ExpiryVehicle extends Model
{
    use ModelTrait;

    public static function indexQuery(): Builder
    {
        // 默认统计未来 60 天内证照到期车辆。
        $days = 60;

        $targetDate = Carbon::today()->addDays($days)->toDateString();

        return static::query()
            ->from('vehicles', 've')
            ->leftJoin('vehicle_models as vm', 've.ve_vm_id', '=', 'vm.vm_id')
            ->select('ve.*', 'vm.vm_brand_name', 'vm.vm_model_name')
            ->addSelect(
                // 计算证件有效期剩余天数，供前端直接展示。
                DB::raw('trunc(EXTRACT(EPOCH FROM ve.ve_cert_valid_to - now() ) / 86400.0,0) as ve_cert_valid_interval'),
            )
            // 仅统计在役车辆。
            ->where('ve.ve_status_service', '=', VeStatusService::YES)
            ->where('ve.ve_cert_valid_to', '<=', $targetDate)
        ;
    }

    public static function optionsQuery(): Builder
    {
        return static::query();
    }
}
