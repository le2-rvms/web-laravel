<?php

namespace App\Models\One;

use App\Models\_\ModelTrait;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int    $oc_id              租赁合同ID
 * @property string $oc_plate_type      号牌种类
 * @property string $oc_plate_number    号牌号码
 * @property string $oc_rental_type     租赁类型
 * @property string $oc_contract_no     租赁合同编号
 * @property Carbon $oc_signed_at       合同签订时间
 * @property Carbon $oc_rental_start_at 租赁开始时间
 * @property Carbon $oc_rental_end_at   租赁结束时间
 * @property string $oc_id_doc_type     身份证明名称
 * @property string $oc_id_doc_no       身份证明号码
 */
class OneContract extends Model
{
    use ModelTrait;

    public const CREATED_AT = 'oc_created_at';
    public const UPDATED_AT = 'oc_updated_at';
    public const UPDATED_BY = 'oc_updated_by';

    protected $table = 'one_contract';

    protected $primaryKey = 'oc_id';

    protected $guarded = ['oc_id'];

    protected $casts = [
        'oc_signed_at'       => 'datetime:Y-m-d H:i',
        'oc_rental_start_at' => 'datetime:Y-m-d H:i',
        'oc_rental_end_at'   => 'datetime:Y-m-d H:i',
    ];

    public static function indexQuery(): Builder
    {
        return static::query()
            ->from('one_contract', 'oc')
            ->orderByDesc('oc.oc_id')
            ->select('oc.*')
        ;
    }

    public static function optionsQuery(): Builder
    {
        return static::query()
            ->from('one_contract', 'oc')
            ->orderByDesc('oc.oc_id')
            ->select('oc.oc_contract_no as text', 'oc.oc_id as value')
        ;
    }
}
