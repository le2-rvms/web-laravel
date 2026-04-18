<?php

namespace Tests\Http\Controllers\Admin\Config;

use App\Http\Controllers\Admin\Config\CompanyController;
use App\Http\Middleware\CheckAdminIsMock;
use App\Models\_\Company;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

/**
 * @internal
 */
#[CoversNothing]
class CompanyControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // 测试场景下去掉该中间件影响
        //        $this->withoutMiddleware(CheckAdminIsMock::class);

        Company::query()->delete();

        $this->company = Company::factory()->create([
            'cp_name'  => '-',
            'cp_phone' => '-',
        ]);
    }

    public function testShowReturnsFirstCompany(): void
    {
        Company::query()->updateOrCreate([
            'cp_id' => $this->company->cp_id,
        ], [
            'cp_name'  => 'Acme Co',
            'cp_phone' => '123456',
        ]);

        $res = $this->getJson(action([CompanyController::class, 'show']));

        $res->assertOk()
            // 不强依赖响应顶层结构，只断言字段片段存在即可
            ->assertJsonFragment([
                'cp_name'  => 'Acme Co',
                'cp_phone' => '123456',
            ])
        ;
    }

    public function testEditReturnsFirstCompany(): void
    {
        Company::query()->delete();

        $res = $this->getJson(action([CompanyController::class, 'edit']));

        $res->assertOk()
            ->assertJsonFragment([
            ])
        ;
    }

    public function testUpdateRequiresCpName(): void
    {
        $res = $this->putJson(action([CompanyController::class, 'update']), [
            // 故意缺少 cp_name
        ]);

        $res->assertStatus(422)
            ->assertJsonValidationErrors(['cp_name'])
        ;
    }

    public function testUpdateValidatesNumericFields(): void
    {
        $res = $this->putJson(action([CompanyController::class, 'update']), [
            'cp_name'      => 'Acme Co',
            'cp_longitude' => 'not-a-number',
            'cp_latitude'  => 'also-bad',
        ]);

        $res->assertStatus(422)
            ->assertJsonValidationErrors(['cp_longitude', 'cp_latitude'])
        ;
    }

    public function testUpdateCreatesRecordWhenEmpty(): void
    {
        $payload = [
            'cp_name'    => 'Acme Co',
            'cp_address' => 'Somewhere',
            'cp_phone'   => '123456',
        ];

        $res = $this->putJson(action([CompanyController::class, 'update']), $payload);

        $res->assertOk()
            ->assertJsonFragment(['cp_name' => 'Acme Co'])
        ;

        $this->assertDatabaseHas((new Company())->getTable(), [
            'cp_name'    => 'Acme Co',
            'cp_address' => 'Somewhere',
            'cp_phone'   => '123456',
        ]);

        $this->assertSame(1, Company::query()->count());
    }

    /**
     * 期望“单例公司信息”被覆盖更新而不是新增第二行。
     *
     * 注意：以当前控制器实现（updateOrCreate($input)）来看，如果输入与现有行不完全匹配
     * 可能会插入新行，导致断言失败——这将暴露潜在问题（欢迎用该用例驱动修复）。
     */
    public function testUpdateOverwritesSingletonInsteadOfCreatingNewRow(): void
    {
        $payload = [
            'cp_name'    => 'New Name',
            'cp_phone'   => '222222',
            'cp_address' => 'New Addr',
        ];

        $res = $this->putJson(action([CompanyController::class, 'update']), $payload);

        $res->assertOk()
            ->assertJsonFragment(['cp_name' => 'New Name'])
        ;

        // 仍应只有 1 行数据（单例语义）
        $this->assertSame(1, Company::query()->count());

        $this->assertDatabaseHas((new Company())->getTable(), [
            'cp_name'  => 'New Name',
            'cp_phone' => '222222',
        ]);
    }
}
