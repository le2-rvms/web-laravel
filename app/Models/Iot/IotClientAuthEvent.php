<?php

namespace App\Models\Iot;

use App\Attributes\ClassName;
use App\Models\_\ModelTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[ClassName('客户端鉴权事件')]
/**
 * @property int         $id
 * @property string      $ts
 * @property string      $result
 * @property string      $reason
 * @property null|string $client_id
 * @property null|string $username
 * @property null|string $peer
 * @property null|string $protocol
 */
class IotClientAuthEvent extends Model
{
    use ModelTrait;

    public const CREATED_AT = null;
    public const UPDATED_AT = null;

    protected $connection = 'timescaledb';

    protected $table = 'client_auth_events';

    protected $primaryKey = 'id';

    protected $guarded = ['id'];

    public static function indexQuery(): Builder
    {
        return static::query()
            ->from('client_auth_events', 'ca')
        ;
    }

    public static function optionsQuery(): Builder
    {
        return static::query();
    }

    protected function casts(): array
    {
        return [
            'ts' => 'datetime:Y-m-d H:i:s',
        ];
    }
}
