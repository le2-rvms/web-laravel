<?php

use App\Http\Controllers\Admin\_\HistoryController;
use App\Http\Controllers\Admin\_\LoginController;
use App\Http\Controllers\Admin\_\MockController;
use App\Http\Controllers\Admin\_\PasswordResetController;
use App\Http\Controllers\Admin\_\StatisticsController;
use App\Http\Controllers\Admin\Admin\AdminController;
use App\Http\Controllers\Admin\Admin\AdminPermissionController;
use App\Http\Controllers\Admin\Admin\AdminProfileController;
use App\Http\Controllers\Admin\Admin\AdminRoleController;
use App\Http\Controllers\Admin\Admin\AdminTeamController;
use App\Http\Controllers\Admin\Config\CompanyController;
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
use App\Http\Controllers\Admin\File\StorageController;
use App\Http\Controllers\Admin\Payment\InoutController;
use App\Http\Controllers\Admin\Payment\PaymentAccountController;
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
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\TemporaryAdmin;
use Illuminate\Support\Facades\Route;

Route::prefix('no-auth')->group(function () {
    Route::post('/login', [LoginController::class, 'login']);
    Route::resource('password', PasswordResetController::class)->only(['store', 'update']);

    if (config('setting.mock.enable')) {
        Route::apiResource('/mock', MockController::class)->only(['index', 'update']);
    }

    Route::get('/storage/tmp/{filename}', [StorageController::class, 'downloadTmp'])->name('storage.tmp')->middleware('signed');
    Route::get('/storage/share/{filename}', [StorageController::class, 'downloadShare'])->name('storage.share')->middleware('signed');
});

