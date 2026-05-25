<?php

namespace App\Console\Commands\Dev;

use App\Enum\Customer\CuType;
use App\Enum\Payment\PPtId;
use App\Enum\SaleContract\ScRentalType;
use App\Enum\SaleContract\ScStatus;
use App\Enum\Vehicle\VcStatus;
use App\Enum\Vehicle\VeStatusDispatch;
use App\Enum\Vehicle\VeStatusRental;
use App\Enum\Vehicle\VeStatusService;
use App\Http\Controllers\Admin\Sale\SaleContractController;
use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Customer\CustomerIndividual;
use App\Models\Payment\Payment;
use App\Models\Sale\SaleContract;
use App\Models\Sale\SaleSettlement;
use App\Models\Sale\VehicleTemp;
use App\Models\Vehicle\Vehicle;
use App\Models\Vehicle\VehicleCenter;
use App\Models\Vehicle\VehicleInspection;
use App\Models\Vehicle\VehicleInsurance;
use App\Models\Vehicle\VehicleMaintenance;
use App\Models\Vehicle\VehicleManualViolation;
use App\Models\Vehicle\VehicleModel;
use App\Models\Vehicle\VehiclePreparation;
use App\Models\Vehicle\VehicleRepair;
use App\Models\Vehicle\VehicleSchedule;
use App\Models\Vehicle\VehicleUsage;
use App\Models\Vehicle\VehicleViolation;
use App\Services\ProgressDisplay;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Attribute\AsCommand;

#[\AllowDynamicProperties]
#[AsCommand(name: '_dev:mock-data:generate', description: 'Generate realistic development mock data')]
class MockDataGenerate extends Command
{
    protected $signature = '_dev:mock-data:generate {--month=1-12}';

    private Carbon $periodStart;

    private Carbon $periodEnd;

