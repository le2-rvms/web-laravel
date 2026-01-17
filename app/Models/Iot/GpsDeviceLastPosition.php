<?php

namespace App\Models\Iot;

use App\Models\_\ModelTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string      $terminal_id
 * @property null|string $tenant_id
 * @property null|string $gps_time
 * @property null|float  $latitude_gcj
 * @property null|float  $longitude_gcj
 */
class GpsDeviceLastPosition extends Model
{
    use ModelTrait;

    public const CREATED_AT = null;
    public const UPDATED_AT = null;

    public $incrementing = false;

    protected $connection = 'pgsql-iot';

    protected $table = 'gps_device_last_positions';

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
}
