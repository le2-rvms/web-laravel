<?php

namespace App\Models\Sale;

use App\Models\_\ModelTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * @property int    $soe_id              扩展租车合同ID
 * @property int    $so_id               租车合同ID
 * @property string $soe_wecom_group_url 微信群机器人url
 *
 * -- relation
 * @property SaleOrder $SaleOrder
 */
class SaleOrderExt extends Model
{
    use ModelTrait;

    protected $primaryKey = 'soe_id';

    protected $guarded = [];

    public function SaleOrder(): BelongsTo
    {
        return $this->BelongsTo(SaleOrder::class, 'so_id', 'so_id');
    }

    public static function indexQuery(array $search = []): Builder
    {
        return DB::query()
            ->from('sale_order_exts', 'soe')
            ->leftJoin('sale_orders as so', 'soe.so_id', '=', 'so.so_id')
            ->leftJoin('vehicles as ve', 've.ve_id', '=', 'so.ve_id')
            ->leftJoin('customers as cu', 'cu.cu_id', '=', 'so.cu_id')
            ->orderByDesc('soe.soe_id')
            ->select('soe.soe_id', 'soe.so_id', 'soe.soe_wecom_group_url')
        ;
    }

    public static function options(?\Closure $where = null): array
    {
        return [];
    }
}
