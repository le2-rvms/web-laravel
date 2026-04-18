<?php

namespace Tests\Http\Controllers\Customer\Sale;

use App\Enum\SaleContract\ScStatus;
use App\Enum\Vehicle\VeStatusDispatch;
use App\Enum\Vehicle\VeStatusRental;
use App\Enum\Vehicle\VeStatusService;
use App\Enum\VehicleInspection\ViInspectionType;
use App\Http\Controllers\Customer\Sale\SaleContractController as CustomerSaleContractController;
use App\Models\Customer\Customer;
use App\Models\Payment\Payment;
use App\Models\Sale\SaleContract;
use App\Models\Sale\VehicleTemp;
use App\Models\Vehicle\Vehicle;
use App\Models\Vehicle\VehicleAccident;
use App\Models\Vehicle\VehicleCenter;
use App\Models\Vehicle\VehicleInspection;
use App\Models\Vehicle\VehicleMaintenance;
use App\Models\Vehicle\VehicleManualViolation;
use App\Models\Vehicle\VehicleRepair;
use App\Models\Vehicle\VehicleUsage;
use App\Models\Vehicle\VehicleViolation;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\NoAuthTestCase;

/**
 * @internal
 */
#[CoversNothing]
class SaleContractControllerTest extends NoAuthTestCase
{
    private Customer $customer;

    private Customer $otherCustomer;

    private Vehicle $vehicle;

    private VehicleCenter $vehicleCenter;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = Str::upper(Str::random(8));

        $this->customer = Customer::factory()->create([
            'cu_contact_name'  => 'CUS-DETAIL-'.$suffix,
            'cu_contact_phone' => '199'.random_int(10000000, 99999999),
        ]);

        $this->otherCustomer = Customer::factory()->create([
            'cu_contact_name'  => 'CUS-OTHER-'.$suffix,
            'cu_contact_phone' => '198'.random_int(10000000, 99999999),
        ]);

        $this->vehicleCenter = VehicleCenter::factory()->create([
            'vc_name' => 'CUS-DETAIL-CENTER-'.$suffix,
        ]);