Route::group(['middleware' => [config('setting.mock.enable') ? TemporaryAdmin::class : 'auth:sanctum', CheckPermission::class]], function () {
    //    Route::apiResource('file', FileController::class);
    //    Route::apiResource('file-name', FileNameController::class);

    Route::resource('statistics', StatisticsController::class)->only('index');

    Route::get('/history/{class_basename}/{pk}', HistoryController::class);

    Route::resource('admin-permissions', AdminPermissionController::class);
    Route::resource('admin-roles', AdminRoleController::class);
    Route::resource('admin-teams', AdminTeamController::class);
    Route::resource('admin', AdminController::class);

    Route::singleton('admin-profile', AdminProfileController::class);

    Route::resource('configuration-sys', ConfigurationSysController::class)->parameters(['configuration-sys' => 'configuration']);
    Route::resource('configuration-app', ConfigurationAppController::class)->parameters(['configuration-app' => 'configuration']);

    Route::put('doc-tpls/{doc_tpl}/status', [DocTplController::class, 'status']);
    Route::get('doc-tpls/{doc_tpl}/preview', [DocTplController::class, 'preview']);
    Route::post('doc-tpls/upload', [DocTplController::class, 'upload']);
    Route::resource('doc-tpls', DocTplController::class);

    Route::singleton('company', CompanyController::class);

    Route::apiSingleton('payment-type', PaymentTypeController::class);

    Route::resource('vehicle-models', VehicleModelController::class);

    Route::resource('one-accounts', OneAccountController::class);

    Route::resource('payment-accounts', PaymentAccountController::class);

    Route::put('sale-contract-tpls/{order_tpl}/status', [SaleContractTplController::class, 'status']);
    Route::post('sale-contract-tpls/upload', [SaleContractTplController::class, 'upload']);
    Route::resource('sale-contract-tpls', SaleContractTplController::class);

    Route::resource('vehicle-centers', VehicleCenterController::class);

    Route::resource('delivery-channels', DeliveryChannelController::class);

    Route::resource('delivery-logs', DeliveryLogController::class);

    Route::apiSingleton('delivery-wecom-group', DeliveryWecomGroupController::class);
    Route::apiSingleton('delivery-wecom-member', DeliveryWecomMemberController::class);

    Route::get('import/template', [ImportController::class, 'template']);
    Route::post('import/upload', [ImportController::class, 'upload']);
    Route::singleton('import', ImportController::class);

    Route::post('vehicles/upload', [VehicleController::class, 'upload']);
    Route::resource('vehicles', VehicleController::class);

    Route::get('vehicle-inspections/sale-contracts-option', [VehicleInspectionController::class, 'saleContractsOption']);
    Route::post('vehicle-inspections/upload', [VehicleInspectionController::class, 'upload']);
    Route::get('vehicle-inspections/{vehicle_inspection}/doc', [VehicleInspectionController::class, 'doc']);
    Route::resource('vehicle-inspections', VehicleInspectionController::class);

    Route::resource('vehicle-manual-violations', VehicleManualViolationController::class);

    Route::resource('vehicle-violations', VehicleViolationController::class);

    Route::get('vehicle-repairs/sale-contracts-option', [VehicleRepairController::class, 'saleContractsOption']);
    Route::post('vehicle-repairs/upload', [VehicleRepairController::class, 'upload']);
    Route::resource('vehicle-repairs', VehicleRepairController::class);

    Route::get('vehicle-maintenances/sale-contracts-option', [VehicleMaintenanceController::class, 'saleContractsOption']);
    Route::post('vehicle-maintenances/upload', [VehicleMaintenanceController::class, 'upload']);
    Route::resource('vehicle-maintenances', VehicleMaintenanceController::class);

    Route::get('vehicle-accidents/sale-contracts-option', [VehicleAccidentController::class, 'saleContractsOption']);
    Route::post('vehicle-accidents/upload', [VehicleAccidentController::class, 'upload']);
    Route::resource('vehicle-accidents', VehicleAccidentController::class);

    Route::post('customers/upload', [CustomerController::class, 'upload']);
    Route::resource('customers', CustomerController::class);

    Route::resource('vehicle-preparations', VehiclePreparationController::class)->only('index', 'create', 'store', 'destroy');

    Route::get('sale-contracts/payments-option', [SaleContractController::class, 'paymentsOption']);
    Route::get('sale-contracts/{sale_contract}/doc', [SaleContractController::class, 'doc']);
    Route::get('sale-contracts/generate/{sale_contract_tpl}', [SaleContractController::class, 'generate']);
    Route::post('sale-contracts/upload', [SaleContractController::class, 'upload']);
    Route::resource('sale-contracts', SaleContractController::class);

    Route::apiSingleton('sale-contracts.cancel', SaleContractCancelController::class);

    Route::post('vehicle-tmp/upload', [VehicleTmpController::class, 'upload']);
    Route::resource('vehicle-tmp', VehicleTmpController::class);

    Route::post('sale-settlement/upload', [SaleSettlementController::class, 'upload']);
    Route::get('sale-settlement/{sale_settlement}/doc', [SaleSettlementController::class, 'doc']);
    Route::resource('sale-settlement', SaleSettlementController::class);

    Route::apiSingleton('sale-settlement.approve', SaleSettlementApproveController::class);

    Route::post('booking-vehicles/upload', [BookingVehicleController::class, 'upload']);
    Route::put('booking-vehicles/{booking_vehicle}/status', [BookingVehicleController::class, 'status']);
    Route::resource('booking-vehicles', BookingVehicleController::class);

    Route::get('booking-orders/{booking_vehicle}/generate', [BookingOrderController::class, 'generate']);
    Route::resource('booking-orders', BookingOrderController::class);

    // sale
    Route::get('payments/{payment}/doc', [PaymentController::class, 'doc']);
    Route::put('payments/{payment}/undo', [PaymentController::class, 'undo']); // 退还
    Route::resource('payments', PaymentController::class);

    Route::resource('inouts', InoutController::class)->only('index');

    Route::get('sale-contract/{sc_id}/sign-pay/create', [SaleContractSignPaymentController::class, 'create'])->where('sc_id', '[0-9]+');
    Route::resource('sale-contract.sign-pay', SaleContractSignPaymentController::class)->only('create', 'store');

    Route::get('sale-contract/{sc_id}/rent-pay/create', [SaleContractRentPaymentController::class, 'create'])->where('sc_id', '[0-9]+');
    Route::resource('sale-contract.rent-pay', SaleContractRentPaymentController::class)->only('create', 'store');

    // risk
    Route::post('vehicle-schedules/upload', [VehicleScheduleController::class, 'upload']);
    Route::get('vehicle-schedules/st_vehicle', [VehicleScheduleController::class, 'st_vehicle']);
    Route::resource('vehicle-schedules', VehicleScheduleController::class);

    Route::post('vehicle-insurances/upload', [VehicleInsuranceController::class, 'upload']);
    Route::resource('vehicle-insurances', VehicleInsuranceController::class);

    Route::post('vehicle-force-takes/upload', [VehicleForceTakeController::class, 'upload']);
    Route::resource('vehicle-force-takes', VehicleForceTakeController::class);

    Route::resource('expiry-drivers', ExpiryDriverController::class)->only('index');

    Route::resource('violation-counts', ViolationCountController::class)->only('index');

    Route::resource('expiry-vehicles', ExpiryVehicleController::class)->only('index');

    // device
    Route::resource('iot-device-bindings', IotDeviceBindingController::class);
    Route::get('gps-data/history/{vehicle}', [GpsDataController::class, 'history']);
});
