<?php

namespace Tests\Http\Controllers\Admin;

use App\Enum\Config\ImportConfig;
use App\Enum\Vehicle\VcStatus;
use App\Http\Controllers\Admin\Config\ImportController;
use App\Models\Customer\Customer;
use App\Models\Customer\CustomerCompany;
use App\Models\Customer\CustomerIndividual;
use App\Models\Payment\Payment;
use App\Models\Sale\SaleContract;
use App\Models\Vehicle\Vehicle;
use App\Models\Vehicle\VehicleCenter;
use App\Models\Vehicle\VehicleRepair;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

/**
 * @internal
 */
#[CoversNothing]
class ImportControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DB::transaction(function () {
            VehicleRepair::query()->whereRaw("vr_ve_id in (select ve_id from vehicles where ve_plate_no like '测%' )")->delete();

            Payment::query()->whereRaw("p_sc_id in (select sc_id from sale_contracts where sc_no like 'TMP%')")->delete();
            SaleContract::query()->whereLike('sc_no', 'TMP%')->delete();

            Vehicle::query()->whereLike('ve_plate_no', '测%')->delete();
            CustomerIndividual::query()->whereRaw("cui_cu_id in (select cu_id from customers where cu_contact_name like '测%')")->delete();
            CustomerCompany::query()->whereRaw("cuc_cu_id in (select cu_id from customers where cu_contact_name like '测%')")->delete();
            Customer::query()->whereLike('cu_contact_name', '测%')->delete();

            VehicleCenter::query()->insertOrIgnore(['vc_name' => '演示修理厂', 'vc_status' => VcStatus::ENABLED]);
        });
    }

    public function testUpdate()
    {
        foreach (ImportConfig::keys() as $model) {
            $model_name = class_basename($model);

            $filename = "template_{$model_name}.xlsx";
            $path     = base_path("tests/Files/{$filename}");
            $this->assertFileExists($path, '请确认文件存在');

            $file = new UploadedFile(
                $path,                // 本地文件路径
                $filename,         // 客户端原始文件名
                null,          // MIME 类型
                null,                 // 文件大小（null 则自动从文件读取）
                true                  // 是否为 test 模式（绕过 is_uploaded_file 检查）
            );

            $response_upload = $this->postJson(
                action([ImportController::class, 'upload']),
                ['field_name' => 'import_file', 'file' => $file]
            );

            $response_upload_data = $response_upload->original['data'];

            $response11 = $this->putJson(
                action([ImportController::class, 'update']),
                [
                    'app_model_name' => $model,
                    'import_file'    => [
                        'name'    => 'a',
                        'extname' => 'xlsx',
                        'size'    => '123',
                        'path_'   => $response_upload_data['filepath'],
                    ],
                ]
            );
            $response11->assertStatus(200);

            // $this->assertDatabaseHas('customers', ['cu_contact_name' => '苏妹妹']);
        }
    }

    public function testTemplateDownload()
    {
        foreach (ImportConfig::keys() as $model) {
            $resp = $this->getJson(
                action([ImportController::class, 'template'], ['app_model_name' => $model]),
            );
            $resp->assertStatus(200)
                ->assertJsonStructure(['data' => ['url']])
            ;
            echo json_encode($resp->original)."\n";
        }
    }

    public function testShow()
    {
        $resp = $this->getJson(
            action([ImportController::class, 'show'])
        );
        $resp->assertOk();
        //                    ->assertJsonStructure(['data' => ['url']]);
        echo json_encode($resp->original)."\n";
    }
}