        $this->vehicle = Vehicle::factory()->create([
            've_plate_no'        => 'DETAIL-VEH-'.$suffix,
            've_status_service'  => VeStatusService::YES,
            've_status_rental'   => VeStatusRental::LISTED,
            've_status_dispatch' => VeStatusDispatch::NOT_DISPATCHED,
        ]);
    }

    public function testShowReturnsGroupDetails(): void
    {
        $order = SaleContract::factory()
            ->for($this->customer, 'Customer')
            ->for($this->vehicle, 'Vehicle')
            ->create([
                'sc_no'        => 'DETAIL-SC-'.Str::upper(Str::random(8)),
                'sc_group_no'  => 'DETAIL-GROUP-'.Str::upper(Str::random(8)),
                'sc_group_seq' => 1,
                'sc_status'    => ScStatus::SIGNED,
            ])
        ;

        $renew = SaleContract::factory()
            ->for($this->customer, 'Customer')
            ->for($this->vehicle, 'Vehicle')
            ->create([
                'sc_no'        => 'DETAIL-REN-'.Str::upper(Str::random(8)),
                'sc_group_no'  => $order->sc_group_no,
                'sc_group_seq' => 2,
                'sc_status'    => ScStatus::SIGNED,
            ])
        ;

        $paymentOnRenew = Payment::factory()
            ->for($renew, 'SaleContract')
            ->create([
                'p_remark' => 'CUS-DETAIL-PAYMENT',
            ])
        ;

        VehicleTemp::factory()
            ->for($order, 'SaleContract')
            ->for($this->vehicle, 'CurrentVehicle')
            ->for($this->vehicle, 'NewVehicle')
            ->create([
                'vt_remark' => 'CUS-DETAIL-TEMP',
            ])
        ;

        VehicleAccident::factory()
            ->for($renew, 'SaleContract')
            ->for($this->vehicle, 'Vehicle')
            ->for($this->vehicleCenter, 'VehicleCenter')
            ->create([
                'va_accident_dt'       => now(),
                'va_accident_location' => 'CUS-DETAIL-ACCIDENT',
            ])
        ;

        VehicleMaintenance::factory()
            ->for($renew, 'SaleContract')
            ->for($this->vehicle, 'Vehicle')
            ->for($this->vehicleCenter, 'VehicleCenter')
            ->create([
                'vm_remark' => 'CUS-DETAIL-MAINTENANCE',
            ])
        ;

        VehicleRepair::factory()
            ->for($renew, 'SaleContract')
            ->for($this->vehicle, 'Vehicle')
            ->for($this->vehicleCenter, 'VehicleCenter')
            ->create([
                'vr_remark' => 'CUS-DETAIL-REPAIR',
            ])
        ;

        $inspection = VehicleInspection::factory()
            ->for($renew, 'SaleContract')
            ->for($this->vehicle, 'Vehicle')
            ->create([
                'vi_inspection_type' => ViInspectionType::SC_DISPATCH,
            ])
        ;

        $usage = VehicleUsage::query()->create([
            'vu_sc_id'       => $renew->getKey(),
            'vu_ve_id'       => $this->vehicle->getKey(),
            'vu_start_vi_id' => $inspection->getKey(),
            'vu_remark'      => 'CUS-DETAIL-USAGE',
        ]);

        VehicleManualViolation::factory()
            ->for($this->vehicle, 'Vehicle')
            ->for($usage, 'VehicleUsage')
            ->create([
                'vv_remark' => 'CUS-DETAIL-MANUAL',
            ])
        ;

        VehicleViolation::factory()
            ->for($this->vehicle, 'Vehicle')
            ->for($usage, 'VehicleUsage')
            ->create([
                'vv_plate_no' => $this->vehicle->ve_plate_no,
                'vv_remark'   => 'CUS-DETAIL-VIOLATION',
            ])
        ;

        Sanctum::actingAs($this->customer);

        $response = $this->getJson(
            action([CustomerSaleContractController::class, 'show'], [$order->getKey()])
        );

        $response->assertOk()
            ->assertJsonPath('data.sc_id', $order->getKey())
            ->assertJsonPath('extra.PaymentIndexList.0.p_id', $paymentOnRenew->getKey())
        ;

        $extra = $response->json('extra');
        $this->assertIsArray($extra);
        $this->assertArrayHasKey('VehicleTempIndexList', $extra);
        $this->assertArrayHasKey('VehicleScheduleIndexList', $extra);
        $this->assertArrayHasKey('ExpiryDriverIndexList', $extra);
        $this->assertArrayHasKey('ExpiryVehicleIndexList', $extra);
        $this->assertArrayHasKey('VehicleAccidentIndexList', $extra);
        $this->assertArrayHasKey('PaymentIndexList', $extra);
        $this->assertArrayHasKey('PaymentIndexStat', $extra);
        $this->assertArrayHasKey('VehicleMaintenanceIndexList', $extra);
        $this->assertArrayHasKey('VehicleRepairIndexList', $extra);
        $this->assertArrayHasKey('VehicleRepairIndexStat', $extra);
        $this->assertArrayHasKey('VehicleViolationIndexList', $extra);
        $this->assertArrayHasKey('VehicleViolationIndexStat', $extra);
        $this->assertArrayHasKey('VehicleManualViolationIndexList', $extra);
        $this->assertArrayHasKey('VehicleManualViolationIndexStat', $extra);
    }

    public function testShowRejectsOtherCustomers(): void
    {
        $order = SaleContract::factory()
            ->for($this->customer, 'Customer')
            ->for($this->vehicle, 'Vehicle')
            ->create([
                'sc_no'        => 'DETAIL-403-'.Str::upper(Str::random(8)),
                'sc_group_no'  => 'DETAIL-403-GROUP-'.Str::upper(Str::random(8)),
                'sc_group_seq' => 1,
            ])
        ;

        Sanctum::actingAs($this->otherCustomer);

        $response = $this->getJson(
            action([CustomerSaleContractController::class, 'show'], [$order->getKey()])
        );

        $response->assertNotFound();
    }

    public function testRemovedDetailRoutesAreNoLongerRegistered(): void
    {
        Sanctum::actingAs($this->customer);

        foreach ([
            '/api-customer/payments',
            '/api-customer/vehicle-accidents',
            '/api-customer/vehicle-temps',
            '/api-customer/vehicle-maintenances',
            '/api-customer/vehicle-repairs',
            '/api-customer/vehicle-manual-violations',
            '/api-customer/vehicle-violations',
            '/api-customer/vehicle-schedules',
            '/api-customer/expiry-drivers',
            '/api-customer/expiry-vehicles',
        ] as $uri) {
            $this->getJson($uri)->assertNotFound();
        }
    }
}
