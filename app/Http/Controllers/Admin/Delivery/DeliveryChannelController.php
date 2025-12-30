<?php

namespace App\Http\Controllers\Admin\Delivery;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Delivery\DcKey;
use App\Enum\Delivery\DcKeyDefault;
use App\Enum\Delivery\DcProvider;
use App\Enum\Delivery\DcStatus;
use App\Http\Controllers\Controller;
use App\Models\Delivery\DeliveryChannel;
use App\Services\PaginateService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('消息类型')]
class DeliveryChannelController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
            DcKey::labelOptions(),
            DcProvider::labelOptions(),
            DcStatus::labelOptions(),
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
                    // 按消息标题关键字过滤。
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
            'dc_status' => DcStatus::ENABLED,
        ]);

        $this->response()->withExtras(
            // 提供默认模板 key 以便快速创建。
            DcKeyDefault::kv(),
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
        $input = Validator::make(
            $request->all(),
            [
                'dc_key'      => ['bail', 'required', Rule::in(DcKey::label_keys())],
                'dc_title'    => ['bail', 'required', 'max:255'],
                'dc_template' => ['bail', 'required', 'max:2550'],
                'dc_tn'       => ['bail', 'required', 'integer'],
                'dc_provider' => ['bail', 'required', Rule::in(DcProvider::label_keys())],
                'dc_status'   => ['bail', 'required', Rule::in(DcStatus::label_keys())],
            ],
            [],
            trans_property(DeliveryChannel::class)
        )
            ->after(function (\Illuminate\Validation\Validator $validator) {
                if ($validator->failed()) {
                    return;
                }
            })
            ->validate()
        ;

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
        $input = Validator::make(
            $request->all(),
            [
                'dc_status' => ['bail', 'required', Rule::in(DcStatus::label_keys())],
            ],
            [],
            trans_property(DeliveryChannel::class)
        )
            ->after(function (\Illuminate\Validation\Validator $validator) {
                if ($validator->failed()) {
                    return;
                }
            })
            ->validate()
        ;

        $deliveryChannel->update($input);

        return $this->response()->withData($deliveryChannel)->respond();
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
            DcKey::options(),
            DcProvider::options(),
            DcStatus::options(),
        );
    }
}
