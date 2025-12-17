<?php

namespace Database\Factories\Sale;

use App\Enum\Booking\BvIsListed;
use App\Enum\Booking\BvProps;
use App\Enum\Booking\BvType;
use App\Models\Sale\BookingVehicle;
use App\Models\Vehicle\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingVehicle>
 */
class BookingVehicleFactory extends Factory
{
    protected $model = BookingVehicle::class;

    public function definition(): array
    {
        $pickup       = $this->faker->dateTimeBetween('-30 days', 'now');
        $registration = $this->faker->dateTimeBetween('-60 days', 'now');

        return [
            'bv_type'               => BvType::label_key_random(),
            'bv_pickup_date'        => $pickup->format('Y-m-d'),
            'bv_rent_per_amount'    => $this->faker->numberBetween(300, 5000),
            'bv_deposit_amount'     => $this->faker->numberBetween(500, 10000),
            'bv_min_rental_periods' => $this->faker->numberBetween(1, 12),
            'bv_registration_date'  => $registration->format('Y-m-d'),
            'bv_mileage'            => $this->faker->numberBetween(0, 150000),
            'bv_service_interval'   => $this->faker->randomElement([0, 5000, 10000, 15000]),
            'bv_props'              => $this->faker->optional()->randomElements(array_keys(BvProps::kv), $this->faker->numberBetween(0, sizeof(BvProps::kv))),
            'bv_note'               => null,
            'bv_is_listed'          => BvIsListed::label_key_random(),
        ];
    }

    //    public function forVehicle(Vehicle $vehicle): self
    //    {
    //        return $this->state(function () use ($vehicle) {
    //            return ['bv_plate_no' => $vehicle->ve_plate_no];
    //        });
    //    }
}
