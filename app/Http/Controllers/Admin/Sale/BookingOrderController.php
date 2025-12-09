<?php

namespace App\Http\Controllers\Admin\Sale;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Booking\BoBoSource;
use App\Enum\Booking\BoBType;
use App\Enum\Booking\BoOrderStatus;
use App\Enum\Booking\BoPaymentStatus;
use App\Enum\Booking\BoRefundStatus;
use App\Enum\Booking\BvIsListed;
use App\Enum\Booking\RboProps;
use App\Enum\Booking\RbvProps;
use App\Enum\Vehicle\VeStatusService;
use App\Http\Controllers\Controller;
use App\Models\Customer\Customer;
use App\Models\Sale\BookingOrder;
use App\Models\Sale\BookingVehicle;
use App\Models\Vehicle\Vehicle;
use App\Services\PaginateService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
                    $builder->whereLike('bo.bo_no', '%'.$value.'%')
                        ->orWhereLike('bo.plate_no', '%'.$value.'%')
                        ->orWhereLike('cu.contact_name', '%'.$value.'%')
                    ;
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
        $bookingOrder = new BookingOrder([
            'bo_no'     => '',
            'bo_source' => BoBoSource::STORE,

            'payment_status' => BoPaymentStatus::UNPAID,
            'order_status'   => BoOrderStatus::UNPROCESSED,
            'refund_status'  => BoRefundStatus::NOREFUND,
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
        $validator = Validator::make(
            $request->all(),
            [
                'bv_id'              => ['bail', Rule::excludeIf(null !== $bookingOrder), 'required', 'integer', Rule::exists(BookingVehicle::class)->where('is_listed', BvIsListed::LISTED)],
                'bo_no'              => ['bail', Rule::excludeIf(null !== $bookingOrder), 'required', 'string', 'max:64', Rule::unique(BookingOrder::class)->ignore($bookingOrder)],
                'bo_source'          => ['bail', Rule::excludeIf(null !== $bookingOrder), 'required', Rule::in(BoBoSource::label_keys())],
                'cu_id'              => ['bail', Rule::excludeIf(null !== $bookingOrder), 'required', 'integer', Rule::exists(Customer::class)],
                'plate_no'           => ['bail', Rule::excludeIf(null !== $bookingOrder), 'required', 'string', Rule::exists(Vehicle::class, 'plate_no')->where('status_service', VeStatusService::YES)],
                'b_type'             => ['bail', Rule::excludeIf(null !== $bookingOrder), 'required', 'string', Rule::in(BoBType::label_keys())],
                'pickup_date'        => ['bail', Rule::excludeIf(null !== $bookingOrder), 'required', 'date_format:Y-m-d'],
                'rent_per_amount'    => ['bail', Rule::excludeIf(null !== $bookingOrder), 'required', 'decimal:0,2', 'gte:0'],
                'deposit_amount'     => ['bail', Rule::excludeIf(null !== $bookingOrder), 'required', 'decimal:0,2', 'gte:0'],
                'b_props'            => ['bail', Rule::excludeIf(null !== $bookingOrder), 'nullable', 'array'],
                'b_props.*'          => ['bail', Rule::excludeIf(null !== $bookingOrder), 'string', Rule::in(array_keys(RbvProps::kv))],
                'registration_date'  => ['bail', Rule::excludeIf(null !== $bookingOrder), 'required', 'date_format:Y-m-d'],
                'v_mileage'          => ['bail', Rule::excludeIf(null !== $bookingOrder), 'nullable', 'integer', 'min:0'],
                'service_interval'   => ['bail', Rule::excludeIf(null !== $bookingOrder), 'nullable', 'integer', 'min:0'],
                'min_rental_periods' => ['bail', Rule::excludeIf(null !== $bookingOrder), 'required', 'integer', 'min:0'],
                'payment_status'     => ['bail', 'required', Rule::in(BoPaymentStatus::label_keys())],
                'order_status'       => ['bail', 'required', Rule::in(BoOrderStatus::label_keys())],
                'refund_status'      => ['bail', 'required', Rule::in(BoRefundStatus::label_keys())],
                'b_notes'            => ['bail', Rule::excludeIf(null !== $bookingOrder), 'nullable', 'string'],
                'earnest_amount'     => ['bail', 'required', 'decimal:0,2', 'gte:0'],
            ],
            [],
            trans_property(BookingOrder::class)
        )
            ->after(function (\Illuminate\Validation\Validator $validator) use ($bookingOrder, $request) {
                if (!$validator->failed()) {
                    if (null === $bookingOrder) { // 添加的时候
                        $bookingVehicle = BookingVehicle::query()->find($request->input('bv_id'));
                        if ($bookingVehicle->b_type->value != $request->input('b_type')
                            || $bookingVehicle->plate_no != $request->input('plate_no')
                            || $bookingVehicle->pickup_date != $request->input('pickup_date')
                            || $bookingVehicle->rent_per_amount != $request->input('rent_per_amount')
                            || $bookingVehicle->deposit_amount != $request->input('deposit_amount')
                            || $bookingVehicle->min_rental_periods != $request->input('min_rental_periods')
                            || $bookingVehicle->registration_date != $request->input('registration_date')
                            || $bookingVehicle->b_mileage != $request->input('b_mileage')
                            || $bookingVehicle->service_interval != $request->input('service_interval')
                            || $bookingVehicle->b_props != $request->input('b_props')
                            || $bookingVehicle->b_note != $request->input('b_note')
                        ) {
                            $validator->errors()->add('bv_id', '信息已经更新，请重新下单');
                        }
                    }
                }
            })
        ;
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $validator->validated();

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
        $bookingVehicle->append('bo_no');
        $bookingVehicle->load(['Vehicle']);

        $result = array_filter($bookingVehicle->toArray());

        return $this->response()->withData($result)->respond();
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
            BoBType::options(),
            BoBoSource::options(),
            BoPaymentStatus::options(),
            BoOrderStatus::options(),
            BoRefundStatus::options(),
            RboProps::options(),
            BoBType::labelDic(),
        );
    }
}
