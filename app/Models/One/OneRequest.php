<?php

namespace App\Models\One;

use App\Models\_\ModelTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

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

    public const CREATED_AT = 'or_created_at';
    public const UPDATED_AT = 'or_updated_at';
    public const UPDATED_BY = 'or_updated_by';

    protected $primaryKey = 'or_id';

    protected $guarded = ['or_id'];

    public static function indexQuery(): Builder
    {
        return DB::query();
    }

    public static function options(?\Closure $where = null, ?string $key = null): array
    {
        return [];
    }

    protected function casts()
    {
        return [
            'or_response' => 'array',
        ];
    }
}
