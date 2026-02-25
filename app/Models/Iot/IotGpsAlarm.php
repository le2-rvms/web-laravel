<?php

namespace App\Models\Iot;

use App\Attributes\ClassName;
use App\Models\_\ModelTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[ClassName('GPS告警')]
/**
 * @property int    $id
 * @property string $terminal_id
 * @property string $alarm_type
 * @property string $description
 * @property string $gps_time
 * @property string $created_at
 */
class IotGpsAlarm extends Model
{
    use ModelTrait;

    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = null;

    protected $connection = 'timescaledb';

    protected $table = 'gps_alarms';

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
            'gps_time'   => 'datetime:Y-m-d H:i:s',
            'created_at' => 'datetime:Y-m-d H:i:s',
        ];
    }
}
