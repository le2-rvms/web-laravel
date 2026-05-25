<?php

namespace Database\Factories\Sale;

use App\Enum\SaleContract\ScPaymentPeriod;
use App\Enum\SaleContract\ScRentalType;
use App\Enum\SaleContract\ScStatus;
use App\Models\Sale\SaleContract;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaleContract>
 */
class SaleContractFactory extends Factory
{
    private const array CLOSED_STATUSES = [
        ScStatus::COMPLETED,
        ScStatus::EARLY_TERMINATION,
    ];

    public function definition(): array
    {
        $rentalType = $this->weightedValue([
            ScRentalType::LONG_TERM  => 72,
            ScRentalType::SHORT_TERM => 28,
        ]);

        $status = $this->weightedValue([
            ScStatus::SIGNED            => 48,
            ScStatus::COMPLETED         => 27,
            ScStatus::PENDING           => 15,
            ScStatus::EARLY_TERMINATION => 6,
            ScStatus::CANCELLED         => 4,
        ]);

        $attributes = ScRentalType::LONG_TERM === $rentalType
            ? $this->longTermContractAttributes($status)
            : $this->shortTermContractAttributes($status);

        $scNo = strtoupper($this->faker->unique()->bothify('CN##########'));

        return $attributes + [
            'sc_no'        => $scNo,
            'sc_group_no'  => $scNo,
            'sc_group_seq' => 1,
        ];
    }

    private function longTermContractAttributes(string $status): array
    {
        $paymentPeriod = $this->weightedValue([
            ScPaymentPeriod::MONTHLY_PREPAID      => 50,
            ScPaymentPeriod::MONTHLY_POSTPAID     => 18,
            ScPaymentPeriod::QUARTERLY_PREPAID    => 12,
            ScPaymentPeriod::QUARTERLY_POSTPAID   => 5,
            ScPaymentPeriod::WEEKLY_PREPAID       => 8,
            ScPaymentPeriod::WEEKLY_POSTPAID      => 3,
            ScPaymentPeriod::SEMI_ANNUAL_PREPAID  => 3,
            ScPaymentPeriod::SEMI_ANNUAL_POSTPAID => 1,
        ]);

        $installments = (int) match ($paymentPeriod) {
            ScPaymentPeriod::WEEKLY_PREPAID, ScPaymentPeriod::WEEKLY_POSTPAID           => $this->weightedValue([4 => 15, 8 => 30, 12 => 35, 24 => 20]),
            ScPaymentPeriod::QUARTERLY_PREPAID, ScPaymentPeriod::QUARTERLY_POSTPAID     => $this->weightedValue([2 => 8, 4 => 45, 8 => 35, 12 => 12]),
            ScPaymentPeriod::SEMI_ANNUAL_PREPAID, ScPaymentPeriod::SEMI_ANNUAL_POSTPAID => $this->weightedValue([2 => 60, 4 => 30, 6 => 10]),
            default                                                                     => $this->weightedValue([6 => 10, 12 => 45, 24 => 30, 36 => 12, 48 => 3]),
        };

        $periodRule   = ScPaymentPeriod::interval[$paymentPeriod];
        $durationDays = (int) Carbon::now()->diffInDays(
            Carbon::now()->copy()->modify(sprintf('+%d %s', $installments * $periodRule['interval'], $periodRule['interval_unit'])),
            true
        );

        [$startDate, $endDate, $timeline] = $this->timeline($status, $durationDays);

        $rentAmount = match ($paymentPeriod) {
            ScPaymentPeriod::WEEKLY_PREPAID, ScPaymentPeriod::WEEKLY_POSTPAID           => $this->money(700, 1800, 50),
            ScPaymentPeriod::QUARTERLY_PREPAID, ScPaymentPeriod::QUARTERLY_POSTPAID     => $this->money(8500, 19000, 100),
            ScPaymentPeriod::SEMI_ANNUAL_PREPAID, ScPaymentPeriod::SEMI_ANNUAL_POSTPAID => $this->money(16000, 36000, 100),
            default                                                                     => $this->money(2800, 6800, 100),
        };

        $managementFee  = fake()->boolean(65) ? $this->money(300, 1500, 50) : 0;
        $depositAmount  = $this->money(max(2000, (int) $rentAmount), (int) $rentAmount * fake()->numberBetween(2, 3), 100);
        $totalRent      = $installments * $rentAmount;
        $isWeeklyPeriod = str_starts_with($paymentPeriod, 'weekly');

        return [
            'sc_rental_type'                     => ScRentalType::LONG_TERM,
            'sc_payment_period'                  => $paymentPeriod,
            'sc_free_days'                       => fake()->numberBetween(0, 5),
            'sc_start_date'                      => $startDate->format('Y-m-d'),
            'sc_installments'                    => $installments,
            'sc_rental_days'                     => (int) $startDate->diffInDays($endDate, true) + 1,
            'sc_end_date'                        => $endDate->format('Y-m-d'),
            'sc_deposit_amount'                  => $depositAmount,
            'sc_management_fee_amount'           => $managementFee,
            'sc_rent_amount'                     => $rentAmount,
            'sc_payment_day'                     => $isWeeklyPeriod ? fake()->numberBetween(1, 7) : fake()->numberBetween(1, 28),
            'sc_total_rent_amount'               => $totalRent,
            'sc_insurance_base_fee_amount'       => null,
            'sc_insurance_additional_fee_amount' => null,
            'sc_other_fee_amount'                => null,
            'sc_total_amount'                    => $totalRent + $depositAmount + $managementFee,
        ] + $timeline;
    }

