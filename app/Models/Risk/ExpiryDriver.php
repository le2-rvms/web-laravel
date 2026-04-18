<?php

namespace App\Models\Risk;

use App\Enum\Customer\CuiGender;
use App\Enum\Customer\CuType;
use App\Models\_\ModelTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

class ExpiryDriver extends Model
{
    use ModelTrait;

    public static function indexQuery(): Builder
    {
        return static::query()
            ->from('customer_individuals', 'cui')
            ->leftJoin('customers as cu', function (JoinClause $join) {
                $join->on('cui.cui_cu_id', '=', 'cu.cu_id')
                    ->where('cu.cu_type', '=', CuType::INDIVIDUAL)
                ;
            })
            ->select('cu.*', 'cui.*')
            ->addSelect(
                DB::raw(CuType::toCaseSQL()),
                DB::raw(CuiGender::toCaseSQL()),
            )
        ;
    }

    public static function optionsQuery(): Builder
    {
        return static::query();
    }
}
