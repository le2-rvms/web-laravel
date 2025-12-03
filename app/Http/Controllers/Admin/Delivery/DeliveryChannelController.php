<?php

namespace App\Http\Controllers\Admin\Delivery;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Delivery\DcDcKey;
use App\Enum\Delivery\DcDcKeyDefault;
use App\Enum\Delivery\DcDcProvider;
use App\Enum\Delivery\DcDcStatus;
use App\Http\Controllers\Controller;
use App\Models\Delivery\DeliveryChannel;
use App\Services\PaginateService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('消息类型管理')]
class DeliveryChannelController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
            DcDcKey::labelOptions(),
            DcDcProvider::labelOptions(),
            DcDcStatus::labelOptions(),
        );
    }

    #[PermissionAction(PermissionAction::READ)]
    public function index(Request $request): Response
    {
        $this->options(true);

        $query = DeliveryChannel::indexQuery();

        $paginate = new PaginateService(
            [],
            [['dc.dc_id', 'desc']],
            ['kw'],
            []
        );

        $paginate->paginator($query, $request, [
            'kw__func' => function ($value, Builder $builder) {
                $builder->where(function (Builder $builder) use ($value) {
                    $builder->where('dc.dc_title', 'like', '%'.$value.'%');
                });
            },
        ]);

        return $this->response()->withData($paginate)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function create(Request $request): Response
    {
        $this->options();

        $deliveryChannel = new DeliveryChannel([
            'dc_status' => DcDcStatus::ENABLED,
        ]);

        $this->response()->withExtras(
            DcDcKeyDefault::kv(),
        );

        return $this->response()->withData($deliveryChannel)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function store(Request $request): Response
    {
        return $this->update($request, null);
    }

    #[PermissionAction(PermissionAction::READ)]
    public function show(DeliveryChannel $deliveryChannel): Response
    {
        $this->options();
        $this->response()->withExtras(
        );

        return $this->response()->withData($deliveryChannel)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function edit(DeliveryChannel $deliveryChannel): Response
    {
        $this->options();

        return $this->response()->withData($deliveryChannel)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function update(Request $request, ?DeliveryChannel $deliveryChannel): Response
    {
        $validator = Validator::make(
            $request->all(),
            [
                'dc_key'      => ['bail', 'required', Rule::in(DcDcKey::label_keys())],
                'dc_title'    => ['bail', 'required', 'max:255'],
                'dc_template' => ['bail', 'required', 'max:2550'],
                'dc_tn'       => ['bail', 'required', 'integer'],
                'dc_provider' => ['bail', 'required', Rule::in(DcDcProvider::label_keys())],
                'dc_status'   => ['bail', 'required', Rule::in(DcDcStatus::label_keys())],
            ],
            [],
            trans_property(DeliveryChannel::class)
        )
            ->after(function (\Illuminate\Validation\Validator $validator) {
                if (!$validator->failed()) {
                }
            })
        ;
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $validator->validated();

        DB::transaction(function () use (&$input, &$deliveryChannel) {
            if (null === $deliveryChannel) {
                /** @var DeliveryChannel $deliveryChannel */
                $deliveryChannel = DeliveryChannel::query()->create($input);
            } else {
                $deliveryChannel->update($input);
            }
        });

        $deliveryChannel->refresh();

        return $this->response()->withData($deliveryChannel)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function destroy(DeliveryChannel $deliveryChannel): Response
    {
        $deliveryChannel->delete();

        return $this->response()->withData($deliveryChannel)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function status(Request $request, DeliveryChannel $deliveryChannel): Response
    {
        $validator = Validator::make(
            $request->all(),
            [
                'dc_status' => ['bail', 'required', Rule::in(DcDcStatus::label_keys())],
            ],
            [],
            trans_property(DeliveryChannel::class)
        )
            ->after(function (\Illuminate\Validation\Validator $validator) {
                if (!$validator->failed()) {
                }
            })
        ;
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $validator->validated();

        $deliveryChannel->update($input);

        return $this->response()->withData($deliveryChannel)->respond();
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
            DcDcKey::options(),
            DcDcProvider::options(),
            DcDcStatus::options(),
        );
    }
}
