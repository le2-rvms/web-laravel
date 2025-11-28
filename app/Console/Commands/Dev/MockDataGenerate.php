<?php

namespace App\Console\Commands\Dev;

use App\Enum\Customer\CuCuType;
use App\Enum\Payment\RpPtId;
use App\Enum\Sale\SoOrderStatus;
use App\Enum\Sale\SoRentalType;
use App\Enum\Vehicle\ScScStatus;
use App\Enum\Vehicle\VeStatusDispatch;
use App\Enum\Vehicle\VeStatusRental;
use App\Enum\Vehicle\VeStatusService;
use App\Enum\Vehicle\ViInspectionType;
use App\Http\Controllers\Admin\Sale\SaleOrderController;
use App\Models\Customer\Customer;
use App\Models\Customer\CustomerIndividual;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentAccount;
use App\Models\Payment\PaymentInout;
use App\Models\Sale\SaleOrder;
use App\Models\Sale\SaleSettlement;
use App\Models\Sale\VehicleReplacement;
use App\Models\Vehicle\ServiceCenter;
use App\Models\Vehicle\Vehicle;
use App\Models\Vehicle\VehicleAccident;
use App\Models\Vehicle\VehicleForceTake;
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
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Attribute\AsCommand;

#[\AllowDynamicProperties]
#[AsCommand(name: '_dev:mock-data:generate', description: 'Command description')]
class MockDataGenerate extends Command
{
    protected $signature   = '_dev:mock-data:generate {--month=1-12}';
    protected $description = 'Command description';

