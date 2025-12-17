<?php

namespace Tests\Http\Controllers\Admin\Booking;

use App\Enum\Booking\BvIsListed;
use App\Enum\Booking\BvProps;
use App\Enum\Booking\BvType;
use App\Enum\Vehicle\VeStatusService;
use App\Http\Controllers\Admin\Sale\BookingVehicleController;
use App\Models\Sale\BookingVehicle;
use App\Models\Vehicle\Vehicle;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

/**
 * @property Vehicle        $vehicle
 * @property BookingVehicle $bv
 *
 * @internal
 */
#[CoversNothing]
#[\AllowDynamicProperties]
class BookingVehiclesControllerTest extends TestCase
{
    use WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        Vehicle::query()->whereLike('ve_plate_no', 'TEST-%')->delete();
        BookingVehicle::query()->whereLike('bv_plate_no', 'TEST-%')->delete();

        $this->vehicle = Vehicle::factory()->create(['ve_plate_no' => 'TEST-004', 've_status_service' => VeStatusService::YES]);
    }

    public function testIndexReturnsOk()
    {
        $bookingVehicle = BookingVehicle::factory()->for($this->vehicle)->create();

        $res = $this->getJson(
            action([BookingVehicleController::class, 'index'])
        );
        $res->assertOk();
        // 列表结构可能是自定义分页体，这里只断言基本成功即可
        $this->assertIsArray($res->json());
    }

    public function testShowReturnsSingleItem()
    {
        $bookingVehicle = BookingVehicle::factory()->for($this->vehicle)->create();

        $res = $this->getJson(
            action([BookingVehicleController::class, 'show'], [$bookingVehicle])
        );
        $res->assertOk()->assertJsonStructure(['data']);
    }

    public function testStoreCreatesRecord()
    {
        $payload = [
            'bv_type'               => BvType::WEEKLY_RENT,
            'bv_plate_no'           => $this->vehicle->ve_plate_no,
            'bv_pickup_date'        => now()->toDateString(),
            'bv_rent_per_amount'    => 1000,
            'bv_deposit_amount'     => 5000,
            'bv_min_rental_periods' => 2,
            'bv_registration_date'  => now()->toDateString(),
            'bv_mileage'            => 0,
            'bv_service_interval'   => 0,
            'bv_props'              => $this->faker->optional()->randomElements(array_keys(BvProps::kv), $this->faker->numberBetween(0, sizeof(BvProps::kv))),
            'bv_note'               => 'note here',
        ];

        $res = $this->postJson(
            action(
                [BookingVehicleController::class, 'store'],
                $payload
            )
        );
        $res->assertOk()
            ->assertJsonStructure(['data'])
        ;

        // 断言数据库已存在对应记录（通过 Eloquent 查询，避免直接依赖表名）
        $this->assertTrue(
            BookingVehicle::query()
                ->where('bv_plate_no', $this->vehicle->ve_plate_no)
                ->where('bv_rent_per_amount', 1000)
                ->exists()
        );
    }

    public function testStoreValidationFailsWhenRequiredMissing()
    {
        // 少传关键字段：b_type / plate_no / pickup_date / rent_per_amount / deposit_amount / min_rental_periods / registration_date
        $res = $this->postJson(
            action(
                [BookingVehicleController::class, 'store'],
                [
                    'bv_type' => BvType::WEEKLY_RENT,
                    // 故意缺失其他必填项
                ]
            )
        );

        $res->assertStatus(422)
            ->assertJsonValidationErrors(['ve_plate_no', 'bv_pickup_date', 'bv_rent_per_amount', 'bv_deposit_amount', 'bv_min_rental_periods', 'bv_registration_date'])
        ;
    }

    public function testUpdateUpdatesRecord()
    {
        $bookingVehicle = BookingVehicle::factory()->for($this->vehicle)->create();

        // 更新时，控制器里对 b_type / plate_no 使用了 excludeIf，因此它们非必传
        $payload = [
            'bv_plate_no'           => $this->vehicle->ve_plate_no,
            'bv_type'               => BvType::WEEKLY_RENT,
            'bv_pickup_date'        => now()->toDateString(),
            'bv_rent_per_amount'    => 2000,
            'bv_deposit_amount'     => 3000,
            'bv_min_rental_periods' => 4,
            'bv_registration_date'  => now()->toDateString(),
            'bv_mileage'            => 12345,
            'bv_service_interval'   => 6000,
            'bv_props'              => $this->faker->optional()->randomElements(array_keys(BvProps::kv), $this->faker->numberBetween(0, sizeof(BvProps::kv))),
            'bv_note'               => 'updated note',
        ];

        $res = $this->putJson(
            action([BookingVehicleController::class, 'update'], [$bookingVehicle]),
            $payload
        );
        $res->assertOk()->assertJsonStructure(['data']);

        $bookingVehicle->refresh();
        $this->assertSame(2000, (int) $bookingVehicle->bv_rent_per_amount);
        $this->assertSame(3000, (int) $bookingVehicle->bv_deposit_amount);
        $this->assertSame(4, (int) $bookingVehicle->bv_min_rental_periods);
        $this->assertSame(12345, (int) $bookingVehicle->bv_mileage);
        $this->assertSame(6000, (int) $bookingVehicle->bv_service_interval);
    }

    public function testDestroyDeletesRecord()
    {
        $bookingVehicle = BookingVehicle::factory()->for($this->vehicle)->create();

        $res = $this->deleteJson(
            action([BookingVehicleController::class, 'destroy'], [$bookingVehicle])
        );
        $res->assertOk()->assertJsonStructure(['data']);

        $this->assertModelMissing($bookingVehicle);
    }

    public function testEditReturnsDefaultFormDataAndOptions()
    {
        $bookingVehicle = BookingVehicle::factory()->for($this->vehicle)->create();

        $res = $this->getJson(
            action([BookingVehicleController::class, 'edit'], [$bookingVehicle])
        );
        $res->assertOk()->assertJsonStructure(['data']);
    }

    public function testCreateReturnsDefaultFormDataAndOptions()
    {
        $res = $this->getJson(
            action([BookingVehicleController::class, 'create'])
        );
        $res->assertOk()->assertJsonStructure(
            [
                'data' => [
                    'bv_type',
                    'bv_pickup_date',
                    'bv_registration_date',
                ],
            ]
        );
    }

    #[Test]
    public function testStatusUpdatesIsListedSuccess()
    {
        $bookingVehicle = BookingVehicle::factory()->for($this->vehicle)->create();

        // 取一个“合法值”
        $valid = array_keys(BvIsListed::LABELS)[0];

        $res = $this->putJson(
            action([BookingVehicleController::class, 'status'], [$bookingVehicle]),
            [
                'bv_is_listed' => $valid,
            ]
        );

        $res->assertOk()->assertJsonStructure(['data']);

        $bookingVehicle->refresh();
        $actual = $bookingVehicle->bv_is_listed->value;
        $this->assertSame($valid, $actual, 'bv_is_listed 未被正确更新');
    }

    #[Test]
    public function testStatusValidationFailsWhenMissingIsListed()
    {
        $bookingVehicle = BookingVehicle::factory()->for($this->vehicle)->create();

        $res = $this->putJson(
            action([BookingVehicleController::class, 'status'], [$bookingVehicle]),
            [
                // 故意不传 bv_is_listed
            ]
        );

        $res->assertStatus(422)
            ->assertJsonValidationErrors(['bv_is_listed'])
        ;
    }

    #[Test]
    public function testStatusValidationFailsOnInvalidEnumValue()
    {
        $bookingVehicle = BookingVehicle::factory()->for($this->vehicle)->create();

        // 提供一个明显非法的值
        $invalid = '___NOT_A_VALID_ENUM___';

        $res = $this->putJson(
            action([BookingVehicleController::class, 'status'], [$bookingVehicle]),
            [
                'bv_is_listed' => $invalid,
            ]
        );

        $res->assertStatus(422)
            ->assertJsonValidationErrors(['bv_is_listed'])
        ;
    }
}
