<?php

namespace App\Http\Controllers\Admin\Sale;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Booking\BvIsListed;
use App\Enum\Booking\BvProps;
use App\Enum\Booking\BvType;
use App\Enum\Vehicle\VeStatusService;
use App\Http\Controllers\Controller;
use App\Models\Sale\BookingVehicle;
use App\Models\Vehicle\Vehicle;
use App\Services\PaginateService;
use App\Services\Uploader;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('预定车辆')]
class BookingVehicleController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
        );
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function create(Request $request): Response
    {
        $bookingVehicles = new BookingVehicle([
            'bv_type'              => BvType::WEEKLY_RENT,
            'bv_registration_date' => date('Y-m-d'),
            'bv_pickup_date'       => date('Y-m-d'),
        ]);

        $this->options();
        $this->response()->withExtras(
            Vehicle::optionsNo(),
        );

        return $this->response()->withData($bookingVehicles)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function edit(BookingVehicle $bookingVehicle): Response
    {
        $bookingVehicle->load('Vehicle');

        $this->options();

        return $this->response()->withData($bookingVehicle)->respond();
    }

    #[PermissionAction(PermissionAction::READ)]
    public function index(Request $request)
    {
        $this->options(true);
        $query = BookingVehicle::indexQuery();

        $paginate = new PaginateService(
            [],
            [['bv.bv_id', 'desc']],
            ['kw'],
            []
        );

        $paginate->paginator($query, $request, [
            'kw__func' => function ($value, Builder $builder) {
                $builder->where(function (Builder $builder) use ($value) {
                    $builder->where('bv.bv_plate_no', 'like', '%'.$value.'%')
                        ->orWhere('bv.bv_note', 'like', '%'.$value.'%')
                    ;
                });
            },
        ]);

        return $this->response()->withData($paginate)->respond();
    }

    #[PermissionAction(PermissionAction::READ)]
    public function show(BookingVehicle $bookingVehicle)
    {
        $this->options();
        $bookingVehicle->load(['Vehicle']);

        return $this->response()->withData($bookingVehicle)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function store(Request $request)
    {
        return $this->update($request, null);
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function update(Request $request, ?BookingVehicle $bookingVehicle)
    {
        $input = Validator::make(
            $request->all(),
            [
                'bv_type'               => ['bail', Rule::excludeIf(null !== $bookingVehicle), 'required', Rule::in(BvType::label_keys())],
                'bv_plate_no'           => ['bail', Rule::excludeIf(null !== $bookingVehicle), 'required', 'string', Rule::exists(Vehicle::class, 've_plate_no')->where('ve_status_service', VeStatusService::YES)],
                'bv_pickup_date'        => ['bail', 'required', 'nullable', 'date'],
                'bv_rent_per_amount'    => ['bail', 'required', 'decimal:0,2', 'gte:0'],
                'bv_deposit_amount'     => ['bail', 'required', 'decimal:0,2', 'gte:0'],
                'bv_min_rental_periods' => ['bail', 'required', 'numeric', 'min:0'],
                'bv_registration_date'  => ['bail', 'required', 'nullable', 'date'],
                'bv_mileage'            => ['bail', 'nullable', 'integer', 'min:0'],
                'bv_service_interval'   => ['bail', 'nullable', 'integer', 'min:0'],
                'bv_props'              => ['bail', 'nullable', 'array'],
                'bv_props.*'            => ['bail', 'string', Rule::in(array_keys(BvProps::kv))],
                'bv_note'               => ['bail', 'nullable', 'string'],
            ]
            + Uploader::validator_rule_upload_object('bv_photo')
            + Uploader::validator_rule_upload_array('bv_additional_photos'),
            [],
            trans_property(BookingVehicle::class)
        )
            ->after(function (\Illuminate\Validation\Validator $validator) {
                if ($validator->failed()) {
                    return;
                }
            })
            ->validate()
        ;

        if (null === $bookingVehicle) {
            $bookingVehicle = BookingVehicle::query()->create($input + ['bv_is_listed' => BvIsListed::LISTED, 'bv_listed_at' => now()]);
        } else {
            $bookingVehicle->update($input);
        }

        $bookingVehicle->load(['Vehicle']);

        return $this->response()->withData($bookingVehicle)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function destroy(BookingVehicle $bookingVehicle)
    {
        $bookingVehicle->delete();

        return $this->response()->withData($bookingVehicle)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function status(Request $request, BookingVehicle $bookingVehicle): Response
    {
        $input = Validator::make(
            $request->all(),
            [
                'bv_is_listed' => ['bail', 'required', Rule::in(BvIsListed::label_keys())],
            ],
            [],
            trans_property(BookingVehicle::class)
        )
            ->after(function (\Illuminate\Validation\Validator $validator) {
                if ($validator->failed()) {
                    return;
                }
            })
            ->validate()
        ;

        $bookingVehicle->update($input);

        return $this->response()->withData($bookingVehicle)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function upload(Request $request): Response
    {
        return Uploader::upload(
            $request,
            'booking_vehicle',
            [
                'bv_photo',
                'bv_additional_photos',
            ],
            $this
        );
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
            BvType::options(),
            BvProps::options(),
            BvType::labelDic(),
        );
    }
}
