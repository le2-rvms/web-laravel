<?php

namespace App\Models\Admin;

use App\Attributes\ClassName;
use App\Enum\Admin\ATeamLimit;
use App\Enum\Admin\AUserType;
use App\Models\_\ModelTrait;
use App\Models\Vehicle\Vehicle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[ClassName('员工')]
/**
 * @property int            $id                    序号
 * @property string         $name                  姓名
 * @property string         $email                 邮件地址
 * @property null|Carbon    $email_verified_at
 * @property string         $password              密码
 * @property null|string    $remember_token
 * @property string         $password_confirmation 确认密码
 * @property array          $roles_                角色
 * @property string         $a_wecom_name          企业微信账号
 * @property array          $a_team_ids            关联车队
 * @property ATeamLimit|int $a_team_limit          是否限制车队
 * @property AUserType|int  $a_user_type           账号类型
 * @property null|Carbon    $a_expires_at          账号过期时间；当为 null 的时候，永不过期
 * @property null|bool      $_is_mock
 *                                                 -- relation
 */
class Admin extends Authenticatable
{
    use HasApiTokens;
    use Notifiable;

    use HasRoles;

    use ModelTrait;

    public const UPDATED_BY = 'updated_by';

    protected $table = 'admins';

    protected $attributes = [];

    protected $appends = [
        'a_team_limit_label',
        'a_expires_status_label',
    ];

    protected $guarded = [
        'id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function adminlte_profile_url()
    {
        return route('profile.edit');
    }

    public static function indexQuery(): Builder
    {
        return static::query()
            ->from('admins as a')
        ;
    }

    public static function optionsQuery(): Builder
    {
        return static::query()
            ->orderBy('id')
            ->select(DB::raw('name as text,id as value'))
        ;
    }

    public static function optionsWithRoles(?\Closure $where = null, ?string $key = null): array
    {
        $key = static::getOptionKey($key);

        $admins = static::query()
            ->where($where)
            ->orderBy('id')
            ->where('a_user_type', '!=', AUserType::TEMP)
            ->with('roles')->get()
        ;

        $value = $admins->map(function ($admin) {
            $role_names = $admin->roles->pluck('name')->toArray();

            return [
                'text'  => $admin->name.($role_names ? '('.implode(',', $role_names).')' : ''),
                'value' => $admin->id,
            ];
        });

        return [$key => $value];
    }

    public function VehicleManagers(): HasMany
    {
        return $this->hasMany(Vehicle::class, 've_vehicle_manager', 'id');
    }

    public function SalesManagers(): HasMany
    {
        return $this->hasMany(Vehicle::class, 've_vehicle_manager', 'id');
    }

    public function DriverManagers(): HasMany
    {
        return $this->hasMany(Vehicle::class, 've_vehicle_manager', 'id');
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime:Y-m-d H:i:s',
            'password'          => 'hashed',
            'a_expires_at'      => 'datetime:Y-m-d H:i:s',
            'a_user_type'       => AUserType::class,
            'a_team_limit'      => ATeamLimit::class,
        ];
    }

    protected function aTeamLimitLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('a_team_limit')?->label
        );
    }

    protected function aTeamIds(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                return is_string($value) ? json_decode($value, true) : [];
            },
            set: function ($value) {
                return is_null($value) ? null : json_encode($value);
            }
        );
    }

    protected function aExpiresStatus(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->a_expires_at && $this->a_expires_at->lt(\Carbon\Carbon::now())
        );
    }

    protected function aExpiresStatusLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->expiresStatus ? '过期' : '正常'
        );
    }
}
