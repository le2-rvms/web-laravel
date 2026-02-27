<?php

namespace App\Models\Iot;

use App\Attributes\ClassName;
use App\Models\_\ModelTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ClassName('设备')]
/**
 * @property int         $dev_id
 * @property string      $terminal_id
 * @property string      $dev_name
 * @property null|string $company_id
 * @property null|string $manufacturer_id
 * @property null|string $product_key
 * @property null|string $sim_number
 * @property null|string $_vehicle_plate
 * @property null|string $_vehicle_vin
 * @property null|string $_bind_status
 * @property null|string $device_status
 * @property null|string $review_status
 * @property null|string $auth_code_seed
 * @property null|string $auth_code_issued_at
 * @property null|string $auth_code_expires_at
 * @property null|int    $auth_failures
 * @property null|string $auth_block_until
 * @property null|int    $city_relation_id
 * @property string      $created_at
 */
class IotDevice extends Model
{
    use ModelTrait;

    // 设备信息存于独立 IoT 数据库。
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = null;
    public const UPDATED_BY = null;

    protected $connection = 'timescaledb';

    protected $table = 'devices';

    protected $keyType    = 'string';
    protected $primaryKey = 'terminal_id';

    protected $guarded = ['dev_id'];

    public static function indexQuery(): Builder
    {
        return static::query()->from('devices', 'dev')->with('IotDeviceProduct');
    }

    public static function optionsQuery(): Builder
    {
        return static::query()
            ->orderBy('dev_id')
            ->selectRaw('dev_name as text, terminal_id as value')
        ;
    }

    public static function terminalIdArray(): array
    {
        $key = static::getOptionKey();

        $value = static::query()
            ->orderBy('dev_id')
            ->pluck('terminal_id')
            ->toArray()
        ;

        return [$key => $value];
    }

    public function IotDeviceProduct(): BelongsTo
    {
        return $this->belongsTo(IotDeviceProduct::class, 'product_key', 'product_key');
    }

    protected static function booted(): void
    {
        static::addGlobalScope('company_id', function (Builder $builder) {
            $builder->where('company_id', config('app.company_id'));
        });
    }

    protected function casts(): array
    {
        return [
            'auth_code_issued_at'  => 'datetime:Y-m-d H:i:s',
            'auth_code_expires_at' => 'datetime:Y-m-d H:i:s',
            'auth_block_until'     => 'datetime:Y-m-d H:i:s',
            'created_at'           => 'datetime:Y-m-d H:i:s',
            'auth_failures'        => 'integer',
            'city_relation_id'     => 'integer',
        ];
    }
}
