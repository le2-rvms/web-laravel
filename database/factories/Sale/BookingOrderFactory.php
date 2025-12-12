<?php

namespace Database\Factories\Sale;

use App\Enum\Booking\BoBoSource;
use App\Enum\Booking\BoOrderStatus;
use App\Enum\Booking\BoPaymentStatus;
use App\Enum\Booking\BoRefundStatus;
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
            'bo_source' => BoBoSource::label_key_random(),

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

            'payment_status' => BoPaymentStatus::label_key_random(),
            'sc_status'      => BoOrderStatus::label_key_random(),
            'refund_status'  => BoRefundStatus::label_key_random(),
            //            'b_notes'      => $this->faker->optional()->sentence(),
            'earnest_amount' => $this->faker->numberBetween(500, 1000),
        ];
    }

    public function forVehicle(Vehicle $vehicle): self
    {
        return $this->state(fn () => [
            'plate_no' => $vehicle->plate_no,
        ]);
    }

    public function forCustomer(Customer $customer): self
    {
        return $this->state(fn () => [
            'cu_id' => $customer->getKey(),
        ]);
    }

    public function forBookingVehicle(BookingVehicle $bookingVehicle): self
    {
        return $this->state(fn () => [
            'b_type'             => $bookingVehicle->b_type->value,
            'plate_no'           => $bookingVehicle->plate_no,
            'pickup_date'        => $bookingVehicle->pickup_date,
            'rent_per_amount'    => $bookingVehicle->rent_per_amount,
            'deposit_amount'     => $bookingVehicle->deposit_amount,
            'min_rental_periods' => $bookingVehicle->min_rental_periods,
            'registration_date'  => $bookingVehicle->registration_date,
            'b_mileage'          => $bookingVehicle->b_mileage,
            'service_interval'   => $bookingVehicle->service_interval,
            'b_props'            => $bookingVehicle->b_props,
            'b_note'             => $bookingVehicle->b_note,
        ]);
    }
}