    private function shortTermContractAttributes(string $status): array
    {
        $rentalDays = match ($this->weightedValue([
            'weekend'    => 20,
            'week'       => 35,
            'half_month' => 25,
            'month'      => 20,
        ])) {
            'weekend'    => fake()->numberBetween(1, 3),
            'week'       => fake()->numberBetween(4, 7),
            'half_month' => fake()->numberBetween(8, 15),
            'month'      => fake()->numberBetween(16, 30),
        };

        [$startDate, $endDate, $timeline] = $this->timeline($status, $rentalDays);

        $freeDays       = fake()->numberBetween(0, 2);
        $rentAmount     = $this->money(180, 880, 10);
        $depositAmount  = $this->money(800, 6000, 100);
        $managementFee  = fake()->boolean(55) ? $this->money(80, 600, 20) : 0;
        $insuranceBase  = $this->money($rentalDays * 20, $rentalDays * 55, 10);
        $insuranceExtra = fake()->boolean(35) ? $this->money($rentalDays * 10, $rentalDays * 35, 10) : 0;
        $otherFee       = fake()->boolean(25) ? $this->money(50, 800, 10) : 0;
        $totalRent      = max(1, $rentalDays - $freeDays) * $rentAmount;

        return [
            'sc_rental_type'                     => ScRentalType::SHORT_TERM,
            'sc_payment_period'                  => null,
            'sc_free_days'                       => $freeDays,
            'sc_start_date'                      => $startDate->format('Y-m-d'),
            'sc_installments'                    => null,
            'sc_rental_days'                     => $rentalDays,
            'sc_end_date'                        => $endDate->format('Y-m-d'),
            'sc_deposit_amount'                  => $depositAmount,
            'sc_management_fee_amount'           => $managementFee,
            'sc_rent_amount'                     => $rentAmount,
            'sc_payment_day'                     => null,
            'sc_total_rent_amount'               => $totalRent,
            'sc_insurance_base_fee_amount'       => $insuranceBase,
            'sc_insurance_additional_fee_amount' => $insuranceExtra,
            'sc_other_fee_amount'                => $otherFee,
            'sc_total_amount'                    => $depositAmount + $managementFee + $totalRent + $insuranceBase + $insuranceExtra + $otherFee,
        ] + $timeline;
    }

