<?php

namespace App\Models\Iot;

use App\Models\_\ModelTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $terminal_id
 * @property string $gps_time
 * @property float  $latitude
 * @property float  $longitude
 * @property float  $latitude_gcj
 * @property float  $longitude_gcj
 * @property int    $altitude
 * @property float  $speed
 * @property int    $direction
 * @property int    $status
 * @property int    $alarm
 * @property array  $extra
 * @property string $updated_at
 */
class IotGpsPositionLast extends Model
{
    use ModelTrait;

    public const CREATED_AT = null;
    public const UPDATED_AT = 'updated_at';

    public $incrementing = false;

    protected $connection = 'timescaledb';

    protected $table = 'gps_position_last';

    protected $primaryKey = 'terminal_id';

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

    protected function casts(): array
    {
        return [
            'gps_time'      => 'datetime:Y-m-d H:i:s',
            'updated_at'    => 'datetime:Y-m-d H:i:s',
            'latitude'      => 'float',
            'longitude'     => 'float',
            'latitude_gcj'  => 'float',
            'longitude_gcj' => 'float',
            'altitude'      => 'integer',
            'speed'         => 'float',
            'direction'     => 'integer',
            'status'        => 'integer',
            'alarm'         => 'integer',
            'extra'         => 'array',
        ];
    }
}
