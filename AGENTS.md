# AGENTS.md

RVMS Laravel API 服务的 Agent 工作约定。这里记录本仓库特有规则，优先级高于通用习惯；没有写明的事项，按 Laravel 项目常规和现有代码风格处理。

## 项目定位

- PHP `^8.4`，Laravel `^13.0`，纯 API 服务，不提供浏览器后台页面。
- 健康检查：`/status`。
- 路由入口：`/api-base/*` -> `routes/api_base.php`，`/api-admin/*` -> `routes/api_admin.php`，`/api-customer/*` -> `routes/api_customer.php`。
- 调度/命令：`routes/console.php`。
- Laravel 13 引导：`bootstrap/app.php`；Provider：`bootstrap/providers.php`。
- 不包含完整业务库 schema；大量功能依赖既有 PostgreSQL 业务库和外部服务。

## 本地环境与命令

项目本地运行依赖多层 env 文件，涉及 DB、Redis、S3、122 等服务时优先用 `Makefile` 注入环境。

```bash
composer install
make print-env
make bash
make serve
```

低依赖诊断命令可以直接运行：

```bash
php artisan about
php artisan route:list
php artisan list --raw
```

常用系统命令：

```bash
php artisan _sys:admin-super-user:upsert
php artisan _sys:admin-role:import
php artisan _sys:admin-permission:import
php artisan _sys:models-casts:check
```

危险命令必须先确认目标环境和数据库：

- 不要在未确认环境变量时运行 `composer setup`、`php artisan migrate --force`、`php artisan _sys:table-log-triggers:create`。
- 不要在测试中真实调用 122、企业微信、MQTT、OCR、对象存储或短信服务。
- 真实 `.env*`、cookie、S3/MinIO、阿里云、企业微信、MQTT、122 账号信息不读取、不展示、不提交。

## 修改边界

- 可改：`app/`、`routes/`、`config/`、`database/`、`tests/`、`lang/`、`helpers/`、`Docs/`。
- 常见业务入口：`app/Http/Controllers/**`、`app/Models/**`、`app/Services/**`、`app/Enum/**`、`app/Attributes/**`、`app/Console/Commands/**`。
- 不改：`vendor/`、`storage/`、`bootstrap/cache/`、IDE 文件、缓存、真实 `.env*` 密钥。
- 不新增前端构建链；本仓库当前只保留 API 服务。
- 不绕过权限、统一响应、分页、上传、导入导出协议。

## API 响应协议

控制器统一使用项目 `Controller::response()`：

```php
return $this->response()->withData($data)->withExtras($extra)->respond();
```

固定 payload：`data`、`message`、`messages`、`extra`、`lang`、`option`、`meta`。

- 不要随意返回裸数组或裸 `response()->json()`，除非已有局部约定明确需要。
- 业务错误优先使用项目已有异常类型，例如 `ClientException`。
- 参数校验使用 Laravel validator / `$request->validate()`，保持错误响应结构统一。
- 列表传 `PaginateService`；请求 `output=excel` 时由 `ResponseBuilder` 调 `PageExcel` 导出。

## 路由与权限

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

权限名：`ControllerShortName::read` / `ControllerShortName::write`。

新增或修改管理端接口时按以下顺序检查：

- 路由放入对应 `routes/api_*.php`。
- 控制器继承项目 `App\Http\Controllers\Controller`。
- 管理端接口加 `PermissionType` / `PermissionAction`。
- 响应走 `response()->withData(...)->respond()`。
- 列表接口使用 `PaginateService`。
- 新增控制器或动作后运行 `php artisan _sys:admin-permission:import`，涉及内置角色时同步检查 `php artisan _sys:admin-role:import`。

## 模型、枚举与列表

业务模型通常实现：

```php
public static function indexQuery(): Builder;
public static function optionsQuery(): Builder;
```

列表用 `PaginateService`，搜索字段和排序字段必须白名单化。支持后缀：`eq`、`in`、`nin`、`ilike`、`gt`、`lt`、`gte`、`lte`、`between_date`、`func`。`neq` 当前在实现中没有行为，不要依赖。

