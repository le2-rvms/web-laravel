<?php

namespace App\Models\Iot;

use App\Attributes\ClassName;
use App\Models\_\ModelTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ClassName('设备产品')]
/**
 * @property int         $product_id
 * @property string      $product_key
 * @property string      $product_name
 * @property null|string $description
 * @property null|string $manufacturer
 * @property null|string $protocol
 * @property null|string $category
 * @property null|string $created_at
 * @property null|string $updated_at
 */
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

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime:Y-m-d H:i:s',
            'updated_at' => 'datetime:Y-m-d H:i:s',
        ];
    }
}