    public function handle(): void
    {
        ini_set('memory_limit', '2G');

        $startTime = microtime(true);
        $this->info('start time: '.date('Y-m-d H:i:s'));

        [$month_form,$month_to] = array_map('intval', explode('-', $this->option('month')));

        $this->call(MockDataClear::class);

        DB::transaction(function () {
            Admin::factory()->count(20)->create();

            VehicleModel::factory()->count(20)->create();

            Vehicle::factory()->count(500)->create();

            VehicleCenter::factory()->count(5)->create();

            /** @var Collection $customers */
            $customers = Customer::factory()->count(20)->create();
            foreach ($customers as $customer) {
                if (CuType::INDIVIDUAL === $customer->cu_type->value) {
                    CustomerIndividual::factory()->for($customer)->create();
                }
            }
        });

        $Vehicles  = Vehicle::query()->get();
        $customers = Customer::query()->get();

        $vehicleCenters            = VehicleCenter::query()->where('vc_status', '=', VcStatus::ENABLED)->get();
        $vehicleStatusAt           = [];
        $vehicleInsuranceGenerated = [];
        $vehicleScheduleGenerated  = [];

        for ($month = $month_form; $month <= $month_to; ++$month) {
            config(['setting.gen.month.current' => $month]);
            $this->periodStart = Carbon::createFromTimestamp(strtotime(sprintf('%d month', config('setting.gen.month.current') + config('setting.gen.month.offset'))))->startOfMonth();
            $this->periodEnd   = $this->periodStart->copy()->endOfMonth();

            $this->info(str_repeat('#', 80));
            $this->info(sprintf("# \033[32mGenerating data for month %s \033[0m", $month));
            $this->info(str_repeat('#', 80));

            DB::transaction(function () use ($Vehicles, $customers, $vehicleCenters, &$vehicleStatusAt, &$vehicleInsuranceGenerated, &$vehicleScheduleGenerated) {
                $factor = config('setting.gen.factor');

                // ---------- group customer ----------
                $progressDisplay = new ProgressDisplay($factor, 'customer');
                for ($length = 0; $length < $factor; ++$length) {
                    $progressDisplay->displayProgress($length);

                    /** @var Vehicle $Vehicle */
                    $Vehicle = $Vehicles->random();

                    /** @var Customer $customer */
                    $customer = $customers->random();

                    VehiclePreparation::factory()->for($Vehicle)->create();

                    /** @var SaleContract $saleContract */
                    $saleContract = SaleContract::factory()
                        ->for($Vehicle)
                        ->for($customer)
                        ->create()
                    ;

                    if (ScRentalType::LONG_TERM === $saleContract->sc_rental_type->value) {
                        foreach (SaleContractController::callPaymentsOption($saleContract->toArray()) as $payment) {
                            Payment::factory()
                                ->for($saleContract)
                                ->mockDataPlan($payment, $saleContract, $this->periodEnd)
                                ->create()
                            ;
                        }
                    } else {
                        foreach (PPtId::getFeeTypes(ScRentalType::SHORT_TERM) as $field => $type) {
                            $amount = (float) $saleContract->{$field};
                            if ($amount <= 0) {
                                continue;
                            }

                            Payment::factory()
                                ->for($saleContract)
                                ->shortTermContractFee($saleContract, $field, $type, $this->periodStart, $this->periodEnd)
                                ->create()
                            ;
                        }
                    }

                    $statusAt = match ($saleContract->sc_status->value) {
                        ScStatus::CANCELLED         => Carbon::parse($saleContract->sc_canceled_at ?? $saleContract->sc_order_at),
                        ScStatus::SIGNED            => Carbon::parse($saleContract->sc_start_date),
                        ScStatus::COMPLETED         => Carbon::parse($saleContract->sc_completed_at),
                        ScStatus::EARLY_TERMINATION => Carbon::parse($saleContract->sc_early_termination_at),
                        default                     => Carbon::parse($saleContract->sc_order_at ?? $saleContract->sc_start_date),
                    };

                    if (!isset($vehicleStatusAt[$Vehicle->ve_id]) || !$vehicleStatusAt[$Vehicle->ve_id]->greaterThan($statusAt)) {
                        $vehicleStatusAt[$Vehicle->ve_id] = $statusAt;
                        $started                          = Carbon::parse($saleContract->sc_start_date)->lessThanOrEqualTo($this->periodEnd);

                        match ($saleContract->sc_status->value) {
                            ScStatus::PENDING => $Vehicle->updateStatus(
                                ve_status_service: VeStatusService::YES,
                                ve_status_rental: VeStatusRental::RESERVED,
                                ve_status_dispatch: VeStatusDispatch::NOT_DISPATCHED
                            ),
                            ScStatus::CANCELLED => $Vehicle->updateStatus(
                                ve_status_service: VeStatusService::YES,
                                ve_status_rental: VeStatusRental::LISTED,
                                ve_status_dispatch: VeStatusDispatch::NOT_DISPATCHED
                            ),
                            ScStatus::SIGNED => $Vehicle->updateStatus(
                                ve_status_service: VeStatusService::YES,
                                ve_status_rental: $started ? VeStatusRental::RENTED : VeStatusRental::RESERVED,
                                ve_status_dispatch: $started ? VeStatusDispatch::DISPATCHED : VeStatusDispatch::NOT_DISPATCHED
                            ),
                            ScStatus::COMPLETED, ScStatus::EARLY_TERMINATION => $Vehicle->updateStatus(
                                ve_status_service: VeStatusService::YES,
                                ve_status_rental: VeStatusRental::PENDING,
                                ve_status_dispatch: VeStatusDispatch::NOT_DISPATCHED
                            ),
                            default => null,
                        };
                    }

                    if (ScStatus::PENDING === $saleContract->sc_status->value && fake()->boolean(12)) {
                        VehicleTemp::factory()
                            ->for($saleContract)
                            ->for($Vehicle, 'CurrentVehicle')
                            ->for($Vehicles->where('ve_id', '!=', $Vehicle->ve_id)->random(), 'NewVehicle')
                            ->pendingReservation($saleContract)
                            ->create()
                        ;
                    }

                    if (
                        in_array($saleContract->sc_status->value, [ScStatus::SIGNED, ScStatus::COMPLETED, ScStatus::EARLY_TERMINATION], true)
                        && Carbon::parse($saleContract->sc_start_date)->lessThanOrEqualTo($this->periodEnd)
                    ) {
                        $dispatchAt = $saleContract->sc_start_date->copy()->setTime(fake()->numberBetween(8, 10), fake()->randomElement([0, 15, 30, 45]));
                        $returnAt   = match ($saleContract->sc_status->value) {
                            ScStatus::COMPLETED         => Carbon::parse($saleContract->sc_completed_at),
                            ScStatus::EARLY_TERMINATION => Carbon::parse($saleContract->sc_early_termination_at),
                            default                     => Carbon::parse($saleContract->sc_end_date)->endOfDay()->greaterThan($this->periodEnd)
                                ? $this->periodEnd->copy()
                                : Carbon::parse($saleContract->sc_end_date)->endOfDay(),
                        };
                        $startMileage  = (int) $Vehicle->ve_mileage;
                        $returnMileage = $this->returnMileage($startMileage, $saleContract, $returnAt);
                        if ($dispatchAt->greaterThan($returnAt)) {
                            $dispatchAt = $returnAt->copy()->subHour();
                        }

                        $dispatchInspection = VehicleInspection::factory()
                            ->for($Vehicle)
                            ->for($saleContract)
                            ->saleContractDispatch($dispatchAt, $startMileage)
                            ->create()
                        ;

                        $returnInspection = null;
                        if ($this->isClosedContract($saleContract)) {
                            $returnInspection = VehicleInspection::factory()
                                ->for($Vehicle)
                                ->for($saleContract)
                                ->saleContractReturn($returnAt, $returnMileage)
                                ->create()
                            ;
                        }

                        $vehicleUsageFactory = VehicleUsage::factory()
                            ->for($saleContract)
                            ->for($Vehicle)
                            ->for($dispatchInspection, 'VehicleInspectionStart')
                        ;
                        if ($returnInspection) {
                            $vehicleUsageFactory->for($returnInspection, 'VehicleInspectionEnd');
                        }
                        $vehicleUsage = $vehicleUsageFactory->create();

                        $isLongTerm = ScRentalType::LONG_TERM === $saleContract->sc_rental_type->value;
                        if (fake()->boolean($isLongTerm ? 16 : 6)) {
                            [$entryAt, $departureAt] = $this->workshopWindow($saleContract, $returnAt);
                            VehicleRepair::factory()
                                ->for($Vehicle)
                                ->for($saleContract)
                                ->for($vehicleCenters->random())
                                ->duringUsage($entryAt, $departureAt, $startMileage, $returnMileage)
                                ->create()
                            ;
                        }

                        if (fake()->boolean($isLongTerm ? 35 : 10)) {
                            [$entryAt, $departureAt] = $this->workshopWindow($saleContract, $returnAt);
                            VehicleMaintenance::factory()
                                ->for($Vehicle)
                                ->for($saleContract)
                                ->for($vehicleCenters->random())
                                ->duringUsage($entryAt, $departureAt, $startMileage, $returnMileage)
                                ->create()
                            ;
                        }

                        if (fake()->boolean(8)) {
                            VehicleManualViolation::factory()
                                ->for($Vehicle)
                                ->for($vehicleUsage)
                                ->duringUsage($this->usageDateTime($saleContract, $returnAt))
                                ->create()
                            ;
                        }

                        if (fake()->boolean($isLongTerm ? 28 : 12)) {
                            VehicleViolation::factory()
                                ->for($vehicleUsage)
                                ->duringUsage($Vehicle, $this->usageDateTime($saleContract, $returnAt))
                                ->create()
                            ;
                        }

                        if ($this->isClosedContract($saleContract)) {
                            SaleSettlement::factory()
                                ->for($saleContract)
                                ->closedContract($saleContract, $returnAt, $returnInspection)
                                ->create()
                            ;
                        }
                    }

                    if (!isset($vehicleInsuranceGenerated[$Vehicle->ve_id]) && fake()->boolean(15)) {
                        $insuranceStart = $this->periodStart->copy()->subMonths(fake()->numberBetween(0, 10))->addDays(fake()->numberBetween(0, 20));
                        VehicleInsurance::factory()
                            ->for($Vehicle)
                            ->annualForVehicle($Vehicle, $insuranceStart)
                            ->create()
                        ;
                        $vehicleInsuranceGenerated[$Vehicle->ve_id] = true;
                    }

                    if (!isset($vehicleScheduleGenerated[$Vehicle->ve_id]) && fake()->boolean(20)) {
                        $inspectionAt = $this->periodStart->copy()->addDays(fake()->numberBetween(0, (int) $this->periodStart->diffInDays($this->periodEnd)));
                        VehicleSchedule::factory()
                            ->for($Vehicle)
                            ->inspectionCycle($inspectionAt)
                            ->create()
                        ;
                        $vehicleScheduleGenerated[$Vehicle->ve_id] = true;
                    }
                }
            });

            $endTime = microtime(true);
            $this->info('end time: '.date('Y-m-d H:i:s'));
            $this->info(sprintf('elapsed time: %.2f s', $endTime - $startTime));
        }
    }

