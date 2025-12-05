<?php

namespace App\Console\Commands\Sys;

use App\Enum\Admin\AdmUserType;
use App\Enum\Admin\ArIsCustom;
use App\Http\Controllers\Admin\Admin\AdminController;
use App\Http\Controllers\Admin\Admin\AdminPermissionController;
use App\Http\Controllers\Admin\Admin\AdminRoleController;
use App\Http\Controllers\Admin\Config\Configuration0Controller;
use App\Http\Controllers\Admin\Config\Configuration1Controller;
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
use App\Http\Controllers\Admin\Payment\PaymentAccountController;
use App\Http\Controllers\Admin\Payment\PaymentController;
use App\Http\Controllers\Admin\Payment\PaymentTypeController;
use App\Http\Controllers\Admin\Payment\SaleOrderRentPaymentController;
use App\Http\Controllers\Admin\Payment\SaleOrderSignPaymentController;
use App\Http\Controllers\Admin\Risk\ExpiryDriverController;
use App\Http\Controllers\Admin\Risk\ExpiryVehicleController;
use App\Http\Controllers\Admin\Risk\VehicleForceTakeController;
use App\Http\Controllers\Admin\Risk\VehicleInsuranceController;
use App\Http\Controllers\Admin\Risk\VehicleScheduleController;
use App\Http\Controllers\Admin\Risk\ViolationCountController;
use App\Http\Controllers\Admin\Sale\BookingOrderController;
use App\Http\Controllers\Admin\Sale\BookingVehicleController;
use App\Http\Controllers\Admin\Sale\SaleOrderCancelController;
use App\Http\Controllers\Admin\Sale\SaleOrderController;
use App\Http\Controllers\Admin\Sale\SaleOrderTplController;
use App\Http\Controllers\Admin\Sale\SaleSettlementController;
use App\Http\Controllers\Admin\Sale\VehiclePreparationController;
use App\Http\Controllers\Admin\Sale\VehicleReplacementController;
use App\Http\Controllers\Admin\Vehicle\OneAccountController;
use App\Http\Controllers\Admin\Vehicle\VehicleController;
use App\Http\Controllers\Admin\Vehicle\VehicleInspectionController;
use App\Http\Controllers\Admin\Vehicle\VehicleManualViolationController;
use App\Http\Controllers\Admin\Vehicle\VehicleModelController;
use App\Http\Controllers\Admin\Vehicle\VehicleViolationController;
use App\Http\Controllers\Admin\VehicleService\ServiceCenterController;
use App\Http\Controllers\Admin\VehicleService\VehicleAccidentController;
use App\Http\Controllers\Admin\VehicleService\VehicleMaintenanceController;
use App\Http\Controllers\Admin\VehicleService\VehicleRepairController;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminRole;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportAdminAndRoles extends Command
{
    public const string role_system = '系统管理';

    public const string role_manager    = '经理';
    public const string role_driver_mgr = '驾管';
    public const string role_payment    = '财务';

    public const string role_sales       = '销售';
    public const string role_vehicle_mgr = '车管';

    public const string role_vehicle_service = '修理厂';

    protected $signature   = 'sys:import-admin-and-roles';
    protected $description = '导入应用内置角色';

    /** 导入的内置角色清单 */
    private array $builtinRoles = [
        self::role_vehicle_mgr     => [GpsDataController::class, IotDeviceBindingController::class, ExpiryDriverController::class, ExpiryVehicleController::class, VehicleForceTakeController::class, VehicleInsuranceController::class, VehicleScheduleController::class, ViolationCountController::class, ServiceCenterController::class, VehicleAccidentController::class, VehicleMaintenanceController::class, VehicleRepairController::class, OneAccountController::class, VehicleController::class, VehicleInspectionController::class, VehicleManualViolationController::class, VehicleModelController::class, VehicleViolationController::class, VehiclePreparationController::class],
        self::role_payment         => [InoutController::class, PaymentController::class, PaymentTypeController::class, SaleOrderRentPaymentController::class, SaleOrderSignPaymentController::class, VehiclePreparationController::class],
        self::role_sales           => [BookingOrderController::class, BookingVehicleController::class, SaleOrderController::class, SaleOrderCancelController::class, SaleOrderTplController::class, SaleSettlementController::class, VehicleReplacementController::class, CustomerController::class],
        self::role_vehicle_service => [VehicleAccidentController::class, VehicleMaintenanceController::class, VehicleRepairController::class],
        self::role_manager         => [Configuration0Controller::class, DocTplController::class, PaymentAccountController::class, AdminController::class, AdminPermissionController::class, AdminRoleController::class, OneAccountController::class, VehicleController::class, ServiceCenterController::class, SaleOrderController::class, CustomerController::class, DeliveryWecomGroupController::class, DeliveryWecomMemberController::class, DocTplController::class],
        self::role_system          => [Configuration0Controller::class, Configuration1Controller::class, ImportController::class, AdminPermissionController::class, DeliveryChannelController::class, DeliveryLogController::class],
    ];

    private array $mock_admins = [
        '演示经理'   => self::role_manager,
        '演示修理厂' => self::role_vehicle_service,
        '演示销售'   => self::role_sales,
        '演示车管'   => self::role_vehicle_mgr,
        '演示财务'   => self::role_payment,
    ];

    public function handle(): int
    {
        DB::transaction(function () {
            foreach ($this->builtinRoles as $name => $_permissions) {
                $role = AdminRole::query()->updateOrCreate(['name' => $name, 'guard_name' => 'web'], ['is_custom' => ArIsCustom::NO]);
                if ($_permissions) {
                    $permissions = array_map(function ($permission) {
                        $class_basename = class_basename($permission);

                        return preg_replace('/Controller$/i', '', $class_basename);
                    }, $_permissions);

                    $role->givePermissionTo($permissions);
                }
            }
            $this->info('内置角色导入完成（基于 name+guard_name 去重）。');

            if (config('setting.mock.enable')) {
                foreach ($this->mock_admins as $admin_name => $role_name) {
                    $admin = Admin::query()->updateOrCreate(['name' => $admin_name], ['user_type' => AdmUserType::MOCK]);

                    $role = AdminRole::query()->where(['name' => $role_name])->firstOrFail();
                    $admin->assignRole($role);
                }

                $this->info('演示用户导入完成（基于 name 去重）。');
            }
        });

        return self::SUCCESS;
    }
}
