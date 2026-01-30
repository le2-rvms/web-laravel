<?php

namespace App\Models\Iot;

use App\Attributes\ClassName;
use App\Models\_\ModelTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ClassName('')]
class IotDeviceProduct extends Model
{
    use ModelTrait;

    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    protected $connection = 'timescaledb';

    protected $table = 'device_products';

    protected $primaryKey = 'product_id';

    protected $guarded = ['product_id'];

    public static function indexQuery(): Builder
    {
        return static::query()->from('device_products', 'pro');
    }

    public static function optionsQuery(): Builder
    {
        return static::query();
    }

    public function IotDevices(): HasMany
    {
        return $this->hasMany(IotDevice::class, 'product_key', 'product_key');
    }
}
