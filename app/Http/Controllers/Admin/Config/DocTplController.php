<?php

namespace App\Http\Controllers\Admin\Config;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Sale\DtExportType;
use App\Enum\Sale\DtFileType;
use App\Enum\Sale\DtStatus;
use App\Enum\Sale\DtType;
use App\Enum\Sale\DtTypeMacroChars;
use App\Http\Controllers\Controller;
use App\Models\Sale\DocTpl;
use App\Services\DocTplService;
use App\Services\PaginateService;
use App\Services\Uploader;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('文档模板')]
class DocTplController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
            DtType::labelOptions(),
            DtFileType::labelOptions(),
            DtStatus::labelOptions(),
        );
    }

    #[PermissionAction(PermissionAction::READ)]
    public function index(Request $request): Response
    {
        $this->options(true);

        $query = DocTpl::indexQuery();

        $paginate = new PaginateService(
            [],
            [['dt.dt_id', 'desc']],
            ['kw'],
            []
        );

        $paginate->paginator($query, $request, [
            'kw__func' => function ($value, Builder $builder) {
                $builder->where(function (Builder $builder) use ($value) {
                    $builder->where('dt.dt_name', 'like', '%'.$value.'%')->orWhere('dt.dt_remark', 'like', '%'.$value.'%');
                });
            },
        ]);

        return $this->response()->withData($paginate)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function create(Request $request): Response
    {
        $this->options();

        $docTpl = new DocTpl([
            'dt_status'    => DtStatus::ENABLED,
            'dt_file_type' => DtFileType::WORD,
        ]);

        $this->response()->withExtras(
            DtType::tryFrom(DtType::SALE_CONTRACT)->getFieldsAndRelations(true),
            DtType::tryFrom(DtType::SALE_SETTLEMENT)->getFieldsAndRelations(true),
            DtType::tryFrom(DtType::PAYMENT)->getFieldsAndRelations(true),
            DtType::tryFrom(DtType::VEHICLE_INSPECTION)->getFieldsAndRelations(true),
            DtStatus::options(),
        );

        return $this->response()->withData($docTpl)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function store(Request $request): Response
    {
        return $this->update($request, null);
    }

    #[PermissionAction(PermissionAction::READ)]
    public function show(DocTpl $docTpl): Response
    {
        $this->options();
        $this->response()->withExtras(
        );

        return $this->response()->withData($docTpl)->respond();
    }

    #[PermissionAction(PermissionAction::READ)]
    public function preview(Request $request, DocTpl $docTpl, DocTplService $docTplService)
    {
        $input = $request->validate([
            'dt_mode' => ['required', Rule::in(DtExportType::label_keys())],
        ]);

        $url = $docTplService->GenerateDoc($docTpl, $input['mode']);

        return $this->response()->withData($url)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function edit(DocTpl $docTpl): Response
    {
        $this->options();

        $this->response()->withExtras(
            DtType::tryFrom(DtType::SALE_CONTRACT)->getFieldsAndRelations(true),
            DtType::tryFrom(DtType::SALE_SETTLEMENT)->getFieldsAndRelations(true),
            DtType::tryFrom(DtType::PAYMENT)->getFieldsAndRelations(true),
            DtType::tryFrom(DtType::VEHICLE_INSPECTION)->getFieldsAndRelations(true),
            DtStatus::options(),
        );

        return $this->response()->withData($docTpl)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function update(Request $request, ?DocTpl $docTpl): Response
    {
        $validator = Validator::make(
            $request->all(),
            [
                'dt_type'      => ['bail', 'required', Rule::in(DtType::label_keys())],
                'dt_file_type' => ['bail', 'required', Rule::in(DtFileType::label_keys())],
                'dt_name'      => ['bail', 'required', 'max:255'],
                'dt_status'    => ['required', Rule::in(DtStatus::label_keys())],
                'dt_remark'    => ['bail', 'nullable', 'string', 'max:255'],
            ]
            + Uploader::validator_rule_upload_object('dt_file', true),
            [],
            trans_property(DocTpl::class)
        )
            ->after(function (\Illuminate\Validation\Validator $validator) {
                if ($validator->failed()) {
                    return;
                }
            })
        ;
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $validator->validated();

        DB::transaction(function () use (&$input, &$docTpl) {
            if (null === $docTpl) {
                /** @var DocTpl $docTpl */
                $docTpl = DocTpl::query()->create($input);
            } else {
                $docTpl->update($input);
            }
        });

        $docTpl->refresh();

        return $this->response()->withData($docTpl)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function destroy(DocTpl $docTpl): Response
    {
        $docTpl->delete();

        return $this->response()->withData($docTpl)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function status(Request $request, DocTpl $docTpl): Response
    {
        $validator = Validator::make(
            $request->all(),
            [
                'dt_status' => ['bail', 'required', Rule::in(DtStatus::label_keys())],
            ],
            [],
            trans_property(DocTpl::class)
        )
            ->after(function (\Illuminate\Validation\Validator $validator) {
                if ($validator->failed()) {
                    return;
                }
            })
        ;
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $validator->validated();

        $docTpl->update([
            'dt_status' => $input['dt_status'],
        ]);

        return $this->response()->withData($docTpl)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function upload(Request $request): Response
    {
        return Uploader::upload($request, 'doc_tpl', ['dt_file'], $this);
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
            DtType::options(),
            DtFileType::options(),
            DtStatus::options(),
            DtTypeMacroChars::kv(),
        );
    }
}
