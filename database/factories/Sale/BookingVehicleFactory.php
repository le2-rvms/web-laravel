<?php

namespace Database\Factories\Sale;

use App\Enum\Booking\BvBType;
use App\Enum\Booking\BvIsListed;
use App\Enum\Booking\RbvProps;
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
            'b_type'             => BvBType::label_key_random(),
            'pickup_date'        => $pickup->format('Y-m-d'),
            'rent_per_amount'    => $this->faker->numberBetween(300, 5000),
            'deposit_amount'     => $this->faker->numberBetween(500, 10000),
            'min_rental_periods' => $this->faker->numberBetween(1, 12),
            'registration_date'  => $registration->format('Y-m-d'),
            'b_mileage'          => $this->faker->numberBetween(0, 150000),
            'service_interval'   => $this->faker->randomElement([0, 5000, 10000, 15000]),
            'b_props'            => $this->faker->optional()->randomElements(array_keys(RbvProps::kv), $this->faker->numberBetween(0, sizeof(RbvProps::kv))),
            'b_note'             => null,
            'is_listed'          => BvIsListed::label_key_random(),
        ];
    }

    public function forVehicle(Vehicle $vehicle): self
    {
        return $this->state(function () use ($vehicle) {
            return ['plate_no' => $vehicle->plate_no];
        });
    }
}
