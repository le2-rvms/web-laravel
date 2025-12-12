<?php

namespace App\Models\Sale;

use App\Models\_\ModelTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * @property int    $sce_id              扩展租车合同ID
 * @property int    $sc_id               租车合同ID
 * @property string $sce_wecom_group_url 微信群机器人url
 *
 * -- relation
 * @property SaleContract $SaleContract
 */
class SaleContractExt extends Model
{
    use ModelTrait;

    protected $primaryKey = 'sce_id';

    protected $guarded = [];

    public function SaleContract(): BelongsTo
    {
        return $this->BelongsTo(SaleContract::class, 'sc_id', 'sc_id');
    }

    public static function indexQuery(array $search = []): Builder
    {
        return DB::query()
            ->from('sale_contract_exts', 'sce')
            ->leftJoin('sale_contracts as sc', 'sce.sc_id', '=', 'sc.sc_id')
            ->leftJoin('vehicles as ve', 've.ve_id', '=', 'sc.ve_id')
            ->leftJoin('customers as cu', 'cu.cu_id', '=', 'sc.cu_id')
            ->orderByDesc('sce.sce_id')
            ->select('sce.sce_id', 'sce.sc_id', 'sce.sce_wecom_group_url')
        ;
    }

    public static function options(?\Closure $where = null): array
    {
        return [];
    }
}
