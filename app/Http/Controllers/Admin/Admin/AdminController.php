<?php

namespace App\Http\Controllers\Admin\Admin;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Admin\AdmTeamLimit;
use App\Enum\Admin\AdmUserType;
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
use Illuminate\Validation\ValidationException;
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
            ->where('user_type', '!=', AdmUserType::TEMP)
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
            'team_limit' => AdmTeamLimit::NOT_LIMITED,
        ]);

        return $this->response()->withData($admin)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function edit(Request $request, Admin $admin): Response
    {
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
        $validator = Validator::make(
            $request->all(),
            [
                'name'                  => ['bail', 'required', 'string', 'max:255'],
                'wecom_name'            => ['bail', 'nullable', 'string', 'max:255'],
                'email'                 => ['bail', 'nullable', 'string', 'email', 'max:255', Rule::unique(Admin::class, 'email')],
                'password'              => ['bail', 'required', 'string', 'min:8', 'confirmed'],
                'password_confirmation' => ['bail', 'required', 'string', 'min:8'],
                'roles_'                => ['bail', 'required'],
                'expires_at'            => ['bail', 'nullable', 'date'],
                'team_limit'            => ['bail', 'required', Rule::in(AdmTeamLimit::label_keys())],
                'team_ids'              => [
                    'bail',
                    Rule::excludeIf(AdmTeamLimit::LIMITED !== (int) $request->team_limit),
                    Rule::when(fn ($input) => AdmTeamLimit::LIMITED === (int) $input->team_limit, 'required', 'nullable'),
                    'array',
                ],
                'team_ids.*' => ['bail', 'integer', Rule::exists(AdminTeam::class, 'at_id')],
            ],
            [],
            trans_property(Admin::class)
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $validator->validated();

        if (null === $input['password']) {
            unset($input['password'], $input['password_verified_at']);
        }

        $input['user_type'] = (function () use ($input) {
            if (config('setting.mock.enable') && Str::startsWith($input['name'], '演示')) {
                return AdmUserType::MOCK;
            }

            return AdmUserType::COMMON;
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
        $validator = Validator::make(
            $request->all(),
            [
                'name'                  => ['bail', 'required', 'string', 'max:255'],
                'wecom_name'            => ['bail', 'nullable', 'string', 'max:255'],
                'email'                 => ['bail', 'nullable', 'string', 'email', 'max:255', Rule::unique(Admin::class)->ignore($admin)],
                'roles_'                => ['bail', 'nullable'],
                'password'              => ['bail', 'nullable', 'required_with:password_confirmation', 'string', 'min:8', 'confirmed'],
                'password_confirmation' => ['bail', 'nullable', 'required_with:password', 'string', 'min:8'],
                'expires_at'            => ['bail', 'nullable', 'date'],
                'team_limit'            => ['bail', 'required', Rule::in(AdmTeamLimit::label_keys())],
                'team_ids'              => [
                    'bail',
                    Rule::excludeIf(AdmTeamLimit::LIMITED !== (int) $request->team_limit),
                    Rule::when(fn ($input) => AdmTeamLimit::LIMITED === (int) $input->team_limit, 'required', 'nullable'),
                    'array',
                ],
                'team_ids.*' => ['bail', 'integer', Rule::exists(AdminTeam::class, 'at_id')],
            ],
            [],
            trans_property(Admin::class)
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $validator->validated();

        if (array_key_exists('password', $input) && null === $input['password']) {
            unset($input['password'], $input['password_verified_at']);
        }

        $input['user_type'] = (function () use ($input) {
            if (config('setting.mock.enable') && Str::startsWith($input['name'], '演示')) {
                return AdmUserType::MOCK;
            }

            return AdmUserType::COMMON;
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
        abort_if($admin->hasRole(config('setting.super_role.name')), 404, 'super_admin not allow edit.');

        return $this->response()->withData($admin)->respond();
    }

    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
            AdmTeamLimit::labelOptions(),
        );
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
            AdmTeamLimit::options(),
        );
    }
}