    public function handle(): void
    {
        ini_set('memory_limit', '2G');

        $startTime = microtime(true);
        $this->info('start time: '.date('Y-m-d H:i:s'));

        [$month_form,$month_to] = explode('-', $this->option('month'));

        // 先删除数据

        DB::transaction(function () {
            PaymentAccount::query()->update(['pa_balance' => '0']);
            PaymentInout::query()->delete();
            Payment::query()->delete();

            VehicleSchedule::query()->delete();
            VehicleManualViolation::query()->delete();
            VehicleMaintenance::query()->delete();
            VehicleInsurance::query()->delete();
            SaleSettlement::query()->delete();
            VehicleRepair::query()->delete();
            VehicleUsage::query()->delete();
            VehicleInspection::query()->delete();
            VehicleReplacement::query()->delete();
            ServiceCenter::query()->delete();
            VehicleAccident::query()->delete();
            VehicleViolation::query()->delete();

            Vehicle::query()->delete();
            VehicleModel::query()->delete();

            SaleOrder::query()->delete();
            VehiclePreparation::query()->delete();

            VehicleForceTake::query()->delete();

            CustomerIndividual::query()->whereRaw("cu_id not in (select cu_id from customers where contact_name like '演示%')")->delete();
            Customer::query()->whereNotLike('contact_name', '演示%')->delete();

            //            Vehicle::query()->update(['status_service' => VeStatusService::YES, 'status_rental' => VeStatusRental::LISTED, 'status_dispatch' => VeStatusDispatch::NOT_DISPATCHED]);

            DB::statement('call reset_identity_sequences(?)', ['public']);
        });

        DB::transaction(function () {
            VehicleModel::factory()->count(20)->create();

            Vehicle::factory()->count(500)->create();

            ServiceCenter::factory()->count(5)->create();

            /** @var Collection $customers */
            $customers = Customer::factory()->count(20)->create();
            foreach ($customers as $customer) {
                if (CuCuType::INDIVIDUAL === $customer->cu_type) {
                    CustomerIndividual::factory()->for($customer)->create();
                }
            }
        });

        $Vehicles  = Vehicle::query()->get();
        $customers = Customer::query()->get();

        $serviceCenters = ServiceCenter::query()->where('sc_status', '=', ScScStatus::ENABLED)->get();

        for ($month = $month_form; $month <= $month_to; ++$month) {
            config(['setting.gen.month.current' => $month]);

            $this->info(str_repeat('#', 80));
            $this->info(sprintf("# \033[32mGenerating data for month %s \033[0m", $month));
            $this->info(str_repeat('#', 80));

            DB::transaction(function () use ($Vehicles, $customers, $serviceCenters) {
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

                    /** @var SaleOrder $SaleOrder */
                    $SaleOrder = SaleOrder::factory()->for($Vehicle)->for($customer)->create();

                    if (SoRentalType::LONG_TERM === $SaleOrder->rental_type->value) {
                        $payments = SaleOrderController::callPaymentsOption($SaleOrder->toArray());
                        foreach ($payments as $payment) {
                            $SaleOrder->Payments()->create($payment);
                        }
                    } elseif (SoRentalType::SHORT_TERM === $SaleOrder->rental_type->value) {
                        if (in_array($SaleOrder->order_status->value, SoOrderStatus::getSignAndAfter)) {
                            $types = RpPtId::getFeeTypes(SoRentalType::SHORT_TERM);
                            foreach ($types as $type) {
                                if (fake()->boolean(75)) {
                                    Payment::factory()->for($SaleOrder)->create(['pt_id' => $type]);
                                }
                            }
                        }
                    }

                    switch ($SaleOrder->order_status) {
                        case SoOrderStatus::PENDING:
                            $Vehicle->updateStatus(status_service: VeStatusService::YES, status_rental: VeStatusRental::RESERVED, status_dispatch: VeStatusDispatch::NOT_DISPATCHED);

                            break;

                        case SoOrderStatus::CANCELLED:
                            $Vehicle->updateStatus(status_service: VeStatusService::YES, status_rental: VeStatusRental::LISTED, status_dispatch: VeStatusDispatch::NOT_DISPATCHED);

                            break;

                        case SoOrderStatus::SIGNED:
                            $Vehicle->updateStatus(status_service: VeStatusService::YES, status_rental: VeStatusRental::RENTED, status_dispatch: VeStatusDispatch::DISPATCHED);

                            break;

                        case SoOrderStatus::COMPLETED:
                        case SoOrderStatus::EARLY_TERMINATION:
                            $Vehicle->updateStatus(status_service: VeStatusService::YES, status_rental: VeStatusRental::PENDING, status_dispatch: VeStatusDispatch::NOT_DISPATCHED);

                            break;
                    }

                    if (SoOrderStatus::PENDING === $SaleOrder->order_status) {
                        VehicleReplacement::factory()->for($SaleOrder)->for($Vehicle, 'CurrentVehicle')->for($Vehicles->random(), 'NewVehicle')->create();
                    }

                    if (in_array($SaleOrder->order_status, [SoOrderStatus::SIGNED, SoOrderStatus::COMPLETED, SoOrderStatus::EARLY_TERMINATION])) {
                        for ($groupSize = 0; $groupSize < 2; ++$groupSize) {
                            $vehicleInspection1 = VehicleInspection::factory()->for($Vehicle)->for($SaleOrder)->create(['inspection_type' => ViInspectionType::DISPATCH]);
                            $vehicleInspection2 = VehicleInspection::factory()->for($Vehicle)->for($SaleOrder)->create(['inspection_type' => ViInspectionType::RETURN]);

                            $VehicleUsage = VehicleUsage::factory()->for($SaleOrder)->for($Vehicle)->for($vehicleInspection1, 'VehicleInspectionStart')->for($vehicleInspection2, 'VehicleInspectionEnd')->create();
                        }

                        VehicleRepair::factory()->for($Vehicle)->for($SaleOrder)->for($serviceCenters->random())->create();
                        VehicleMaintenance::factory()->for($Vehicle)->for($SaleOrder)->for($serviceCenters->random())->create();

                        VehicleManualViolation::factory()->for($Vehicle)->for($VehicleUsage)->create();
                        VehicleViolation::factory()->for($Vehicle)->for($VehicleUsage)->create();
                    }

                    if (in_array($SaleOrder->order_status, [SoOrderStatus::COMPLETED, SoOrderStatus::EARLY_TERMINATION])) {
                        SaleSettlement::factory()->for($SaleOrder)->create();
                    }

                    VehicleInsurance::factory()->for($Vehicle)->create();

                    VehicleSchedule::factory()->for($Vehicle)->create();
                }
            });

            $endTime = microtime(true);
            $this->info('end time: '.date('Y-m-d H:i:s'));
            $this->info(sprintf('elapsed time: %.2f s', $endTime - $startTime));
        }
    }
}
