<?php

namespace App\Models\Iot;

use App\Attributes\ClassName;
use App\Models\_\ModelTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[ClassName('GPS电子围栏')]
/**
 * @property int    $id
 * @property string $name
 * @property float  $center_lat
 * @property float  $center_lon
 * @property float  $radius_meters
 * @property bool   $active
 * @property string $created_at
 */
class IotGpsGeofence extends Model
{
    use ModelTrait;

    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = null;

    protected $connection = 'timescaledb';

    protected $table = 'gps_geofences';

    protected $primaryKey = 'id';

    protected $guarded = ['id'];

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
            'center_lat'    => 'float',
            'center_lon'    => 'float',
            'radius_meters' => 'float',
            'active'        => 'boolean',
            'created_at'    => 'datetime:Y-m-d H:i:s',
        ];
    }
}
