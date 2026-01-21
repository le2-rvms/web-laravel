<?php

namespace App\Console\Commands\Dev;

use App\Enum\Admin\AUserType;
use App\Enum\Customer\CuType;
use App\Enum\Payment\PPtId;
use App\Enum\SaleContract\ScRentalType;
use App\Enum\SaleContract\ScStatus;
use App\Enum\Vehicle\VcStatus;
use App\Enum\Vehicle\VeStatusDispatch;
use App\Enum\Vehicle\VeStatusRental;
use App\Enum\Vehicle\VeStatusService;
use App\Enum\VehicleInspection\ViInspectionType;
use App\Http\Controllers\Admin\Sale\SaleContractController;
use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Customer\CustomerCompany;
use App\Models\Customer\CustomerIndividual;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentAccount;
use App\Models\Payment\PaymentInout;
use App\Models\Sale\SaleContract;
use App\Models\Sale\SaleSettlement;
use App\Models\Sale\VehicleTemp;
use App\Models\Vehicle\Vehicle;
use App\Models\Vehicle\VehicleAccident;
use App\Models\Vehicle\VehicleCenter;
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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Attribute\AsCommand;

#[\AllowDynamicProperties]
#[AsCommand(name: '_dev:mock-data:generate', description: 'Command description')]
class MockDataGenerate extends Command
{
    protected $signature = '_dev:mock-data:generate {--month=1-12}';

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
            VehicleTemp::query()->delete();
            VehicleCenter::query()->delete();
            VehicleAccident::query()->delete();
            VehicleViolation::query()->delete();
            VehicleForceTake::query()->delete();

            Vehicle::query()->delete();
            VehicleModel::query()->delete();

            SaleContract::query()->delete();
            VehiclePreparation::query()->delete();

            CustomerIndividual::query()->whereRaw("cui_cu_id not in (select cu_id from customers where cu_contact_name like '演示%')")->delete();
            CustomerCompany::query()->delete();
            Customer::query()->whereNotLike('cu_contact_name', '演示%')->delete();

            //            Vehicle::query()->update(['ve_status_service' => VeStatusService::YES, 've_status_rental' => VeStatusRental::LISTED, 've_status_dispatch' => VeStatusDispatch::NOT_DISPATCHED]);

            Admin::query()->where('a_user_type', '=', AUserType::COMMON)->delete();
            DB::table(config('permission.table_names.model_has_roles'))->whereRaw('model_id not in (select id from admins)')->delete();

            DB::statement('call reset_identity_sequences(?)', ['public']);
        });

        DB::transaction(function () {
            $auditSchema = config('setting.dblog.schema');

            foreach (config('setting.dblog.models') as $class_name => $pk) {
                /** @var Model $model */
                $model = new $class_name();
                $table = $model->getTable();

                DB::table("{$auditSchema}.{$table}")->delete();
            }
        });

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

        $vehicleCenters = VehicleCenter::query()->where('vc_status', '=', VcStatus::ENABLED)->get();

        for ($month = $month_form; $month <= $month_to; ++$month) {
            config(['setting.gen.month.current' => $month]);

            $this->info(str_repeat('#', 80));
            $this->info(sprintf("# \033[32mGenerating data for month %s \033[0m", $month));
            $this->info(str_repeat('#', 80));

            DB::transaction(function () use ($Vehicles, $customers, $vehicleCenters) {
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
                    $saleContract = SaleContract::factory()->for($Vehicle)->for($customer)->create();

                    if (ScRentalType::LONG_TERM === $saleContract->sc_rental_type->value) {
                        $payments = SaleContractController::callPaymentsOption($saleContract->toArray());
                        foreach ($payments as $payment) {
                            $saleContract->Payments()->create($payment);
                        }
                    } elseif (ScRentalType::SHORT_TERM === $saleContract->sc_rental_type->value) {
                        if (in_array($saleContract->sc_status->value, ScStatus::getSignAndAfter)) {
                            $types = PPtId::getFeeTypes(ScRentalType::SHORT_TERM);
                            foreach ($types as $type) {
                                if (fake()->boolean(75)) {
                                    Payment::factory()->for($saleContract)->create(['p_pt_id' => $type]);
                                }
                            }
                        }
                    }

                    switch ($saleContract->sc_status) {
                        case ScStatus::PENDING:
                            $Vehicle->updateStatus(ve_status_service: VeStatusService::YES, ve_status_rental: VeStatusRental::RESERVED, ve_status_dispatch: VeStatusDispatch::NOT_DISPATCHED);

                            break;

                        case ScStatus::CANCELLED:
                            $Vehicle->updateStatus(ve_status_service: VeStatusService::YES, ve_status_rental: VeStatusRental::LISTED, ve_status_dispatch: VeStatusDispatch::NOT_DISPATCHED);

                            break;

                        case ScStatus::SIGNED:
                            $Vehicle->updateStatus(ve_status_service: VeStatusService::YES, ve_status_rental: VeStatusRental::RENTED, ve_status_dispatch: VeStatusDispatch::DISPATCHED);

                            break;

                        case ScStatus::COMPLETED:
                        case ScStatus::EARLY_TERMINATION:
                            $Vehicle->updateStatus(ve_status_service: VeStatusService::YES, ve_status_rental: VeStatusRental::PENDING, ve_status_dispatch: VeStatusDispatch::NOT_DISPATCHED);

                            break;
                    }

                    if (ScStatus::PENDING === $saleContract->sc_status->value) {
                        VehicleTemp::factory()->for($saleContract)->for($Vehicle, 'CurrentVehicle')->for($Vehicles->random(), 'NewVehicle')->create();
                    }

                    if (in_array($saleContract->sc_status, [ScStatus::SIGNED, ScStatus::COMPLETED, ScStatus::EARLY_TERMINATION])) {
                        for ($groupSize = 0; $groupSize < 2; ++$groupSize) {
                            $vehicleInspection1 = VehicleInspection::factory()->for($Vehicle)->for($saleContract)->create(['vi_inspection_type' => ViInspectionType::SC_DISPATCH]);
                            $vehicleInspection2 = VehicleInspection::factory()->for($Vehicle)->for($saleContract)->create(['vi_inspection_type' => ViInspectionType::SC_RETURN]);

                            $VehicleUsage = VehicleUsage::factory()->for($saleContract)->for($Vehicle)->for($vehicleInspection1, 'VehicleInspectionStart')->for($vehicleInspection2, 'VehicleInspectionEnd')->create();
                        }

                        VehicleRepair::factory()->for($Vehicle)->for($saleContract)->for($vehicleCenters->random())->create();
                        VehicleMaintenance::factory()->for($Vehicle)->for($saleContract)->for($vehicleCenters->random())->create();

                        VehicleManualViolation::factory()->for($Vehicle)->for($VehicleUsage)->create();
                        VehicleViolation::factory()->for($Vehicle)->for($VehicleUsage)->create();
                    }

                    if (in_array($saleContract->sc_status, [ScStatus::COMPLETED, ScStatus::EARLY_TERMINATION])) {
                        SaleSettlement::factory()->for($saleContract)->create();
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
