<?php

namespace App\Http\Controllers\Admin\Admin;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Admin\ATeamLimit;
use App\Enum\Admin\AUserType;
use App\Http\Controllers\Controller;
use App\Http\Middleware\CheckAdminIsMock;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminRole;
use App\Models\Admin\AdminTeam;
use App\Models\Vehicle\Vehicle;
use App\Services\PaginateService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('员工')]
class AdminController extends Controller
{
    public function __construct()
    {
        $this->middleware(CheckAdminIsMock::class);
    }

    #[PermissionAction(PermissionAction::READ)]
    public function index(Request $request): Response
    {
        $this->options(true);
        $this->response()->withExtras();

        $query = Admin::query()
            // 排除临时账号，并追加车管数量统计。
            ->where('a_user_type', '!=', AUserType::TEMP)
            ->addSelect([
                'vehicle_manager_count' => Vehicle::query()->selectRaw('count(*)')->whereColumn('vehicles.ve_vehicle_manager', 'admins.id'),
            ])
            ->with('roles')
        ;

        $paginate = new PaginateService(
            [],
            [['id', 'asc']],
            ['kw'],
            []
        );

        $paginate->paginator($query, $request, [
            'kw__func' => function ($value, Builder $builder) {
                $builder->where(function (Builder $builder) use ($value) {
                    $builder->where('name', 'like', '%'.$value.'%')->orWhere('email', 'like', '%'.$value.'%');
                });
            },
        ]);

        return $this->response()->withData($paginate)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function create(Request $request): Response
    {
        $this->options();
        $this->response()->withExtras(
            AdminRole::options(),
            AdminTeam::options(),
        );

        $admin = new Admin([
            'a_team_limit' => ATeamLimit::NOT_LIMITED,
        ]);

        return $this->response()->withData($admin)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function edit(Request $request, Admin $admin): Response
    {
        // 超级管理员不可被编辑。
        abort_if($admin->hasRole(config('setting.super_role.name')), 404, 'super_admin not allow edit.');

        $this->options();
        $this->response()->withExtras(
            AdminRole::options(),
            AdminTeam::options(),
        );

        $admin->roles_ = $admin->roles->pluck('id');

        return $this->response()->withData($admin)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function store(Request $request): Response
    {
        $input = Validator::make(
            $request->all(),
            [
                'name'                  => ['bail', 'required', 'string', 'max:255'],
                'a_wecom_name'          => ['bail', 'nullable', 'string', 'max:255'],
                'email'                 => ['bail', 'nullable', 'string', 'email', 'max:255', Rule::unique(Admin::class, 'email')],
                'password'              => ['bail', 'required', 'string', 'min:8', 'confirmed'],
                'password_confirmation' => ['bail', 'required', 'string', 'min:8'],
                'roles_'                => ['bail', 'required'],
                'a_expires_at'          => ['bail', 'nullable', 'date'],
                'a_team_limit'          => ['bail', 'required', Rule::in(ATeamLimit::label_keys())],
                'a_team_ids'            => [
                    'bail',
                    Rule::excludeIf(ATeamLimit::LIMITED !== (int) $request->a_team_limit),
                    Rule::when(fn ($input) => ATeamLimit::LIMITED === (int) $input->a_team_limit, 'required', 'nullable'),
                    'array',
                ],
                'a_team_ids.*' => ['bail', 'integer', Rule::exists(AdminTeam::class, 'at_id')],
            ],
            [],
            trans_property(Admin::class)
        )
            ->validate()
        ;

        if (null === $input['password']) {
            unset($input['password'], $input['password_verified_at']);
        }

        $input['a_user_type'] = (function () use ($input) {
            // 演示账号在开启 MOCK 时标记为体验用户。
            if (config('setting.mock.enable') && Str::startsWith($input['name'], '演示')) {
                return AUserType::MOCK;
            }

            return AUserType::COMMON;
        })();

        DB::transaction(function () use (&$input, &$admin) {
            /** @var Admin $admin */
            $admin = Admin::query()->create($input);

            $admin->assignRole($input['roles_'] ?? []);
        });

        $this->response()->withMessages(message_success(__METHOD__));

        return $this->response()->withRedirect(redirect()->route('admins.index'))->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function update(Request $request, Admin $admin): Response
    {
        $input = Validator::make(
            $request->all(),
            [
                'name'                  => ['bail', 'required', 'string', 'max:255'],
                'a_wecom_name'          => ['bail', 'nullable', 'string', 'max:255'],
                'email'                 => ['bail', 'nullable', 'string', 'email', 'max:255', Rule::unique(Admin::class)->ignore($admin)],
                'roles_'                => ['bail', 'nullable'],
                'password'              => ['bail', 'nullable', 'required_with:password_confirmation', 'string', 'min:8', 'confirmed'],
                'password_confirmation' => ['bail', 'nullable', 'required_with:password', 'string', 'min:8'],
                'a_expires_at'          => ['bail', 'nullable', 'date'],
                'a_team_limit'          => ['bail', 'required', Rule::in(ATeamLimit::label_keys())],
                'a_team_ids'            => [
                    'bail',
                    Rule::excludeIf(ATeamLimit::LIMITED !== (int) $request->input('a_team_limit')),
                    Rule::when(fn ($input) => ATeamLimit::LIMITED === (int) $input->a_team_limit, 'required', 'nullable'),
                    'array',
                ],
                'a_team_ids.*' => ['bail', 'integer', Rule::exists(AdminTeam::class, 'at_id')],
            ],
            [],
            trans_property(Admin::class)
        )
            ->validate()
        ;

        if (array_key_exists('password', $input) && null === $input['password']) {
            unset($input['password'], $input['password_verified_at']);
        }

        $input['a_user_type'] = (function () use ($input) {
            // 演示账号在开启 MOCK 时标记为体验用户。
            if (config('setting.mock.enable') && Str::startsWith($input['name'], '演示')) {
                return AUserType::MOCK;
            }

            return AUserType::COMMON;
        })();

        DB::transaction(function () use (&$input, &$admin) {
            $admin->update($input);

            $roles_ = $input['roles_'] ?? [];
            $admin->syncRoles($roles_);
            unset($admin->roles);
        });

        $this->response()->withMessages(message_success(__METHOD__));

        return $this->response()->withRedirect(redirect()->route('admins.index'))->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function destroy(Admin $admin): Response
    {
        // 超级管理员不可删除。
        abort_if($admin->hasRole(config('setting.super_role.name')), 404, 'super_admin not allow destroy.');

        DB::transaction(function () use (&$admin) {
            $admin->delete();
            DB::table(config('permission.table_names.model_has_roles'))->where('model_id', $admin->id)->delete();
            DB::table(config('permission.table_names.model_has_permissions'))->where('model_id', $admin->id)->delete();
        });

        $this->response()->withMessages(message_success(__METHOD__));

        return $this->response()->withRedirect(redirect()->route('admins.index'))->respond();
    }

    #[PermissionAction(PermissionAction::READ)]
    public function show(Admin $admin): Response
    {
        // 超级管理员不可查看编辑页。
        abort_if($admin->hasRole(config('setting.super_role.name')), 404, 'super_admin not allow edit.');

        return $this->response()->withData($admin)->respond();
    }

    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
            ATeamLimit::labelOptions(),
        );
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
            ATeamLimit::options(),
        );
    }
}
