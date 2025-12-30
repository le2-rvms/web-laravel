<?php

namespace App\Models\Admin;

use App\Attributes\ClassName;
use App\Enum\Admin\ArIsCustom;
use App\Models\_\ModelTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

#[ClassName('员工角色')]
/**
 * @property int            $id           序号
 * @property string         $name         角色名
 * @property string         $guard_name
 * @property ArIsCustom|int $ar_is_custom 是否是自定义
 * @property Collection     $permissions
 */
class AdminRole extends Role
{
    use ModelTrait;

    public const string role_system = '系统管理';

    public const string role_manager    = '经理';
    public const string role_driver_mgr = '驾管';
    public const string role_payment    = '财务';

    public const string role_sales       = '销售';
    public const string role_vehicle_mgr = '车管';

    public const string role_vehicle_service = '修理厂';

    public const UPDATED_BY = 'updated_by';
    protected $table        = 'admin_roles';

    protected $attributes = [
        'ar_is_custom' => ArIsCustom::NO,
    ];

    public static function optionsQuery(): Builder
    {
        return static::query()
            ->where('name', '!=', config('setting.super_role.name'))
            ->orderBy('id')
            ->select(DB::raw('name as text,id as value'))
        ;
    }

    public static function indexQuery(): Builder
    {
        return static::query();
    }

    protected function casts(): array
    {
        return [
            'ar_is_custom' => ArIsCustom::class,
        ];
    }
}
