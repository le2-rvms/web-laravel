<?php

namespace App\Http\Controllers\Admin\Admin;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Admin\AtStatus;
use App\Http\Controllers\Controller;
use App\Models\Admin\AdminTeam;
use App\Services\PaginateService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('车队')]
class AdminTeamController extends Controller
{
    #[PermissionAction(PermissionAction::READ)]
    public function index(Request $request): Response
    {
        $this->options(true);
        $this->response()->withExtras();

        $query = AdminTeam::indexQuery();

        $paginate = new PaginateService(
            [],
            [['at_id', 'asc']],
            ['kw'],
            []
        );

        $paginate->paginator($query, $request, [
            'kw__func' => function ($value, Builder $builder) {
                $builder->where(function (Builder $builder) use ($value) {
                    // 支持按名称或备注检索车队。
                    $builder->where('at_name', 'ilike', '%'.$value.'%')->orWhere('at_remark', 'ilike', '%'.$value.'%');
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
        );

        $adminTeam = new AdminTeam([
            // 默认启用车队。
            'at_status' => AtStatus::ENABLED,
        ]);

        return $this->response()->withData($adminTeam)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function edit(Request $request, AdminTeam $adminTeam): Response
    {
        $this->options();
        $this->response()->withExtras(
        );

        return $this->response()->withData($adminTeam)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function store(Request $request): Response
    {
        $input = Validator::make(
            $request->all(),
            [
                'at_parent_id' => ['bail', 'nullable', 'int', Rule::unique(AdminTeam::class, 'at_id')],
                'at_name'      => ['bail', 'required', 'string', 'max:255', Rule::unique(AdminTeam::class, 'at_name')],
                'at_status'    => ['bail', 'required', Rule::in(AtStatus::label_keys())],
                'at_sort'      => ['bail', 'nullable', 'int'],
                'at_remark'    => ['bail', 'nullable', 'string', 'max:255'],
            ],
            [],
            trans_property(AdminTeam::class)
        )
            ->validate()
        ;

        DB::transaction(function () use (&$input, &$adminTeam) {
            /** @var AdminTeam $adminTeam */
            $adminTeam = AdminTeam::query()->create($input);
        });

        $this->response()->withMessages(message_success(__METHOD__));

        return $this->response()->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function update(Request $request, AdminTeam $adminTeam): Response
    {
        $input = Validator::make(
            $request->all(),
            [
                'at_parent_id' => ['bail', 'nullable', 'int', Rule::unique(AdminTeam::class, 'at_id')],
                'at_name'      => ['bail', 'required', 'string', 'max:255', Rule::unique(AdminTeam::class, 'at_name')->ignore($adminTeam)],
                'at_status'    => ['bail', 'required', Rule::in(AtStatus::label_keys())],
                'at_sort'      => ['bail', 'nullable', 'int'],
                'at_remark'    => ['bail', 'nullable', 'string', 'max:255'],
            ],
            [],
            trans_property(AdminTeam::class)
        )
            ->validate()
        ;

        DB::transaction(function () use (&$input, &$adminTeam) {
            $adminTeam->update($input);
        });

        $this->response()->withMessages(message_success(__METHOD__));

        return $this->response()->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function destroy(AdminTeam $adminTeam): Response
    {
        DB::transaction(function () use (&$adminTeam) {
            $adminTeam->delete();
        });

        $this->response()->withMessages(message_success(__METHOD__));

        return $this->response()->respond();
    }

    #[PermissionAction(PermissionAction::READ)]
    public function show(AdminTeam $adminTeam): Response
    {
        return $this->response()->withData($adminTeam)->respond();
    }

    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
            AtStatus::labelOptions()
        );
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
            AtStatus::options()
        );
    }
}
