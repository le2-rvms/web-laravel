<?php

namespace App\Models\Payment;

use App\Attributes\ClassName;
use App\Enum\Payment\PIsValid;
use App\Enum\Payment\PtIsActive;
use App\Models\_\ModelTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

#[ClassName('财务类型', '信息')]
/**
 * @property int         $pt_id          收付款类型序号
 * @property string      $pt_name        收付款类型名称；例如定金、租金
 * @property null|int    $pt_required    是否不能关闭的
 * @property null|int    $pt_is_active   是否启用
 * @property null|string $pt_description 描述
 */
class PaymentType extends Model
{
    use ModelTrait;

    public const CREATED_AT = 'pt_created_at';
    public const UPDATED_AT = 'pt_updated_at';
    public const UPDATED_BY = 'pt_updated_by';

    protected $primaryKey = 'pt_id';

    protected $guarded = ['pt_id'];

    public function Payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'p_pt_id', 'pt_id');
    }

    public static function options(
        ?\Closure $where = null
    ): array {
        $key = preg_replace('/^.*\\\/', '', get_called_class()).'Options';

        $value = static::query()->toBase()
            ->where('pt_is_active', PtIsActive::ENABLED)
            ->select(DB::raw('pt_name as text, pt_id as value'))
            ->get()
        ;

        return [$key => $value];
    }

    public static function options_with_count(?\Closure $where = null): array
    {
        $key = preg_replace('/^.*\\\/', '', get_called_class()).'Options';

        $value = DB::query()
            ->from('payment_types', 'pt')
            ->leftJoin('payments as p', function (JoinClause $join) {
                $join->on('p.p_pt_id', '=', 'pt.pt_id')
                    ->where('p.p_is_valid', '=', PIsValid::VALID) // 只统计有效的财务记录
                ;
            }, )
            ->where('pt.pt_is_active', PtIsActive::ENABLED)
            ->select(DB::raw("pt.pt_id as value, pt.pt_name || '(' ||  COUNT(p.p_id) || ')'  as text"))
            ->groupBy('pt.pt_id')
            ->orderBy('pt.pt_id')
            ->get()
        ;

        return [$key => $value];
    }

    public static function indexOptions(): array
    {
        $key = preg_replace('/^.*\\\/', '', get_called_class()).'Index';

        $value = static::query()->toBase()
            ->select(DB::raw('pt_name as text, pt_id as value, required as disable'))
            ->orderby('pt_id')
            ->get()
        ;

        return [$key => $value];
    }

    public static function indexQuery(array $search = []): Builder
    {
        return DB::query();
    }
}
