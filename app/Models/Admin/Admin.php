<?php

namespace App\Models\Admin;

use App\Attributes\ClassName;
use App\Enum\Admin\AdmTeamLimit;
use App\Enum\Admin\AdmUserType;
use App\Models\_\ModelTrait;
use App\Models\Vehicle\Vehicle;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[ClassName('员工')]
/**
 * @property int              $id                    序号
 * @property string           $name                  姓名
 * @property string           $email                 邮件地址
 * @property string           $wecom_name            企业微信账号
 * @property null|Carbon      $email_verified_at
 * @property string           $password              密码
 * @property null|string      $remember_token
 * @property string           $password_confirmation 确认密码
 * @property array            $roles_                角色
 * @property array            $team_ids              关联车队
 * @property AdmTeamLimit|int $team_limit            是否限制车队
 * @property AdmUserType|int  $user_type             账号类型
 * @property null|Carbon      $expires_at            账号过期时间；当为 null 的时候，永不过期
 * @property null|bool        $is_mock
 *                                                   -- relation
 */
class Admin extends Authenticatable
{
    use HasApiTokens;
    use Notifiable;

    use HasRoles;

    use ModelTrait;

    protected $table = 'admins';

    protected $attributes = [];

    protected $appends = [
        'team_limit_label',
        'expires_status_label',
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

    public static function indexQuery(array $search = []): Builder
    {
        return DB::query()
            ->from('admins as adm')
        ;
    }

    public static function options(?\Closure $where = null): array
    {
        $key   = preg_replace('/^.*\\\/', '', get_called_class()).'Options';
        $value = static::query()
            ->where($where)
            ->orderBy('id')
            ->select(DB::raw('name as text,id as value'))->get()
        ;

        return [$key => $value];
    }

    public static function optionsWithRoles(?\Closure $where = null): array
    {
        $key    = preg_replace('/^.*\\\/', '', get_called_class()).'Options';
        $admins = static::query()
            ->where($where)
            ->orderBy('id')
            ->where('user_type', '!=', AdmUserType::TEMP)
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
        return $this->hasMany(Vehicle::class, 'vehicle_manager', 'id');
    }

    public function SalesManagers(): HasMany
    {
        return $this->hasMany(Vehicle::class, 'vehicle_manager', 'id');
    }

    public function DriverManagers(): HasMany
    {
        return $this->hasMany(Vehicle::class, 'vehicle_manager', 'id');
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime:Y-m-d H:i:s',
            'password'          => 'hashed',
            'expires_at'        => 'datetime:Y-m-d H:i:s',
            'user_type'         => AdmUserType::class,
            'team_limit'        => AdmTeamLimit::class,
        ];
    }

    protected function teamLimitLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('team_limit')?->label
        );
    }

    protected function teamIds(): Attribute
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

    protected function expiresStatus(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->expires_at && $this->expires_at->lt(\Carbon\Carbon::now())
        );
    }

    protected function expiresStatusLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->expiresStatus ? '过期' : '正常'
        );
    }
}
