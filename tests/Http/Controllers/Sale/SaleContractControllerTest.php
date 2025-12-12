<?php

namespace Tests\Http\Controllers\Sale;

use App\Enum\Payment\RpPtId;
use App\Enum\Sale\ScPaymentDay_Month;
use App\Enum\Sale\ScPaymentDayType;
use App\Enum\Sale\ScRentalType;
use App\Enum\Sale\ScScStatus;
use App\Enum\Vehicle\VeStatusDispatch;
use App\Enum\Vehicle\VeStatusRental;
use App\Enum\Vehicle\VeStatusService;
use App\Http\Controllers\Admin\Sale\SaleContractController;
use App\Models\Customer\Customer;
use App\Models\Payment\Payment;
use App\Models\Sale\SaleContract;
use App\Models\Vehicle\Vehicle;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

/**
 * @property Customer $customer
 * @property Vehicle  $vehicle
 *
 * @internal
 */
#[CoversNothing]
#[\AllowDynamicProperties]
class SaleContractControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Customer::query()->whereLike('contact_name', '测试客户%')->delete();
        Vehicle::query()->whereLike('plate_no', 'TEST-%')->delete();

        $this->customer = Customer::factory()->create([
            'contact_name'  => '测试客户'.Str::upper(Str::random(4)),
            'contact_phone' => '199'.rand(10000000, 99999999),
        ]);

        $this->vehicle = Vehicle::factory()->create([
            'plate_no'        => 'TEST-001',
            'status_service'  => VeStatusService::YES,
            'status_rental'   => VeStatusRental::LISTED,
            'status_dispatch' => VeStatusDispatch::NOT_DISPATCHED,
        ]);
    }

    public function testIndexReturnsPaginatedOrdersList(): void
    {
        $order = SaleContract::factory()
            ->for($this->customer)
            ->for($this->vehicle)
            ->create()
        ;

        $response = $this->getJson(action([SaleContractController::class, 'index']));

        $response->assertOk()
            ->assertJsonFragment(['contract_number' => $order->contract_number])
        ;

        $extra = $response->json('extra');
        $this->assertIsArray($extra);
        $this->assertArrayHasKey('CustomerOptions', $extra);
        $this->assertArrayHasKey('VehicleOptions', $extra);
    }

    public function testCreateProvidesDefaultOrderSkeleton(): void
    {
        $response = $this->getJson(action([SaleContractController::class, 'create']));

        $response->assertOk();

        $this->assertSame(ScRentalType::LONG_TERM, $response->json('data.rental_type'));
        $this->assertArrayHasKey('CustomerOptions', $response->json('extra'));
    }

    public function testShowReturnsOrderWithPayments(): void
    {
        $order = SaleContract::factory()
            ->for($this->customer)
            ->for($this->vehicle)->create()
        ;

        $payment = Payment::factory()
            ->for($order)
            ->create()
        ;

        $response = $this->getJson(
            action([SaleContractController::class, 'show'], [$order->getKey()])
        );

        $response->assertOk()
            ->assertJsonPath('data.sc_id', $order->getKey())
            ->assertJsonPath('data.payments.0.rp_id', $payment->getKey())
        ;
    }

    public function testStoreCreatesLongTermOrderWithPayments(): void
    {
        $payload = SaleContract::factory()
            ->for($this->customer)
            ->for($this->vehicle)->raw()
        ;
        if (ScRentalType::LONG_TERM === $payload['rental_type']) {
            $payment             = Payment::factory()->raw();
            $payload['payments'] = [$payment];
        } else {
            $payload['payments'] = [];
        }

        $response = $this->postJson(action([SaleContractController::class, 'store']), $payload);

        $response->assertOk()
            ->assertJsonFragment(['contract_number' => $payload['contract_number']])
        ;

        $this->assertDatabaseHas((new SaleContract())->getTable(), [
            'contract_number' => $payload['contract_number'],
            'cu_id'           => $this->customer->getKey(),
        ]);

        $created = SaleContract::query()
            ->where('contract_number', $payload['contract_number'])
            ->with('Payments')
            ->firstOrFail()
        ;

        $this->assertCount(count($payload['payments']), $created->Payments);
        $this->assertSame(
            VeStatusRental::RESERVED,
            $this->vehicle->fresh()->status_rental->value
        );
    }

    public function testUpdateReplacesPaymentsAndPersistsComputedFields(): void
    {
        $order = SaleContract::factory()
            ->for($this->customer)
            ->for($this->vehicle)->create(['sc_status' => ScScStatus::PENDING])
        ;

        Payment::factory()
            ->for($order)
            ->create()
        ;

        $payload = SaleContract::factory()
            ->for($this->customer)
            ->for($this->vehicle)->raw()
        ;

        if (ScRentalType::LONG_TERM === $payload['rental_type']) {
            $payment             = Payment::factory()->raw();
            $payload['payments'] = [$payment];
        } else {
            $payload['payments'] = [];
        }

        $response = $this->putJson(
            action([SaleContractController::class, 'update'], [$order->getKey()]),
            $payload
        );

        $response->assertOk()
            ->assertJsonPath('data.rent_amount', bcadd($payload['rent_amount'], '0', 2))
            ->assertJsonPath('data.total_rent_amount', bcadd($payload['total_rent_amount'], '0', 2))
        ;

        $order->refresh()->load('Payments');

        $this->assertSame((float) $payload['rent_amount'], (float) $order->rent_amount);
    }

    public function testDestroyRemovesOrder(): void
    {
        $order = SaleContract::factory()
            ->for($this->customer)
            ->for($this->vehicle)
            ->create()
        ;

        $response = $this->deleteJson(
            action([SaleContractController::class, 'destroy'], [$order->getKey()])
        );

        $response->assertOk();

        $this->assertDatabaseMissing((new SaleContract())->getTable(), ['sc_id' => $order->getKey()]);
    }

    public function testPaymentsOptionGeneratesSchedule(): void
    {
        $params = [
            'rental_type'           => ScRentalType::LONG_TERM,
            'payment_day_type'      => ScPaymentDayType::MONTHLY_PREPAID,
            'deposit_amount'        => '300.00',
            'management_fee_amount' => '50.00',
            'rental_start'          => '2024-01-01',
            'installments'          => 2,
            'rent_amount'           => '800.00',
            'payment_day'           => ScPaymentDay_Month::DAY_5,
        ];

        $response = $this->getJson(
            action([SaleContractController::class, 'paymentsOption'], $params)
        );

        $response->assertOk();
        $response->assertJsonCount(4, 'data');
        $this->assertSame(RpPtId::DEPOSIT, $response->json('data.0.pt_id'));
        $this->assertSame(RpPtId::MANAGEMENT_FEE, $response->json('data.1.pt_id'));
        $this->assertSame(RpPtId::RENT, $response->json('data.2.pt_id'));
    }
}