    private function isClosedContract(SaleContract $saleContract): bool
    {
        return in_array($saleContract->sc_status->value, [ScStatus::COMPLETED, ScStatus::EARLY_TERMINATION], true);
    }

    private function returnMileage(int $startMileage, SaleContract $saleContract, Carbon $actualEndAt): int
    {
        $days         = max(1, (int) Carbon::parse($saleContract->sc_start_date)->diffInDays($actualEndAt) + 1);
        $dailyMileage = ScRentalType::LONG_TERM === $saleContract->sc_rental_type->value
            ? fake()->numberBetween(35, 180)
            : fake()->numberBetween(60, 320);

        return $startMileage + ($days * $dailyMileage);
    }

    private function usageDateTime(SaleContract $saleContract, Carbon $actualEndAt): Carbon
    {
        $startAt = Carbon::parse($saleContract->sc_start_date)->startOfDay();
        $endAt   = $actualEndAt->copy();
        if ($endAt->lessThanOrEqualTo($startAt)) {
            return $startAt->copy()->setTime(fake()->numberBetween(9, 18), fake()->randomElement([0, 15, 30, 45]));
        }

        $candidate = Carbon::createFromTimestamp(fake()->numberBetween($startAt->timestamp, $endAt->timestamp))
            ->setTime(fake()->numberBetween(9, 20), fake()->randomElement([0, 15, 30, 45]))
        ;

        if ($candidate->greaterThan($endAt)) {
            return $endAt->copy();
        }
        if ($candidate->lessThan($startAt)) {
            return $startAt->copy();
        }

        return $candidate;
    }

    private function workshopWindow(SaleContract $saleContract, Carbon $actualEndAt): array
    {
        $entryAt     = $this->usageDateTime($saleContract, $actualEndAt);
        $departureAt = $entryAt->copy()->addHours(fake()->numberBetween(4, 72));
        if ($departureAt->greaterThan($actualEndAt)) {
            $departureAt = $actualEndAt->copy();
        }

        return [$entryAt, $departureAt];
    }
}
