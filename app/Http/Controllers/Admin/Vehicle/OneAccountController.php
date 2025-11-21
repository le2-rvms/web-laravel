<?php

namespace App\Http\Controllers\Admin\Vehicle;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\One\OaOaProvince;
use App\Enum\One\OaOaType;
use App\Http\Controllers\Controller;
use App\Models\One\OneAccount;
use App\Services\PaginateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('122账号管理')]
class OneAccountController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
            OaOaType::labelOptions(),
        );
    }

    #[PermissionAction(PermissionAction::ADD)]
    public function create(): Response
    {
        $this->options();
        $this->response()->withExtras(
            OaOaProvince::options(),
        );

        return $this->response()->respond();
    }

    #[PermissionAction(PermissionAction::INDEX)]
    public function index(Request $request): Response
    {
        $this->options(true);
        $this->response()->withExtras(
        );

        $query = OneAccount::indexQuery();

        $paginate = new PaginateService(
            [],
            [['oa.oa_id', 'desc']],
            [],
            []
        );

        $paginate->paginator($query, $request, []);

        return $this->response()->withData($paginate)->respond();
    }

    #[PermissionAction(PermissionAction::ADD)]
    public function store(Request $request): Response
    {
        return $this->update($request, null);
    }

    #[PermissionAction(PermissionAction::SHOW)]
    public function show(OneAccount $oneAccount): Response
    {
        return $this->response()->withData($oneAccount)->respond();
    }

    #[PermissionAction(PermissionAction::EDIT)]
    public function edit(OneAccount $oneAccount): Response
    {
        $this->options();
        $this->response()->withExtras(
            OaOaProvince::options(),
        );

        return $this->response()->withData($oneAccount)->respond();
    }

    #[PermissionAction(PermissionAction::EDIT)]
    public function update(Request $request, ?OneAccount $oneAccount): Response
    {
        $validator = Validator::make(
            $request->all(),
            [
                'oa_type'       => ['required', Rule::in(OaOaType::label_keys())],
                'oa_name'       => ['required', 'string', 'max:255', Rule::unique(OneAccount::class)->ignore($oneAccount)],
                'oa_province'   => ['required', 'string', Rule::in(OaOaProvince::getKeys())],
                'cookie_string' => ['nullable', 'string'],
            ],
            [],
            trans_property(OneAccount::class)
        )
            ->after(function (\Illuminate\Validation\Validator $validator) use (&$vehicle) {
                if (!$validator->failed()) {
                }
            })
        ;

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $validator->validated();
        if (null === $oneAccount) {
            $oneAccount = OneAccount::query()->create($input);
        } else {
            $input['cookie_refresh_at'] = null;
            $oneAccount->update($input);
        }

        return $this->response()->withData($oneAccount)->respond();
    }

    #[PermissionAction(PermissionAction::DELETE)]
    public function destroy(OneAccount $oneAccount): Response
    {
        $oneAccount->delete();

        return $this->response()->withData($oneAccount)->respond();
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
            OaOaType::options(),
            ['how_cookie_url' => config('setting.host_manual').'/config/122']
        );
    }
}