- 不拼接未白名单参数。
- PostgreSQL JSON、数组、`ilike`、TimescaleDB 等特性不能用 MySQL/SQLite 语义推断。
- 枚举是可 Cast 的类枚举，不是 PHP 原生 enum；定义常量和 `LABELS`，选项用 `labelOptions()` / `options()`。
- 修改模型 `$casts` 或枚举映射后运行 `php artisan _sys:models-casts:check`。
- 不要在模型访问器、cast、query scope 中隐藏不可控外部网络调用。

## 文件、导入与导出

- 上传走 `App\Services\Uploader`；业务文件写 `s3`，临时文件写 `local`。
- 上传校验用 `Uploader::validator_rule_upload_object()` / `Uploader::validator_rule_upload_array()`。
- Excel 导出必须给 `PaginateService::paginator()` 传 `$columns`。
- 导入模型用 `ImportTrait`，清洗、校验、持久化不要堆在控制器。
- 多表写入用 `DB::transaction()`；不要在事务里调用不可控外部网络。
- 审计触发器命令：`php artisan _sys:table-log-triggers:create`，运行前必须确认目标数据库。

## 外部依赖与测试 Fake

外部依赖包括 PostgreSQL、Redis、S3/MinIO、Reverb、阿里云短信/OCR、企业微信、MQTT、TimescaleDB、122 平台。

测试中必须 fake 外部边界：

- 文件存储：`Storage::fake()`。
- HTTP、OCR、短信、122：使用 `Http::fake()` 或对应封装服务 fake。
- 队列、事件：`Queue::fake()`、`Event::fake()`。
- 企业微信、MQTT、Reverb：不得在测试中真实连接。

## IoT / GPS / 122

- GPS 通道：`routes/channels.php`。
- GPS 说明：`Docs/WebSocketGps.md`；外部依赖：`Docs/ExternalDependencies.md`。
- 最新定位：`GET /api-admin/gps-data/latest`。
- BLE 密钥：`php artisan app:iot:ble:k-dev-enc`。
- 122 同步：`app/Console/Commands/App/One/**`，调度在 `routes/console.php`。
- 涉及 122 cookie、账号、抓取结果和同步命令时，默认视为敏感操作，除非用户明确要求并确认环境。

## 验证矩阵

```bash
composer test
php artisan test --testsuite=Unit
php artisan test --filter=具体测试类名
```

- 改 helper、枚举、纯函数、小服务：跑相关 Unit。
- 改 route、controller、middleware、permission、response 结构：跑相关 Feature。
- 改 `ResponseBuilder`、`PaginateService`、权限中间件、上传/导入导出协议：优先跑相关测试，风险高时跑 `composer test`。
- Feature/API 多依赖真实 PostgreSQL；SQLite 只适合纯单元测试，不能作为 PostgreSQL 特性正确性的最终证明。
- 没有运行测试时，完成说明里必须写明原因和剩余风险。

## 代码风格与收尾

- 遵循现有代码风格，不做无关重构。
- 优先提高阅读性，不要新增薄封装。只有在逻辑被复用、能隔离复杂业务规则、能降低认知负担或符合现有明确模式时才抽方法；仅被调用一次、只是转调、只是包一层简单 `if`、只是返回字面量数组的小方法应直接内联到调用处。
- 抽出的私有方法名称必须比方法体更能表达业务含义；否则保留顺序代码和局部变量通常更清楚。
- 文件过长时按稳定业务边界拆到有明确职责的类，例如“场景属性生成”“关联数据图生成”；不要为了降低行数把连续流程切成一组无语义的小方法。
- Factory 场景数据不要在命令、测试或 Seeder 中用 `Model::factory()->create([...])` 二次覆盖字段；有业务含义的生成规则应放进对应 Factory 的具名 state，调用侧只表达场景和关系，避免规则散落形成隐性技术债。
- 修改 PHP 文件后必须用 php-cs-fixer 按仓库配置 `.php-cs-fixer.php` 格式化，例如 `$(composer -q global config bin-dir --absolute)/php-cs-fixer fix --config=./.php-cs-fixer.php <file...>`；提交前 hook 也会按该配置处理暂存 PHP 文件。不要用其他格式化工具替代这条规则。
- 完成时说明：修改文件、改动意图、验证命令、未验证风险。
- 如果工作区已有用户改动，不要回滚；只处理本任务相关文件。