    private function timeline(string $status, float|int $rentalDays): array
    {
        $periodStart = Carbon::createFromTimestamp(strtotime(sprintf('%d month', config('setting.gen.month.current') + config('setting.gen.month.offset'))))->startOfMonth();
        $periodEnd   = $periodStart->copy()->endOfMonth();
        $rentalDays  = max(1, (int) round($rentalDays));
        $beforeStart = fn (Carbon $startDate, int $minDays, int $maxDays): Carbon => $startDate->copy()
            ->subDays(fake()->numberBetween($minDays, $maxDays))
            ->setTime(fake()->numberBetween(9, 19), fake()->randomElement([0, 15, 30, 45]))
        ;

        if (in_array($status, self::CLOSED_STATUSES, true)) {
            $closedAt = Carbon::createFromTimestamp(fake()->numberBetween($periodStart->timestamp, $periodEnd->timestamp))
                ->setTime(fake()->numberBetween(15, 21), fake()->randomElement([0, 15, 30, 45]))
            ;
            $startDate = $closedAt->copy()->startOfDay()->subDays($rentalDays - 1);
            $endDate   = ScStatus::EARLY_TERMINATION === $status
                ? $startDate->copy()->addDays($rentalDays + fake()->numberBetween(7, 90))
                : $closedAt->copy()->startOfDay();

            return [$startDate, $endDate, [
                'sc_status'               => $status,
                'sc_order_at'             => $beforeStart($startDate, 1, 12),
                'sc_signed_at'            => $beforeStart($startDate, 0, 3),
                'sc_canceled_at'          => null,
                'sc_completed_at'         => ScStatus::COMPLETED === $status ? $closedAt : null,
                'sc_early_termination_at' => ScStatus::EARLY_TERMINATION === $status ? $closedAt : null,
            ]];
        }

        if (ScStatus::SIGNED === $status) {
            $activeStartEarliest = $periodStart->copy()->subDays(min(45, max(0, $rentalDays - 1)));
            $startDate           = fake()->boolean(85)
                ? Carbon::createFromTimestamp(fake()->numberBetween($activeStartEarliest->timestamp, $periodEnd->timestamp))->startOfDay()
                : $periodEnd->copy()->addDays(fake()->numberBetween(1, 14))->startOfDay();
            $endDate  = $startDate->copy()->addDays($rentalDays - 1);
            $signedAt = $beforeStart($startDate, 0, 2);
            if ($signedAt->greaterThan($periodEnd->copy()->setTime(18, 0))) {
                $signedAt = $periodEnd->copy()->setTime(18, 0);
            }
            $orderAt = $signedAt->copy()->subDays(fake()->numberBetween(1, 10))->setTime(fake()->numberBetween(9, 19), fake()->randomElement([0, 15, 30, 45]));

            return [$startDate, $endDate, [
                'sc_status'               => $status,
                'sc_order_at'             => $orderAt,
                'sc_signed_at'            => $signedAt,
                'sc_canceled_at'          => null,
                'sc_completed_at'         => null,
                'sc_early_termination_at' => null,
            ]];
        }

        $orderAt    = Carbon::createFromTimestamp(fake()->numberBetween($periodStart->timestamp, $periodEnd->timestamp));
        $startDate  = $orderAt->copy()->addDays(fake()->numberBetween(1, 14))->startOfDay();
        $canceledAt = ScStatus::CANCELLED === $status
            ? $orderAt->copy()->addHours(fake()->numberBetween(2, 96))
            : null;

        return [$startDate, $startDate->copy()->addDays($rentalDays - 1), [
            'sc_status'               => $status,
            'sc_order_at'             => $orderAt,
            'sc_signed_at'            => null,
            'sc_canceled_at'          => $canceledAt,
            'sc_completed_at'         => null,
            'sc_early_termination_at' => null,
        ]];
    }

    private function money(float|int $min, float|int $max, int $step = 10): float
    {
        $min = (int) ceil($min / $step);
        $max = max($min, (int) floor($max / $step));

        return (float) (fake()->numberBetween($min, $max) * $step);
    }

    private function weightedValue(array $weights): int|string
    {
        $total = array_sum($weights);
        $pick  = fake()->numberBetween(1, $total);

        foreach ($weights as $value => $weight) {
            $pick -= $weight;
            if ($pick <= 0) {
                return $value;
            }
        }

        return array_key_first($weights);
    }
}
