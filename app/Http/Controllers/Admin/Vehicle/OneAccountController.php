<?php

namespace App\Http\Controllers\Admin\Vehicle;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\One\OaIsSyncRentalContract;
use App\Enum\One\OaProvince;
use App\Enum\One\OaType;
use App\Http\Controllers\Controller;
use App\Models\One\OneAccount;
use App\Services\PaginateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('122账号')]
class OneAccountController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
            OaType::labelOptions(),
            OaIsSyncRentalContract::labelOptions(),
        );
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function create(): Response
    {
        $this->options();
        $this->response()->withExtras(
            OaProvince::options(),
        );

        return $this->response()->respond();
    }

    #[PermissionAction(PermissionAction::READ)]
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

    #[PermissionAction(PermissionAction::WRITE)]
    public function store(Request $request): Response
    {
        return $this->update($request, null);
    }

    #[PermissionAction(PermissionAction::READ)]
    public function show(OneAccount $oneAccount): Response
    {
        return $this->response()->withData($oneAccount)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function edit(OneAccount $oneAccount): Response
    {
        $this->options();
        $this->response()->withExtras(
            OaProvince::options(),
        );

        return $this->response()->withData($oneAccount)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function update(Request $request, ?OneAccount $oneAccount): Response
    {
        $input = Validator::make(
            $request->all(),
            [
                'oa_type'                    => ['required', Rule::in(OaType::label_keys())],
                'oa_name'                    => ['required', 'string', 'max:255', Rule::unique(OneAccount::class)->ignore($oneAccount)],
                'oa_province'                => ['required', 'string', Rule::in(OaProvince::getKeys())],
                'oa_cookie_string'           => ['nullable', 'string'],
                'oa_is_sync_rental_contract' => ['nullable', Rule::in(OaIsSyncRentalContract::label_keys())],
            ],
            [],
            trans_property(OneAccount::class)
        )
            ->after(function (\Illuminate\Validation\Validator $validator) use (&$vehicle) {
                if ($validator->failed()) {
                    return;
                }
            })
            ->validate()
        ;

        if (null === $oneAccount) {
            $oneAccount = OneAccount::query()->create($input);
        } else {
            $input['oa_cookie_refresh_at'] = null;
            $oneAccount->update($input);
        }

        return $this->response()->withData($oneAccount)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function destroy(OneAccount $oneAccount): Response
    {
        $oneAccount->delete();

        return $this->response()->withData($oneAccount)->respond();
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
            OaType::options(),
            OaIsSyncRentalContract::options(),
            //            ['how_cookie_url' => config('setting.host_manual').'/config/122']
        );
    }
}
