<?php

namespace App\Http\Controllers\Admin\Admin;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Http\Controllers\Controller;
use App\Http\Middleware\CheckAdminIsMock;
use App\Models\Admin\AdminPermission;
use App\Services\PaginateService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('员工权限')]
class AdminPermissionController extends Controller
{
    public function __construct()
    {
        $this->middleware(CheckAdminIsMock::class);
    }

    #[PermissionAction(PermissionAction::READ)]
    public function index(Request $request)
    {
        $this->options(true);
        $this->response()->withExtras();

        // 预加载角色关联，避免列表页 N+1。
        $query = AdminPermission::query()
            ->orderBy('name')
            ->with('roles')
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
                    // 支持按权限名/标题模糊查询。
                    $builder->where('name', 'like', '%'.$value.'%')
                        ->orWhere('title', 'like', '%'.$value.'%')
                    ;
                });
            },
        ]);

        return $this->response()->withData($paginate)->respond();
    }

    public function store(Request $request): Response
    {
        $input = Validator::make(
            $request->all(),
            [
                'name'  => ['required', Rule::unique(AdminPermission::class, 'name')],
                'title' => ['nullable'],
            ],
            [],
            trans_property(AdminPermission::class)
        )
            ->validate()
        ;

        DB::transaction(function () use (&$input, &$admin_permission) {
            $admin_permission = AdminPermission::query()->create($input);
        });

        $this->response()->withMessages(message_success(__METHOD__));

        return $this->response()->withRedirect(redirect()->route('permissions.index'))->respond();
    }

    public function update(Request $request, AdminPermission $admin_permission): Response
    {
        $input = Validator::make(
            $request->all(),
            [
                'name'  => ['required', Rule::unique(AdminPermission::class, 'name')->ignore($admin_permission)],
                'title' => ['nullable'],
            ],
            [],
            trans_property(AdminPermission::class)
        )
            ->validate()
        ;

        DB::transaction(function () use (&$input, &$admin_permission) {
            $admin_permission->fill($input);

            $admin_permission->save();
        });

        $this->response()->withMessages(message_success(__METHOD__));

        return $this->response()->withRedirect(redirect()->route('permissions.index'))->respond();
    }

    public function destroy(AdminPermission $admin_permission): Response
    {
        DB::transaction(function () use (&$admin_permission) {
            // 删除权限前清理关联表，避免残留关系。
            DB::table('model_has_permissions')->where('permission_id', $admin_permission->id)->delete();
            DB::table('role_has_permissions')->where('permission_id', $admin_permission->id)->delete();
            $admin_permission->delete();
        });

        $this->response()->withMessages(message_success(__METHOD__));

        return $this->response()->withRedirect(redirect()->back())->respond();
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
