<?php

namespace App\Models\Iot;

use App\Attributes\ClassName;
use App\Enum\Iot\EventType_CONN;
use App\Models\_\ModelTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

#[ClassName('客户端连接事件')]
/**
 * @property int         $id
 * @property string      $ts
 * @property string      $event_type
 * @property string      $client_id
 * @property null|string $username
 * @property null|string $peer
 * @property null|string $protocol
 * @property null|int    $reason_code
 * @property null|array  $extra
 */
class IotClientConnEvent extends Model
{
    use ModelTrait;

    public const CREATED_AT = null;
    public const UPDATED_AT = null;

    protected $connection = 'timescaledb';

    protected $table = 'client_conn_events';

    protected $primaryKey = 'id';

    protected $guarded = ['id'];

    protected $appends = [
        'event_type_label',
    ];

    public static function indexQuery(): Builder
    {
        return static::query()
            ->from('client_conn_events', 'ce')
        ;
    }

    public static function optionsQuery(): Builder
    {
        return static::query();
    }

    protected function casts(): array
    {
        return [
            'ts'          => 'datetime:Y-m-d H:i:s',
            'reason_code' => 'integer',
            'extra'       => 'array',
            'event_type'  => EventType_CONN::class,
        ];
    }

    protected function eventTypeLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('event_type')?->label
        );
    }
}
