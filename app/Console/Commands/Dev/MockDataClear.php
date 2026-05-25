<?php

namespace App\Console\Commands\Dev;

use App\Enum\Admin\AUserType;
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
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: '_dev:mock-data:clear', description: 'Clear development mock data')]
class MockDataClear extends Command
{
    protected $signature = '_dev:mock-data:clear';

    public function handle(): void
    {
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

            Admin::query()->where('a_user_type', '=', AUserType::COMMON)->delete();
            DB::table(config('permission.table_names.model_has_roles'))->whereRaw('model_id not in (select id from admins)')->delete();

            DB::statement('call reset_identity_sequences(?)', ['public']);
        });

        DB::transaction(function () {
            $auditSchema = config('setting.dblog.schema');

            foreach (config('setting.dblog.models') as $class_name) {
                /** @var Model $model */
                $model = new $class_name();
                $table = $model->getTable();

                DB::table("{$auditSchema}.{$table}")->delete();
            }
        });
    }
}
