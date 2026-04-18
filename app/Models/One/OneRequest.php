<?php

namespace App\Models\One;

use App\Models\_\ModelTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int          $or_id
 * @property string       $or_turn
 * @property string       $or_url
 * @property string       $or_key
 * @property string       $or_headers
 * @property string       $or_form_data
 * @property string       $or_status_code
 * @property array|string $or_response
 */
class OneRequest extends Model
{
    use ModelTrait;

    // 记录 122 平台请求日志。
    public const CREATED_AT = 'or_created_at';
    public const UPDATED_AT = 'or_updated_at';
    public const UPDATED_BY = 'or_updated_by';

    protected $primaryKey = 'or_id';

    protected $guarded = ['or_id'];

    public static function indexQuery(): Builder
    {
        return static::query();
    }

    public static function optionsQuery(): Builder
    {
        return static::query();
    }

    protected function casts()
    {
        return [
            // 响应体按数组存储，便于查询与解析。
            'or_response' => 'array',
        ];
    }
}
