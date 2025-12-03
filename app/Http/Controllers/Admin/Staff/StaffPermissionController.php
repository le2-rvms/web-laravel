<?php

namespace App\Http\Controllers\Admin\Staff;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Http\Controllers\Controller;
use App\Http\Middleware\CheckAdminIsMock;
use App\Models\Admin\StaffPermission;
use App\Services\PaginateService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('员工权限管理')]
class StaffPermissionController extends Controller
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

        $query = StaffPermission::query()
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
        $validator = Validator::make(
            $request->all(),
            [
                'name'  => ['required', Rule::unique(StaffPermission::class, 'name')],
                'title' => ['nullable'],
            ],
            [],
            trans_property(StaffPermission::class)
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $validator->validated();

        DB::transaction(function () use (&$input, &$staff_permission) {
            $staff_permission = StaffPermission::query()->create($input);
        });

        $this->response()->withMessages(message_success(__METHOD__));

        return $this->response()->withRedirect(redirect()->route('permissions.index'))->respond();
    }

    public function update(Request $request, StaffPermission $staff_permission): Response
    {
        $validator = Validator::make(
            $request->all(),
            [
                'name'  => ['required', Rule::unique(StaffPermission::class, 'name')->ignore($staff_permission)],
                'title' => ['nullable'],
            ],
            [],
            trans_property(StaffPermission::class)
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $validator->validated();

        DB::transaction(function () use (&$input, &$staff_permission) {
            $staff_permission->fill($input);

            $staff_permission->save();
        });

        $this->response()->withMessages(message_success(__METHOD__));

        return $this->response()->withRedirect(redirect()->route('permissions.index'))->respond();
    }

    public function destroy(StaffPermission $staff_permission): Response
    {
        DB::transaction(function () use (&$staff_permission) {
            DB::table('model_has_permissions')->where('permission_id', $staff_permission->id)->delete();
            DB::table('role_has_permissions')->where('permission_id', $staff_permission->id)->delete();
            $staff_permission->delete();
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
