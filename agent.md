# agent.md

RVMS Laravel API 服务的 Agent 工作约定。只写本仓库特有规则。

## 项目事实

- PHP `^8.4`，Laravel `^13.0`，纯 API，无浏览器后台。
- 路由：`/api-base/*` -> `routes/api_base.php`，`/api-admin/*` -> `routes/api_admin.php`，`/api-customer/*` -> `routes/api_customer.php`。
- 调度/命令：`routes/console.php`。
- Laravel 13 引导：`bootstrap/app.php`；Provider：`bootstrap/providers.php`。
- 不包含完整业务库 schema；大量功能依赖既有 PostgreSQL 业务库和外部服务。

## 修改边界

- 可改：`app/`、`routes/`、`config/`、`database/`、`tests/`、`lang/`、`Docs/`。
- 不改：`vendor/`、`storage/`、`bootstrap/cache/`、IDE 文件、缓存、真实 `.env*` 密钥。
- 不新增前端构建链。
- 不绕过权限、统一响应、分页、上传、导入导出协议。
- 不提交真实凭证、Cookie、S3/MinIO、阿里云、企业微信、MQTT、122 账号信息。

## 常用命令

```bash
composer install
php artisan about
php artisan route:list
php artisan serve
```

```bash
php artisan _sys:admin-super-user:upsert
php artisan _sys:admin-role:import
php artisan _sys:admin-permission:import
php artisan _sys:models-casts:check
```

```bash
composer test
php artisan test --testsuite=Unit
php artisan test --filter=具体测试类名
```

Feature/API 多依赖真实 PostgreSQL；SQLite 只适合纯单元测试。

## 核心目录

- 管理端：`app/Http/Controllers/Admin/**`
- 客户端：`app/Http/Controllers/Customer/**`
- 统一响应：`app/Http/Responses/ResponseBuilder.php`
- 权限：`app/Http/Middleware/CheckPermission.php`、`app/Attributes/**`
- 模型公共能力：`app/Models/_/ModelTrait.php`、`ImportTrait.php`
- 枚举：`app/Enum/**`，参考 `EnumLikeBase`
- 服务：`app/Services/**`
- 翻译：`lang/zh_CN/**`

外部依赖：PostgreSQL、Redis、S3/MinIO、Reverb、阿里云短信/OCR、企业微信、MQTT、TimescaleDB、122 平台。

## 响应协议

控制器统一：

```php
return $this->response()->withData($data)->withExtras($extra)->respond();
```

固定 payload：`data`、`message`、`messages`、`extra`、`lang`、`option`、`meta`。

列表传 `PaginateService`；请求 `output=excel` 时由 `ResponseBuilder` 调 `PageExcel` 导出。

## 权限协议

管理端控制器必须声明：

```php
#[PermissionType('中文模块名')]
class XxxController extends Controller
{
    #[PermissionAction(PermissionAction::READ)]
    public function index(Request $request): Response {}

    #[PermissionAction(PermissionAction::WRITE)]
    public function store(Request $request): Response {}
}
```

权限名：`ControllerShortName::read` / `ControllerShortName::write`。新增控制器或动作后运行：

```bash
php artisan _sys:admin-permission:import
```

## 模型与列表

业务模型通常实现：

```php
public static function indexQuery(): Builder;
public static function optionsQuery(): Builder;
```

列表用 `PaginateService`，搜索字段必须白名单化。支持后缀：`eq`、`in`、`nin`、`ilike`、`gt`、`lt`、`gte`、`lte`、`between_date`、`func`。不要拼接未白名单参数。

枚举是可 Cast 的类枚举，不是 PHP 原生 enum；定义常量和 `LABELS`，选项用 `labelOptions()` / `options()`。

## 文件与数据

- 上传走 `App\Services\Uploader`；业务文件写 `s3`，临时文件写 `local`。
- 上传校验用 `validator_rule_upload_object()` / `validator_rule_upload_array()`。
- Excel 导出必须给 `PaginateService::paginator()` 传 `$columns`。
- 导入模型用 `ImportTrait`，清洗/校验/持久化不要堆在控制器。
- 多表写入用 `DB::transaction()`；不要在事务里调用不可控外部网络。
- 审计触发器：`php artisan _sys:table-log-triggers:create`。

## IoT / GPS / 122

- GPS 通道：`routes/channels.php`。
- GPS 说明：`Docs/WebSocketGps.md`；外部依赖：`Docs/ExternalDependencies.md`。
- 最新定位：`GET /api-admin/gps-data/latest`。
- BLE 密钥：`php artisan app:iot:ble:k-dev-enc`。
- 122 同步：`app/Console/Commands/App/One/**`，调度在 `routes/console.php`。
- 测试 122、企业微信、MQTT、OCR、对象存储时必须 fake。

## 测试与收尾

- Unit：helper、枚举、纯函数、小服务。
- Feature：路由、控制器、权限、响应结构。
- 外部依赖用 `Storage::fake()`、`Http::fake()`、`Queue::fake()`、`Event::fake()`。
- PostgreSQL 特性不能用 SQLite 结果证明正确。
- 完成时说明：修改文件、改动意图、验证命令、未验证风险。
