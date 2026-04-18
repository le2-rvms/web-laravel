<?php

namespace App\Http\Controllers\Admin\Payment;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Payment\PaStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment\PaymentAccount;
use App\Services\PaginateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('收付款账号')]
class PaymentAccountController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
        );
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function create(): Response
    {
        $this->options();
        $this->response()->withExtras(
        );

        $paymentAccount = new PaymentAccount([
            // New payment accounts start enabled by default.
            'pa_status' => PaStatus::ENABLED,
        ]);

        return $this->response()->withData($paymentAccount)->respond();
    }

    #[PermissionAction(PermissionAction::READ)]
    public function index(Request $request): Response
    {
        $this->options(true);
        $this->response()->withExtras(
        );

        $query = PaymentAccount::indexQuery();

        $paginate = new PaginateService(
            [],
            [],
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
    public function show(PaymentAccount $paymentAccount): Response
    {
        $this->options();

        return $this->response()->withData($paymentAccount)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function edit(PaymentAccount $paymentAccount): Response
    {
        $this->options();

        return $this->response()->withData($paymentAccount)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function update(Request $request, ?PaymentAccount $paymentAccount): Response
    {
        $input = Validator::make(
            $request->all(),
            [
                'pa_name'   => ['required', 'string', 'max:255', Rule::unique(PaymentAccount::class)->ignore($paymentAccount)],
                'pa_status' => ['required', Rule::in(PaStatus::label_keys())],
                'pa_remark' => ['nullable', 'string'],
            ],
            [],
            trans_property(PaymentAccount::class)
        )
            ->after(function (\Illuminate\Validation\Validator $validator) use (&$vehicle, &$customer) {
                if ($validator->failed()) {
                    return;
                }
            })->validate()
        ;

        // Use the same validation flow for create and update.
        if (null === $paymentAccount) {
            $paymentAccount = PaymentAccount::query()->create($input);
        } else {
            $paymentAccount->update($input);
        }

        return $this->response()->withData($paymentAccount)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function destroy(PaymentAccount $paymentAccount): Response
    {
        $paymentAccount->delete();

        return $this->response()->withData($paymentAccount)->respond();
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
            PaStatus::options(),
        );
    }
}
