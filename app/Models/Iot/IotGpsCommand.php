<?php

namespace App\Models\Iot;

use App\Attributes\ClassName;
use App\Models\_\ModelTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ClassName('GPS命令')]
/**
 * @property int    $id
 * @property int    $device_id
 * @property string $terminal_id
 * @property string $cmd_type
 * @property string $payload
 * @property int    $flow_id
 * @property string $status
 * @property int    $retries
 * @property int    $max_retries
 * @property string $created_at
 * @property string $updated_at
 */
class IotGpsCommand extends Model
{
    use ModelTrait;

    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    protected $connection = 'timescaledb';

    protected $table = 'gps_commands';

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

    public function Device(): BelongsTo
    {
        return $this->belongsTo(IotDevice::class, 'device_id', 'dev_id');
    }

    protected function casts(): array
    {
        return [
            'device_id'   => 'integer',
            'flow_id'     => 'integer',
            'retries'     => 'integer',
            'max_retries' => 'integer',
            'created_at'  => 'datetime:Y-m-d H:i:s',
            'updated_at'  => 'datetime:Y-m-d H:i:s',
        ];
    }
}
