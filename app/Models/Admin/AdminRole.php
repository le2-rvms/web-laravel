<?php

namespace App\Models\Admin;

use App\Attributes\ClassName;
use App\Enum\Admin\ArIsCustom;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

#[ClassName('员工角色')]
/**
 * @property int            $id          序号
 * @property string         $name        角色名
 * @property string         $guard_name
 * @property ArIsCustom|int $is_custom   是否是自定义
 * @property Collection     $permissions
 */
class AdminRole extends Role
{
    protected $table = 'admin_roles';

    protected $attributes = [
        'is_custom' => ArIsCustom::NO,
    ];

    public static function options(?\Closure $where = null): array
    {
        $key   = preg_replace('/^.*\\\/', '', get_called_class()).'Options';
        $value = Role::query()->toBase()
            ->where('name', '!=', config('setting.super_role.name'))
            ->orderBy('id')
            ->select(DB::raw('name as text,id as value'))->get()
        ;

        return [$key => $value];
    }

    protected function casts(): array
    {
        return [
            'is_custom' => ArIsCustom::class,
        ];
    }
}
