<?php

namespace App\Models\Admin;

use App\Attributes\ClassName;
use App\Models\_\ModelTrait;
use Illuminate\Database\Query\Builder;
use Spatie\Permission\Models\Permission;

#[ClassName('员工权限')]
/**
 * @property int    $id
 * @property string $name       权限英文名
 * @property string $title      权限标题
 * @property string $guard_name
 */
class AdminPermission extends Permission
{
    use ModelTrait;

    public const UPDATED_BY = 'updated_by';

    protected $table = 'permissions';

    public static function options(?\Closure $where = null, ?string $key = null): array
    {
        $key = static::getOptionKey($key);

        $value = static::query()->toBase()
            ->orderBy('group_name')->orderBy('name')
            ->get()
            ->groupBy(fn ($row) => $row->group_name)
        ;

        return [$key => $value];
    }

    public static function indexQuery(): Builder
    {
        // TODO: Implement indexQuery() method.
    }
}
