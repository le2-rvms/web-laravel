<?php

namespace App\Models\Iot;

use App\Models\_\ModelTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class IotGpsPositionHistory extends Model
{
    use ModelTrait;

    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = null;

    public $incrementing = false;

    protected $connection = 'timescaledb';

    protected $table = 'gps_position_histories';

    protected $primaryKey;

    protected $keyType = 'string';

    protected $guarded = [];

    public static function indexQuery(): Builder
    {
        return static::query();
    }

    public static function optionsQuery(): Builder
    {
        return static::query();
    }
}
