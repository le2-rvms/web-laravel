<?php

namespace Tests\Http\Controllers\Sale;

use App\Enum\Payment\PPtId;
use App\Enum\SaleContract\ScPaymentDay_Month;
use App\Enum\SaleContract\ScPaymentPeriod;
use App\Enum\SaleContract\ScRentalType;
use App\Enum\SaleContract\ScStatus;
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

        Customer::query()->whereLike('cu_contact_name', '测试客户%')->delete();
        Vehicle::query()->whereLike('ve_plate_no', 'TEST-%')->delete();

        $this->customer = Customer::factory()->create([
            'cu_contact_name'  => '测试客户'.Str::upper(Str::random(4)),
            'cu_contact_phone' => '199'.rand(10000000, 99999999),
        ]);

        $this->vehicle = Vehicle::factory()->create([
            've_plate_no'        => 'TEST-001',
            've_status_service'  => VeStatusService::YES,
            've_status_rental'   => VeStatusRental::LISTED,
            've_status_dispatch' => VeStatusDispatch::NOT_DISPATCHED,
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
            ->assertJsonFragment(['sc_no' => $order->sc_no])
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

        $this->assertSame(ScRentalType::LONG_TERM, $response->json('data.sc_rental_type'));
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
            ->assertJsonPath('data.payments.0.p_id', $payment->getKey())
        ;
    }

    public function testStoreCreatesLongTermOrderWithPayments(): void
    {
        $payload = SaleContract::factory()
            ->for($this->customer)
            ->for($this->vehicle)->raw()
        ;
        if (ScRentalType::LONG_TERM === $payload['sc_rental_type']) {
            $payment             = Payment::factory()->raw();
            $payload['payments'] = [$payment];
        } else {
            $payload['payments'] = [];
        }

        $response = $this->postJson(action([SaleContractController::class, 'store']), $payload);

        $response->assertOk()
            ->assertJsonFragment(['sc_no' => $payload['sc_no']])
        ;

        $this->assertDatabaseHas((new SaleContract())->getTable(), [
            'sc_no'    => $payload['sc_no'],
            'sc_cu_id' => $this->customer->getKey(),
        ]);

        $created = SaleContract::query()
            ->where('sc_no', $payload['sc_no'])
            ->with('Payments')
            ->firstOrFail()
        ;

        $this->assertCount(count($payload['payments']), $created->Payments);
        $this->assertSame(
            VeStatusRental::RESERVED,
            $this->vehicle->fresh()->ve_status_rental->value
        );
    }

    public function testUpdateReplacesPaymentsAndPersistsComputedFields(): void
    {
        $order = SaleContract::factory()
            ->for($this->customer)
            ->for($this->vehicle)->create(['sc_status' => ScStatus::PENDING])
        ;

        Payment::factory()
            ->for($order)
            ->create()
        ;

        $payload = SaleContract::factory()
            ->for($this->customer)
            ->for($this->vehicle)->raw()
        ;

        if (ScRentalType::LONG_TERM === $payload['sc_rental_type']) {
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
            ->assertJsonPath('data.sc_rent_amount', bcadd($payload['sc_rent_amount'], '0', 2))
            ->assertJsonPath('data.sc_total_rent_amount', bcadd($payload['sc_total_rent_amount'], '0', 2))
        ;

        $order->refresh()->load('Payments');

        $this->assertSame((float) $payload['sc_rent_amount'], (float) $order->sc_rent_amount);
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
            'sc_rental_type'           => ScRentalType::LONG_TERM,
            'sc_payment_period'        => ScPaymentPeriod::MONTHLY_PREPAID,
            'sc_deposit_amount'        => '300.00',
            'sc_management_fee_amount' => '50.00',
            'sc_start_date'            => '2024-01-01',
            'sc_installments'          => 2,
            'sc_rent_amount'           => '800.00',
            'sc_payment_day'           => ScPaymentDay_Month::DAY_5,
        ];

        $response = $this->getJson(
            action([SaleContractController::class, 'paymentsOption'], $params)
        );

        $response->assertOk();
        $response->assertJsonCount(4, 'data');
        $this->assertSame(PPtId::DEPOSIT, $response->json('data.0.p_pt_id'));
        $this->assertSame(PPtId::MANAGEMENT_FEE, $response->json('data.1.p_pt_id'));
        $this->assertSame(PPtId::RENT, $response->json('data.2.p_pt_id'));
    }
}
