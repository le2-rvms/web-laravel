<?php

namespace App\Console\Commands\Sys;

use App\Attributes\PermissionAction;
use App\Enum\Admin\ArIsCustom;
use App\Enum\Admin\AUserType;
use App\Http\Controllers\Admin\Admin\AdminController;
use App\Http\Controllers\Admin\Admin\AdminPermissionController;
use App\Http\Controllers\Admin\Admin\AdminRoleController;
use App\Http\Controllers\Admin\Admin\AdminTeamController;
use App\Http\Controllers\Admin\Config\ConfigurationAppController;
use App\Http\Controllers\Admin\Config\ConfigurationSysController;
use App\Http\Controllers\Admin\Config\DocTplController;
use App\Http\Controllers\Admin\Config\ImportController;
use App\Http\Controllers\Admin\Customer\CustomerController;
use App\Http\Controllers\Admin\Delivery\DeliveryChannelController;
use App\Http\Controllers\Admin\Delivery\DeliveryLogController;
use App\Http\Controllers\Admin\Delivery\DeliveryWecomGroupController;
use App\Http\Controllers\Admin\Delivery\DeliveryWecomMemberController;
use App\Http\Controllers\Admin\Device\GpsDataController;
use App\Http\Controllers\Admin\Device\IotDeviceBindingController;
use App\Http\Controllers\Admin\Payment\InoutController;
use App\Http\Controllers\Admin\Payment\PaymentController;
use App\Http\Controllers\Admin\Payment\PaymentTypeController;
use App\Http\Controllers\Admin\Payment\SaleContractRentPaymentController;
use App\Http\Controllers\Admin\Payment\SaleContractSignPaymentController;
use App\Http\Controllers\Admin\Risk\ExpiryDriverController;
use App\Http\Controllers\Admin\Risk\ExpiryVehicleController;
use App\Http\Controllers\Admin\Risk\VehicleForceTakeController;
use App\Http\Controllers\Admin\Risk\VehicleInsuranceController;
use App\Http\Controllers\Admin\Risk\VehicleScheduleController;
use App\Http\Controllers\Admin\Risk\ViolationCountController;
use App\Http\Controllers\Admin\Sale\BookingOrderController;
use App\Http\Controllers\Admin\Sale\BookingVehicleController;
use App\Http\Controllers\Admin\Sale\SaleContractCancelController;
use App\Http\Controllers\Admin\Sale\SaleContractController;
use App\Http\Controllers\Admin\Sale\SaleContractTplController;
use App\Http\Controllers\Admin\Sale\SaleContractVehicleChangeController;
use App\Http\Controllers\Admin\Sale\SaleContractVehicleChangePaymentController;
use App\Http\Controllers\Admin\Sale\SaleSettlementApproveController;
use App\Http\Controllers\Admin\Sale\SaleSettlementController;
use App\Http\Controllers\Admin\Sale\VehiclePreparationController;
use App\Http\Controllers\Admin\Sale\VehicleTmpController;
use App\Http\Controllers\Admin\Vehicle\OneAccountController;
use App\Http\Controllers\Admin\Vehicle\VehicleController;
use App\Http\Controllers\Admin\Vehicle\VehicleInspectionController;
use App\Http\Controllers\Admin\Vehicle\VehicleManualViolationController;
use App\Http\Controllers\Admin\Vehicle\VehicleModelController;
use App\Http\Controllers\Admin\Vehicle\VehicleViolationController;
use App\Http\Controllers\Admin\VehicleService\VehicleAccidentController;
use App\Http\Controllers\Admin\VehicleService\VehicleCenterController;
use App\Http\Controllers\Admin\VehicleService\VehicleMaintenanceController;
use App\Http\Controllers\Admin\VehicleService\VehicleRepairController;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminRole;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AdminRoleImport extends Command
{
    protected $signature   = '_sys:admin-role:import';
    protected $description = '导入应用内置角色';

    /** 导入的内置角色清单 */
    private array $builtinRoles = [
        AdminRole::role_vehicle_mgr     => [GpsDataController::class => [PermissionAction::READ], IotDeviceBindingController::class => [PermissionAction::READ, PermissionAction::WRITE], ExpiryDriverController::class => [PermissionAction::READ], ExpiryVehicleController::class => [PermissionAction::READ], VehicleForceTakeController::class => [PermissionAction::READ, PermissionAction::WRITE], VehicleInsuranceController::class => [PermissionAction::READ, PermissionAction::WRITE], VehicleScheduleController::class => [PermissionAction::READ, PermissionAction::WRITE], ViolationCountController::class => [PermissionAction::READ], VehicleCenterController::class => [PermissionAction::READ, PermissionAction::WRITE], VehicleAccidentController::class => [PermissionAction::READ, PermissionAction::WRITE], VehicleMaintenanceController::class => [PermissionAction::READ, PermissionAction::WRITE], VehicleRepairController::class => [PermissionAction::READ, PermissionAction::WRITE], OneAccountController::class => [PermissionAction::READ, PermissionAction::WRITE], VehicleController::class => [PermissionAction::READ, PermissionAction::WRITE], VehicleInspectionController::class => [PermissionAction::READ, PermissionAction::WRITE], VehicleManualViolationController::class => [PermissionAction::READ, PermissionAction::WRITE], VehicleModelController::class => [PermissionAction::READ, PermissionAction::WRITE], VehicleViolationController::class => [PermissionAction::READ, PermissionAction::WRITE], VehiclePreparationController::class => [PermissionAction::READ, PermissionAction::WRITE]],
        AdminRole::role_driver_mgr      => [],
        AdminRole::role_payment         => [InoutController::class => [PermissionAction::READ], PaymentController::class => [PermissionAction::READ, PermissionAction::WRITE], PaymentTypeController::class => [PermissionAction::WRITE], SaleContractRentPaymentController::class => [PermissionAction::WRITE], SaleContractSignPaymentController::class => [PermissionAction::WRITE], VehiclePreparationController::class => [PermissionAction::READ, PermissionAction::WRITE], SaleSettlementApproveController::class => [PermissionAction::WRITE]],
        AdminRole::role_sales           => [BookingOrderController::class => [PermissionAction::READ, PermissionAction::WRITE], BookingVehicleController::class => [PermissionAction::READ, PermissionAction::WRITE], SaleContractController::class => [PermissionAction::READ, PermissionAction::WRITE], SaleContractCancelController::class => [PermissionAction::WRITE], SaleContractTplController::class => [PermissionAction::READ, PermissionAction::WRITE], SaleContractVehicleChangeController::class => [PermissionAction::WRITE], SaleContractVehicleChangePaymentController::class => [PermissionAction::WRITE], SaleSettlementController::class => [PermissionAction::READ, PermissionAction::WRITE], VehicleTmpController::class => [PermissionAction::READ, PermissionAction::WRITE], CustomerController::class => [PermissionAction::READ, PermissionAction::WRITE]],
        AdminRole::role_vehicle_service => [VehicleAccidentController::class => [PermissionAction::READ, PermissionAction::WRITE], VehicleMaintenanceController::class => [PermissionAction::READ, PermissionAction::WRITE], VehicleRepairController::class => [PermissionAction::READ, PermissionAction::WRITE]],
        AdminRole::role_manager         => [ConfigurationAppController::class => [PermissionAction::READ, PermissionAction::WRITE], AdminController::class => [PermissionAction::READ, PermissionAction::WRITE], AdminRoleController::class => [PermissionAction::READ, PermissionAction::WRITE], VehicleCenterController::class => [PermissionAction::READ, PermissionAction::WRITE], DeliveryWecomGroupController::class => [PermissionAction::READ, PermissionAction::WRITE], DeliveryWecomMemberController::class => [PermissionAction::READ, PermissionAction::WRITE], DocTplController::class => [PermissionAction::READ, PermissionAction::WRITE], DeliveryChannelController::class => [PermissionAction::READ, PermissionAction::WRITE], DeliveryLogController::class => [PermissionAction::READ], AdminTeamController::class => [PermissionAction::READ, PermissionAction::WRITE]],
        AdminRole::role_system          => [ConfigurationSysController::class => [PermissionAction::READ, PermissionAction::WRITE], ImportController::class => [PermissionAction::WRITE], AdminPermissionController::class => [PermissionAction::READ]],
    ];

    private array $mock_admins = [
        '演示经理'   => AdminRole::role_manager,
        '演示修理厂' => AdminRole::role_vehicle_service,
        '演示销售'   => AdminRole::role_sales,
        '演示车管'   => AdminRole::role_vehicle_mgr,
        '演示驾管'   => AdminRole::role_driver_mgr,
        '演示财务'   => AdminRole::role_payment,
    ];

    public function handle(): int
    {
        DB::transaction(function () {
            foreach ($this->builtinRoles as $name => $_permissions) {
                $role = AdminRole::query()->updateOrCreate(['name' => $name, 'guard_name' => 'web'], ['ar_is_custom' => ArIsCustom::NO]);
                if ($_permissions) {
                    $permissions = [];
                    foreach ($_permissions as $permission => $suffixes) {
                        $class_basename = class_basename($permission);

                        $class_basename_ = preg_replace('/Controller$/i', '', $class_basename);

                        foreach ($suffixes as $suffix) {
                            $permissions[] = $class_basename_.'::'.$suffix;
                        }
                    }

                    $role->givePermissionTo($permissions);
                }
            }
            $this->info('内置角色导入完成（基于 name+guard_name 去重）。');

            if (config('setting.mock.enable')) {
                foreach ($this->mock_admins as $admin_name => $role_name) {
                    $admin = Admin::query()->updateOrCreate(['name' => $admin_name], ['a_user_type' => AUserType::MOCK]);

                    $role = AdminRole::query()->where(['name' => $role_name])->firstOrFail();
                    $admin->assignRole($role);
                }

                $this->info('演示用户导入完成（基于 name 去重）。');
            }
        });

        return self::SUCCESS;
    }
}
