<?php

namespace App\Http\Controllers\Admin\Sale;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Booking\BoOrderStatus;
use App\Enum\Booking\BoPaymentStatus;
use App\Enum\Booking\BoProps;
use App\Enum\Booking\BoRefundStatus;
use App\Enum\Booking\BoSource;
use App\Enum\Booking\BoType;
use App\Enum\Booking\BvIsListed;
use App\Enum\Booking\BvProps;
use App\Enum\Vehicle\VeStatusService;
use App\Http\Controllers\Controller;
use App\Models\Customer\Customer;
use App\Models\Sale\BookingOrder;
use App\Models\Sale\BookingVehicle;
use App\Models\Vehicle\Vehicle;
use App\Services\PaginateService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('预定租车')]
class BookingOrderController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
        );
    }

    #[PermissionAction(PermissionAction::READ)]
    public function index(Request $request): Response
    {
        $this->options(true);
        $query = BookingOrder::indexQuery();

        $paginate = new PaginateService(
            [],
            [['bo.bo_id', 'desc']],
            ['kw'],
            []
        );

        $paginate->paginator($query, $request, [
            'kw__func' => function ($value, Builder $builder) {
                $builder->where(function (Builder $builder) use ($value) {
                    $builder->whereLike('bo.bo_no', '%'.$value.'%')->orWhereLike('bo.bo_plate_no', '%'.$value.'%')->orWhereLike('cu.cu_contact_name', '%'.$value.'%');
                });
            },
        ]);

        return $this->response()->withData($paginate)->respond();
    }

    #[PermissionAction(PermissionAction::READ)]
    public function show(BookingOrder $bookingOrder): Response
    {
        $bookingOrder->load(['Vehicle', 'Customer']);

        return $this->response()->withData($bookingOrder)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function create(Request $request): Response
    {
        // 新建订单默认未支付/未处理/未退款。
        $bookingOrder = new BookingOrder([
            'bo_no'             => '',
            'bo_source'         => BoSource::STORE,
            'bo_payment_status' => BoPaymentStatus::UNPAID,
            'bo_order_status'   => BoOrderStatus::UNPROCESSED,
            'bo_refund_status'  => BoRefundStatus::NOREFUND,
        ]);

        $this->options();
        $this->response()->withExtras(
            BookingVehicle::options(),
            Customer::options(),
        );

        return $this->response()->withData($bookingOrder)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function edit(BookingOrder $bookingOrder): Response
    {
        $bookingOrder->load('Vehicle', 'Customer');

        $this->options();

        return $this->response()->withData($bookingOrder)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function store(Request $request): Response
    {
        return $this->update($request, null);
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function update(Request $request, ?BookingOrder $bookingOrder): Response
    {
        $input = Validator::make(
            $request->all(),
            (null === $bookingOrder  // 新增的时候
                ? [
                    // 仅允许使用已上架的预定车辆下单。
                    'bo_bv_id'              => ['bail', 'required', 'integer', Rule::exists(BookingVehicle::class, 'bv_id')->where('bv_is_listed', BvIsListed::LISTED)],
                    'bo_no'                 => ['bail', 'required', 'string', 'max:64', Rule::unique(BookingOrder::class)->ignore($bookingOrder)],
                    'bo_source'             => ['bail', 'required', Rule::in(BoSource::label_keys())],
                    'bo_cu_id'              => ['bail', 'required', 'integer', Rule::exists(Customer::class, 'cu_id')],
                    'bo_plate_no'           => ['bail', 'required', 'string', Rule::exists(Vehicle::class, 've_plate_no')->where('ve_status_service', VeStatusService::YES)],
                    'bo_type'               => ['bail', 'required', 'string', Rule::in(BoType::label_keys())],
                    'bo_pickup_date'        => ['bail', 'required', 'date_format:Y-m-d'],
                    'bo_rent_per_amount'    => ['bail', 'required', 'decimal:0,2', 'gte:0'],
                    'bo_deposit_amount'     => ['bail', 'required', 'decimal:0,2', 'gte:0'],
                    'bo_props'              => ['bail', 'nullable', 'array'],
                    'bo_props.*'            => ['bail', 'string', Rule::in(array_keys(BvProps::kv))],
                    'bo_registration_date'  => ['bail', 'required', 'date_format:Y-m-d'],
                    'bo_mileage'            => ['bail', 'nullable', 'integer', 'min:0'],
                    'bo_service_interval'   => ['bail', 'nullable', 'integer', 'min:0'],
                    'bo_min_rental_periods' => ['bail', 'required', 'integer', 'min:0'],
                ] : [])
            + [
                'bo_payment_status' => ['bail', 'required', Rule::in(BoPaymentStatus::label_keys())],
                'bo_order_status'   => ['bail', 'required', Rule::in(BoOrderStatus::label_keys())],
                'bo_refund_status'  => ['bail', 'required', Rule::in(BoRefundStatus::label_keys())],
                'bo_notes'          => ['bail', 'nullable', 'string'],
                'bo_earnest_amount' => ['bail', 'required', 'decimal:0,2', 'gte:0'],
            ],
            [],
            trans_property(BookingOrder::class)
        )
            ->after(function (\Illuminate\Validation\Validator $validator) use ($bookingOrder, $request) {
                if ($validator->failed()) {
                    return;
                }
                if (null === $bookingOrder) { // 添加的时候
                    $bookingVehicle = BookingVehicle::query()->find($request->input('bo_bv_id'));
                    // 防止下单时车辆信息被更新，需与当前预定车辆信息一致。
                    if ($bookingVehicle->bv_type->value != $request->input('bo_type')
                        || $bookingVehicle->bv_plate_no != $request->input('bo_plate_no')
                        || $bookingVehicle->bv_pickup_date != $request->input('bo_pickup_date')
                        || $bookingVehicle->bv_rent_per_amount != $request->input('bo_rent_per_amount')
                        || $bookingVehicle->bv_deposit_amount != $request->input('bo_deposit_amount')
                        || $bookingVehicle->bv_min_rental_periods != $request->input('bo_min_rental_periods')
                        || $bookingVehicle->bv_registration_date != $request->input('bo_registration_date')
                        || $bookingVehicle->bv_mileage != $request->input('bo_mileage')
                        || $bookingVehicle->bv_service_interval != $request->input('bo_service_interval')
                        || $bookingVehicle->bv_props != $request->input('bo_props')
                        || $bookingVehicle->bv_note != $request->input('bo_note')
                    ) {
                        $validator->errors()->add('bv_id', '信息已经更新，请重新下单');

                        return;
                    }
                }
            })
            ->validate()
        ;

        if (null === $bookingOrder) {
            $bookingOrder = BookingOrder::query()->create($input + ['order_at' => now()]);
        } else {
            $bookingOrder->update($input);
        }

        $bookingOrder->load(['Vehicle', 'Customer']);

        return $this->response()->withData($bookingOrder)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function destroy(BookingOrder $bookingOrder): Response
    {
        $bookingOrder->delete();

        return $this->response()->withData($bookingOrder)->respond();
    }

    #[PermissionAction(PermissionAction::READ)]
    public function generate(Request $request, BookingVehicle $bookingVehicle): Response
    {
        // 根据预定车辆生成订单草稿数据。
        $bookingVehicle->append('bo_no');
        $bookingVehicle->load(['Vehicle']);

        $bookingVehicleArray = $bookingVehicle->toArray();

        $result = [];
        foreach ($bookingVehicleArray as $key => $value) {
            if (null === $value || '' === $value) {
            } else {
                $key          = preg_replace('/^bv_/', 'bo_', $key);
                $result[$key] = $value;
            }
        }

        return $this->response()->withData($result)->respond();
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
            BoType::options(),
            BoSource::options(),
            BoPaymentStatus::options(),
            BoOrderStatus::options(),
            BoRefundStatus::options(),
            BoProps::options(),
            BoType::labelDic(),
        );
    }
}
