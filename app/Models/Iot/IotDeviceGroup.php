<?php

namespace App\Models\Iot;

use App\Attributes\ClassName;
use App\Models\_\ModelTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ClassName('设备分组')]
/**
 * @property int         $group_id
 * @property string      $group_name
 * @property null|string $description
 * @property string      $product_key
 * @property null|string $created_at
 * @property null|string $updated_at
 */
class IotDeviceGroup extends Model
{
    use ModelTrait;

    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    protected $connection = 'timescaledb';

    protected $table = 'device_groups';

    protected $primaryKey = 'group_id';

    protected $guarded = ['group_id'];

    public static function indexQuery(): Builder
    {
        return static::query();
    }

    public static function optionsQuery(): Builder
    {
        return static::query();
    }

    public function Product(): BelongsTo
    {
        return $this->belongsTo(IotDeviceProduct::class, 'product_key', 'product_key');
    }

    public function Mappings(): HasMany
    {
        return $this->hasMany(IotDeviceGroupMapping::class, 'group_id', 'group_id');
    }

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime:Y-m-d H:i:s',
            'updated_at' => 'datetime:Y-m-d H:i:s',
        ];
    }
}
