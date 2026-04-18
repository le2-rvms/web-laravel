<?php

namespace App\Models\Iot;

use App\Attributes\ClassName;
use App\Models\_\ModelTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[ClassName('客户端会话')]
/**
 * @property string      $client_id
 * @property null|string $username
 * @property string      $last_event_ts
 * @property string      $last_event_type
 * @property null|string $last_connect_ts
 * @property null|string $last_disconnect_ts
 * @property null|string $last_peer
 * @property null|string $last_protocol
 * @property null|int    $last_reason_code
 * @property null|array  $extra
 */
class IotClientSession extends Model
{
    use ModelTrait;

    public const CREATED_AT = null;
    public const UPDATED_AT = null;

    public $incrementing = false;

    protected $connection = 'timescaledb';

    protected $table = 'client_sessions';

    protected $primaryKey = 'client_id';

    protected $keyType = 'string';

    protected $guarded = ['client_id'];

    public static function indexQuery(): Builder
    {
        return static::query()
            ->from('client_sessions', 'cs')
        ;
    }

    public static function optionsQuery(): Builder
    {
        return static::query();
    }

    protected function casts(): array
    {
        return [
            'last_event_ts'      => 'datetime:Y-m-d H:i:s',
            'last_connect_ts'    => 'datetime:Y-m-d H:i:s',
            'last_disconnect_ts' => 'datetime:Y-m-d H:i:s',
            'extra'              => 'array',
        ];
    }
}
