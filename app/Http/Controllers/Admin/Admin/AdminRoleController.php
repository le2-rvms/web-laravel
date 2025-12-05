<?php

namespace App\Http\Controllers\Admin\Admin;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Admin\ArIsCustom;
use App\Http\Controllers\Controller;
use App\Http\Middleware\CheckAdminIsMock;
use App\Models\Admin\AdminPermission;
use App\Models\Admin\AdminRole;
use App\Services\PaginateService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('员工角色')]
class AdminRoleController extends Controller
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

        $query = AdminRole::query()
            ->where('name', '!=', config('setting.super_role.name'))
            ->with('permissions')
        ;

        $paginate = new PaginateService(
            [],
            [['name', 'asc']],
            ['kw'],
            []
        );

        $paginate->paginator($query, $request, [
            'kw__func' => function ($value, Builder $builder) {
                $builder->where(function (Builder $builder) use ($value) {
                    $builder->where('name', 'like', '%'.$value.'%');
                });
            },
        ]);

        return $this->response()->withData($paginate)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function create(Request $request): Response
    {
        $this->response()->withExtras(
            AdminPermission::options(),
        );

        return $this->response()->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function store(Request $request): Response
    {
        $validator = Validator::make(
            $request->all(),
            [
                'name'         => ['required', Rule::unique(AdminRole::class, 'name')],
                '_permissions' => ['nullable'],
                'title'        => ['nullable'],
            ],
            [],
            trans_property(AdminRole::class)
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $validator->validated();

        DB::transaction(function () use (&$input) {
            $admin_role = AdminRole::create($input + ['guard_name' => 'web', 'is_custom' => ArIsCustom::YES]);

            $permissions = $input['_permissions'] ?? [];
            if ($permissions) {
                foreach ($permissions as $item) {
                    $admin_role->givePermissionTo($item);
                }
            }
        });

        $this->response()->withMessages(message_success(__METHOD__));

        return $this->response()->withRedirect(redirect()->route('roles.index'))->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function edit(Request $request, AdminRole $admin_role): Response
    {
        abort_if(config('setting.super_role.name') == $admin_role->name, 403, 'You Cannot Edit Super Admin Role!');

        $this->response()->withExtras(
            AdminPermission::options(),
        );

        $permissions = $admin_role->permissions;

        $admin_role->_permissions = $permissions->pluck('name')->toArray();

        $groupedPermissions = $permissions->groupBy(fn ($row) => $row->group_name)
            ->map(
                fn ($group) => $group->pluck('name')->values()
            )
            ->toArray()
        ;

        $admin_role->_group_permissions = (object) $groupedPermissions;

        return $this->response()->withData($admin_role)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function update(Request $request, AdminRole $admin_role): Response
    {
        $validator = Validator::make(
            $request->all(),
            [
                'name'         => ['required', Rule::unique(AdminRole::class, 'name')->ignore($admin_role)],
                '_permissions' => ['nullable'],
                'title'        => ['nullable'],
            ],
            [],
            trans_property(AdminRole::class)
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $validator->validated();

        abort_if(config('setting.super_role.name') == $admin_role->name, 403, 'You Cannot Edit Super Admin Role!');

        DB::transaction(function () use (&$input, &$admin_role) {
            $permissions = $input['_permissions'] ?? [];

            $admin_role->fill($input);
            $admin_role->syncPermissions($permissions);
            $admin_role->save();
        });

        $this->response()->withMessages(message_success(__METHOD__));

        return $this->response()->withRedirect(redirect()->route('roles.index'))->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function destroy(AdminRole $admin_role): Response
    {
        abort_if(config('setting.super_role.name') == $admin_role->name, 403, 'You Cannot delete Super Admin Role!');

        DB::transaction(function () use (&$admin_role) {
            DB::table(config('permission.table_names.model_has_roles'))->where('role_id', $admin_role->id)->delete();
            DB::table(config('permission.table_names.role_has_permissions'))->where('role_id', $admin_role->id)->delete();
            $admin_role->delete();
        });

        $this->response()->withMessages(message_success(__METHOD__));

        return $this->response()->withRedirect(redirect()->route('roles.index'))->respond();
    }

    #[PermissionAction(PermissionAction::READ)]
    public function show(Request $request, AdminRole $admin_role): Response
    {
        abort_if(config('setting.super_role.name') == $admin_role->name, 403, 'You Cannot Edit Super Admin Role!');

        $admin_role->_permissionsNames = $admin_role->getPermissionNames();

        return $this->response()->withData($admin_role)->respond();
    }

    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
        );
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
        );
    }
}
