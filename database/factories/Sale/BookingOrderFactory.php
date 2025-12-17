<?php

namespace Database\Factories\Sale;

use App\Enum\Booking\BoOrderStatus;
use App\Enum\Booking\BoPaymentStatus;
use App\Enum\Booking\BoRefundStatus;
use App\Enum\Booking\BoSource;
use App\Models\Customer\Customer;
use App\Models\Sale\BookingOrder;
use App\Models\Sale\BookingVehicle;
use App\Models\Vehicle\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingOrder>
 */
class BookingOrderFactory extends Factory
{
    protected $model = BookingOrder::class;

    public function definition(): array
    {
        $pickup       = $this->faker->dateTimeBetween('-3 months', '+1 month');
        $registration = $this->faker->dateTimeBetween('-5 years', $pickup);

        return [
            'bo_no'     => 'RBO-'.$this->faker->unique()->bothify('####-######'),
            'bo_source' => BoSource::label_key_random(),

            // 生成并关联客户/车辆
            //            'cu_id'              => function () {
            //                return Customer::factory()->create()->getKey();
            //            },
            //            'plate_no'           => function () {
            //                return Vehicle::factory()->create()->plate_no;
            //            },

            // 校验规则要求存在于 vehicles.plate_no，这里与 plate_no 保持一致
            //            'b_type' => RboRboType::random(),

            //            'pickup_date'        => $pickup->format('Y-m-d'),
            //            'rent_per_amount'    => $this->faker->numberBetween(500, 5000),
            //            'deposit_amount'     => $this->faker->numberBetween(1000, 10000),
            //            'b_props'          => ['color' => $this->faker->safeColorName()],
            //            'registration_date'  => $registration->format('Y-m-d'),
            //            'mileage'            => $this->faker->numberBetween(0, 120000),
            //            'service_interval'   => $this->faker->randomElement([3000, 5000, 10000]),
            //            'min_rental_periods' => $this->faker->numberBetween(1, 12),

            'bo_payment_status' => BoPaymentStatus::label_key_random(),
            'bo_order_status'   => BoOrderStatus::label_key_random(),
            'bo_refund_status'  => BoRefundStatus::label_key_random(),
            //            'b_notes'      => $this->faker->optional()->sentence(),
            'bo_earnest_amount' => $this->faker->numberBetween(500, 1000),
        ];
    }

    public function forVehicle(Vehicle $vehicle): self
    {
        return $this->state(fn () => [
            'bo_plate_no' => $vehicle->ve_plate_no,
        ]);
    }

    public function forCustomer(Customer $customer): self
    {
        return $this->state(fn () => [
            'bo_cu_id' => $customer->getKey(),
        ]);
    }

    public function forBookingVehicle(BookingVehicle $bookingVehicle): self
    {
        return $this->state(fn () => [
            'bo_type'               => $bookingVehicle->bv_type->value,
            'bo_plate_no'           => $bookingVehicle->bv_plate_no,
            'bo_pickup_date'        => $bookingVehicle->bv_pickup_date,
            'bo_rent_per_amount'    => $bookingVehicle->bv_rent_per_amount,
            'bo_deposit_amount'     => $bookingVehicle->bv_deposit_amount,
            'bo_min_rental_periods' => $bookingVehicle->bv_min_rental_periods,
            'bo_registration_date'  => $bookingVehicle->bv_registration_date,
            'bo_mileage'            => $bookingVehicle->bv_mileage,
            'bo_service_interval'   => $bookingVehicle->bv_service_interval,
            'bo_props'              => $bookingVehicle->bv_props,
            'bo_note'               => $bookingVehicle->bv_note,
        ]);
    }
}
