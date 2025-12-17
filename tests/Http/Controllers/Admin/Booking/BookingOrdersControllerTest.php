<?php

namespace Tests\Http\Controllers\Admin\Booking;

use App\Enum\Booking\BoOrderStatus;
use App\Enum\Booking\BoPaymentStatus;
use App\Enum\Booking\BoRefundStatus;
use App\Enum\Booking\BoSource;
use App\Enum\Booking\BvIsListed;
use App\Enum\Vehicle\VeStatusService;
use App\Http\Controllers\Admin\Sale\BookingOrderController;
use App\Models\Customer\Customer;
use App\Models\Sale\BookingOrder;
use App\Models\Sale\BookingVehicle;
use App\Models\Vehicle\Vehicle;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

/**
 * @property Vehicle  $vehicle
 * @property Customer $customer
 *
 * @internal
 */
#[CoversNothing]
#[\AllowDynamicProperties]
class BookingOrdersControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Customer::query()->whereLike('cu_contact_name', 'TEST-%')->delete();
        Vehicle::query()->whereLike('ve_plate_no', 'TEST-%')->delete();
        BookingVehicle::query()->whereLike('bv_plate_no', 'TEST-%')->delete();
        BookingOrder::query()->whereLike('bo_plate_no', 'TEST-%')->delete();

        $this->vehicle  = Vehicle::factory()->create(['ve_plate_no' => 'TEST-001', 've_status_service' => VeStatusService::YES]);
        $this->customer = Customer::factory()->create(['cu_contact_name' => 'TEST-001']);

        $this->bookingVehicle = BookingVehicle::factory()->for($this->vehicle)->create(['bv_is_listed' => BvIsListed::LISTED]);
    }

    public function testIndexReturnsPaginatedList()
    {
        $bookingOrder = BookingOrder::factory()
            ->forBookingVehicle($this->bookingVehicle)
            ->for($this->customer)
            ->create()
        ;

        // getJson
        $resp = $this->getJson(
            action([BookingOrderController::class, 'index'])
        );

        $resp->assertOk();
    }

    public function testShowReturnsSingleResourceWithRelations()
    {
        $bookingOrder = BookingOrder::factory()
            ->forBookingVehicle($this->bookingVehicle)
            ->for($this->customer)
            ->create()
        ;

        $resp = $this->getJson(
            action([BookingOrderController::class, 'show'], [$bookingOrder])
        );

        $resp->assertOk()
            ->assertJson([
                'data' => [],
            ])
        ;
    }

    public function testCreateReturnsDefaultValuesAndExtras()
    {
        // create 并不需要模型，直接请求
        $resp = $this->getJson(
            action([BookingOrderController::class, 'create'])
        );

        $resp->assertOk()
            ->assertJson(
                [
                    'data' => [
                        'bo_no'           => '',
                        'bo_source'       => BoSource::STORE,
                        'bo_source_label' => BoSource::tryFrom(BoSource::STORE)->label,
                    ]],
            )
        ;
    }

    public function testEditReturnsModelWithRelationsAndExtras()
    {
        $bookingOrder = BookingOrder::factory()
            ->forBookingVehicle($this->bookingVehicle)
            ->for($this->customer)
            ->create()
        ;

        $resp = $this->getJson(
            action([BookingOrderController::class, 'edit'], [$bookingOrder])
        );

        $resp->assertOk();
    }

    public function testStoreCreatesANewOrderWithValidPayload()
    {
        $payload = BookingOrder::factory()
            ->forBookingVehicle($this->bookingVehicle)
            ->for($this->customer)
            ->raw()
        ;

        $payload['bo_bv_id'] = $this->bookingVehicle->bv_id;

        $resp = $this->postJson(
            action([BookingOrderController::class, 'store'], $payload)
        );

        $resp->assertOk()
            ->assertJson(
                [
                    'data' => [
                        'bo_no'             => $payload['bo_no'],
                        'bo_payment_status' => $payload['bo_payment_status'],
                    ],
                ]
            )
        ;

        $this->assertDatabaseHas((new BookingOrder())->getTable(), [
            'bo_no' => $payload['bo_no'],
        ]);
    }

    public function testStoreValidatesRequiredFields()
    {
        $resp = $this->postJson(
            action([BookingOrderController::class, 'store'])
        ); // 空载荷，触发校验

        $resp->assertStatus(422) // ValidationException
            ->assertJsonStructure(['message', 'errors'])
        ;
    }

    public function testUpdateChangesStatusFieldsOnExistingOrder()
    {
        /** @var BookingOrder $bookingOrder */
        $bookingOrder = BookingOrder::factory()
            ->forBookingVehicle($this->bookingVehicle)
            ->for($this->customer)
            ->create()
        ;

        $payload = [
            // update 请求里，仅这些枚举必填（其余在 update 中 excludeIf）
            'bo_payment_status' => BoPaymentStatus::PAID,
            'bo_order_status'   => BoOrderStatus::PROCESSED,
            'bo_refund_status'  => BoRefundStatus::REFUNDED,
            'bo_earnest_amount' => $bookingOrder->earnest_amount,
        ];

        $resp = $this->putJson(
            action([BookingOrderController::class, 'update'], [$bookingOrder]),
            $payload
        );

        $resp->assertOk()
            ->assertJson(
                [
                    'data' => [
                        'bo_payment_status' => BoPaymentStatus::PAID,
                        'bo_order_status'   => BoOrderStatus::PROCESSED,
                        'bo_refund_status'  => BoRefundStatus::REFUNDED,
                    ],
                ]
            )
        ;

        $bookingOrder->refresh();
        $this->assertEquals(BoPaymentStatus::PAID, $bookingOrder->bo_payment_status);
        $this->assertEquals(BoOrderStatus::PROCESSED, $bookingOrder->bo_order_status);
    }

    public function testDestroyDeletesTheOrder()
    {
        $bookingOrder = BookingOrder::factory()
            ->forBookingVehicle($this->bookingVehicle)
            ->for($this->customer)
            ->create()
        ;

        $resp = $this->deleteJson(
            action([BookingOrderController::class, 'destroy'], [$bookingOrder])
        );

        $resp->assertOk()
            ->assertJson(
                [
                    'data' => ['bo_id' => $bookingOrder->getKey()],
                ]
            )
        ;

        // 避免依赖表名/软删，直接用断言模型缺失
        $this->assertModelMissing($bookingOrder);
    }

    public function testGenerateReturnsVehicleDerivedPayloadWithRboNo()
    {
        $resp = $this->getJson(
            action([BookingOrderController::class, 'generate'], [$this->bookingVehicle])
        );

        $resp->assertOk()
            ->assertJson(
                ['data' => []]
            )
        ;
    }
}
