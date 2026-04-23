<?php

namespace Tests\Http\Controllers\Customer\Sale;

use App\Enum\Booking\BvIsListed;
use App\Enum\Vehicle\VeStatusDispatch;
use App\Enum\Vehicle\VeStatusRental;
use App\Enum\Vehicle\VeStatusService;
use App\Http\Controllers\Customer\Sale\BookingVehicleController as CustomerBookingVehicleController;
use App\Models\_\Company;
use App\Models\Customer\Customer;
use App\Models\Sale\BookingVehicle;
use App\Models\Vehicle\Vehicle;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\NoAuthTestCase;

/**
 * @internal
 */
#[CoversNothing]
class BookingVehicleControllerTest extends NoAuthTestCase
{
    private Customer $customer;

    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();

        if (!Schema::hasTable('customers') || !Schema::hasTable('vehicles') || !Schema::hasTable('booking_vehicles')) {
            $this->markTestSkipped('Customer booking vehicle tests require the legacy business database.');
        }

        $suffix = Str::upper(Str::random(8));

        $this->customer = Customer::factory()->create([
            'cu_contact_name'  => 'CUS-BOOKING-'.$suffix,
            'cu_contact_phone' => '197'.random_int(10000000, 99999999),
        ]);

        $this->vehicle = Vehicle::factory()->create([
            've_plate_no'        => 'CUS-BV-'.$suffix,
            've_status_service'  => VeStatusService::YES,
            've_status_rental'   => VeStatusRental::LISTED,
            've_status_dispatch' => VeStatusDispatch::NOT_DISPATCHED,
        ]);
    }

    public function testIndexReturnsOnlyListedVehiclesAndCompany(): void
    {
        Company::query()->delete();
        Company::factory()->create([
            'cp_name'  => '客户预定测试公司',
            'cp_phone' => '400-800-9000',
        ]);

        $listed = BookingVehicle::factory()
            ->for($this->vehicle, 'Vehicle')
            ->create([
                'bv_plate_no'  => $this->vehicle->ve_plate_no,
                'bv_note'      => 'CUS-BV-LISTED',
                'bv_is_listed' => BvIsListed::LISTED,
                'bv_listed_at' => now(),
            ])
        ;

        BookingVehicle::factory()
            ->for($this->vehicle, 'Vehicle')
            ->create([
                'bv_plate_no'  => $this->vehicle->ve_plate_no,
                'bv_note'      => 'CUS-BV-UNLISTED',
                'bv_is_listed' => BvIsListed::UNLISTED,
                'bv_listed_at' => now(),
            ])
        ;

        Sanctum::actingAs($this->customer);

        $response = $this->getJson(
            action([CustomerBookingVehicleController::class, 'index'])
        );

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.bv_id', $listed->getKey())
            ->assertJsonPath('extra.perPage', 20)
            ->assertJsonPath('extra.company.cp_name', '客户预定测试公司')
        ;
    }

    public function testIndexSupportsLastIdCursor(): void
    {
        $first = BookingVehicle::factory()
            ->for($this->vehicle, 'Vehicle')
            ->create([
                'bv_plate_no'  => $this->vehicle->ve_plate_no,
                'bv_is_listed' => BvIsListed::LISTED,
                'bv_listed_at' => now(),
            ])
        ;

        $second = BookingVehicle::factory()
            ->for($this->vehicle, 'Vehicle')
            ->create([
                'bv_plate_no'  => $this->vehicle->ve_plate_no,
                'bv_is_listed' => BvIsListed::LISTED,
                'bv_listed_at' => now(),
            ])
        ;

        Sanctum::actingAs($this->customer);

        $response = $this->getJson(
            action([CustomerBookingVehicleController::class, 'index'], ['last_id' => $second->getKey()])
        );

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.bv_id', $first->getKey())
        ;
    }

    public function testShowReturnsListedVehicleAndCompany(): void
    {
        Company::query()->delete();
        Company::factory()->create([
            'cp_name' => '详情公司',
        ]);

        $bookingVehicle = BookingVehicle::factory()
            ->for($this->vehicle, 'Vehicle')
            ->create([
                'bv_plate_no'  => $this->vehicle->ve_plate_no,
                'bv_is_listed' => BvIsListed::LISTED,
                'bv_listed_at' => now(),
            ])
        ;

        Sanctum::actingAs($this->customer);

        $response = $this->getJson(
            action([CustomerBookingVehicleController::class, 'show'], [$bookingVehicle->getKey()])
        );

        $response->assertOk()
            ->assertJsonPath('data.bv_id', $bookingVehicle->getKey())
            ->assertJsonPath('data.vehicle.ve_plate_no', $this->vehicle->ve_plate_no)
            ->assertJsonPath('extra.company.cp_name', '详情公司')
        ;
    }

    public function testShowRejectsUnlistedVehicle(): void
    {
        $bookingVehicle = BookingVehicle::factory()
            ->for($this->vehicle, 'Vehicle')
            ->create([
                'bv_plate_no'  => $this->vehicle->ve_plate_no,
                'bv_is_listed' => BvIsListed::UNLISTED,
                'bv_listed_at' => now(),
            ])
        ;

        Sanctum::actingAs($this->customer);

        $response = $this->getJson(
            action([CustomerBookingVehicleController::class, 'show'], [$bookingVehicle->getKey()])
        );

        $response->assertNotFound();
    }

    public function testRequiresAuthentication(): void
    {
        $bookingVehicle = BookingVehicle::factory()
            ->for($this->vehicle, 'Vehicle')
            ->create([
                'bv_plate_no'  => $this->vehicle->ve_plate_no,
                'bv_is_listed' => BvIsListed::LISTED,
                'bv_listed_at' => now(),
            ])
        ;

        $this->getJson(action([CustomerBookingVehicleController::class, 'index']))
            ->assertUnauthorized()
        ;

        $this->getJson(action([CustomerBookingVehicleController::class, 'show'], [$bookingVehicle->getKey()]))
            ->assertUnauthorized()
        ;
    }

    public function testIndexReturnsEmptyCompanyObjectWhenMissing(): void
    {
        Company::query()->delete();

        $bookingVehicle = BookingVehicle::factory()
            ->for($this->vehicle, 'Vehicle')
            ->create([
                'bv_plate_no'  => $this->vehicle->ve_plate_no,
                'bv_is_listed' => BvIsListed::LISTED,
                'bv_listed_at' => now(),
            ])
        ;

        Sanctum::actingAs($this->customer);

        $response = $this->getJson(action([CustomerBookingVehicleController::class, 'index']));

        $response->assertOk()
            ->assertJsonPath('data.0.bv_id', $bookingVehicle->getKey())
        ;

        $company = $response->json('extra.company');
        $this->assertIsArray($company);
        $this->assertArrayHasKey('cp_id', $company);
    }
}
