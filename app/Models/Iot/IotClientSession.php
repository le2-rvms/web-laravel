<?php

namespace App\Models\Iot;

use App\Attributes\ClassName;
use App\Models\_\ModelTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[ClassName('')]
class IotClientSession extends Model
{
    use ModelTrait;

    public const CREATED_AT = null;
    public const UPDATED_AT = null;

    protected $connection = 'timescaledb';

    protected $table = 'client_sessions';

    protected $primaryKey = 'client_id';

    protected $guarded = ['client_id'];

    public static function indexQuery(): Builder
    {
        return static::query();
    }

    public static function optionsQuery(): Builder
    {
        return static::query();
    }
}
