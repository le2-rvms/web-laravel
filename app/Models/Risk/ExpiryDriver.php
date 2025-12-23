<?php

namespace App\Models\Risk;

use App\Enum\Customer\CuiGender;
use App\Enum\Customer\CuType;
use App\Models\_\ModelTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

class ExpiryDriver extends Model
{
    use ModelTrait;

    public static function indexQuery(): Builder
    {
        //        $cu_id = $search['cu_id'] ?? null;

        return DB::query()
            ->from('customer_individuals', 'cui')
            ->leftJoin('customers as cu', function (JoinClause $join) {
                $join->on('cui.cui_cu_id', '=', 'cu.cu_id')
                    ->where('cu.cu_type', '=', CuType::INDIVIDUAL)
                ;
            })
//            ->where('cu.cu_type', CuCustomerType::INDIVIDUAL)
//            ->where(function (Builder $q) use ($targetDate) {
//                $q->where('cui.cui_driver_license_expiry_date', '<=', $targetDate)
//                    ->orWhere('cui_id_expiry_date', '<=', $targetDate)
//                ;
//            })
//            ->when($cu_id, function (Builder $query) use ($cu_id) {
//                $query->where('cu.cu_id', '=', $cu_id);
//            })
            ->select('cu.*', 'cui.*')
            ->addSelect(
                DB::raw(CuType::toCaseSQL()),
                DB::raw(CuiGender::toCaseSQL()),
            )
        ;
    }

    public static function options(?\Closure $where = null, ?string $key = null): array
    {
        return [];
    }
}
