<?php

namespace App\Models\_;

use App\Enum\AuthUserType;
use App\Http\Controllers\Controller;
use App\Models\Admin\Admin;
use App\Models\Sale\SaleContract;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @property null|string $updated_by
 * @property null|Carbon $created_at
 * @property null|Carbon $updated_at
 */
trait ModelTrait
{
    use HasFactory;

    //    use GenDataTrait;

    use FieldsTrait;

    public static $indexValue;
    public static $detailValue;

    //    public function getHidden()
    //    {
    //        return array_merge($this->hidden, ['created_at', 'updated_at']);
    //    }

    abstract public static function indexQuery();

    public function toArray(): array|object
    {
        $attributes = parent::toArray();

        return !empty($attributes) ? $attributes : (object) [];
    }

    public static function indexList(?\Closure $callback = null): array
    {
        //        $query = array_filter($query);
        $key = preg_replace('/^.*\\\/', '', get_called_class()).'IndexList';

        $query = static::indexQuery();

        if ($callback) {
            $callback($query); // 注意：这里是对主查询操作
        }

        static::$indexValue = $value = $query->get();

        return [$key => $value];
    }

    public static function detailList(?\Closure $where = null): array
    {
        //        $query = array_filter($query);
        $key = preg_replace('/^.*\\\/', '', get_called_class()).'DetailList';

        static::$detailValue = $value = static::detailQuery()
            ->where($where)
            ->get()
        ;

        return [$key => $value];
    }

    public static function indexStat(): array
    {
        $key   = preg_replace('/^.*\\\/', '', get_called_class()).'IndexStat';
        $value = static::indexStatValue(static::$indexValue);

        return [$key => $value];
    }

    //    public static function customerQuery(Controller $controller): Builder
    //    {
    //        $perPage = 20;
    //
    //        $controller->response()->withExtras(
    //            ['perPage' => $perPage]
    //        );
    //
    //        $auth = auth();
    //
    //        return static::indexQuery(['cu_id' => $auth->id()])
    //            ->forPage(1, $perPage)
    //        ;
    //    }

    //    public static function customerQueryWithOrderVeId(Controller $controller, Request $request): Builder
    //    {
    //        $page = $request->input('page', 1);
    //
    //        $perPage = 20;
    //
    //        $controller->response()->withExtras(
    //            ['perPage' => $perPage]
    //        );
    //
    //        return static::indexQuery()
    //            ->whereIn('ve.ve_id', SaleContract::CustomerHasVeId())
    //            ->forPage($page, $perPage)
    //        ;
    //    }

    public function ProcessedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'processed_by', 'id');
    }

    abstract public static function options(?\Closure $where = null, ?string $key = null): array;

    protected function uploadFile(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes) {
                if (!$value) {
                    return new \stdClass();
                }
                $file = $value ? json_decode($value, true) : [];
                if (!$file) {
                    return new \stdClass();
                }

                if ($file['path_'] ?? null) {
                    $file['url'] = Storage::disk('s3')->url($file['path_']);
                }

                return $file;
            },
            set: function (mixed $value, array $attributes) {
                return json_encode($value);
            },
        );
    }

    protected function uploadFileArray(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes) {
                if (is_array($value)) {
                    return $value;
                }
                $files = $value ? json_decode($value, true) : [];
                if ($files) {
                    foreach ($files as &$file) {
                        if (is_array($file)) {
                            if ($file['path_'] ?? null) {
                                $file['url'] = Storage::disk('s3')->url($file['path_']);
                            }
                        } else {
                            return [];
                        }
                    }
                }

                return $files;
            },
            set: function (mixed $value, array $attributes) {
                return json_encode($value);
            },
        );
    }

    protected function arrayInfo(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                $array = $value ? json_decode($value, true) : [];
                foreach ($array as &$item) {
                    if ($item['info_photos'] ?? null) {
                        foreach ($item['info_photos'] as &$photo) {
                            if (is_array($photo)) {
                                if ($photo['path_'] ?? null) {
                                    $photo['url'] = Storage::disk('s3')->url($photo['path_']);
                                }
                            }
                        }
                    }
                }

                return $array;
            },
            set: function (array $value) {
                return json_encode($value);
            }
        );
    }

    protected static function bootModelTrait(): void
    {
        static::saving(function (self $model) {
            if (Auth::check()) {
                $updated_by_name = static::UPDATED_BY ?? 'updated_by';

                $model->{$updated_by_name} = AuthUserType::getValue();
            }
        });
    }

    protected static function getOptionKey(?string $key = null): string
    {
        return ($key ? Str::studly($key) : preg_replace('/^.*\\\/', '', get_called_class())).'Datas';
    }
}
