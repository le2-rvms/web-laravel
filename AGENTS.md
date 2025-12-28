# AGENTS.md

本文件面向智能代理（AI 助手）与新加入的开发者，帮助你在本仓库中高效、安全地工作。请在修改代码前通读要点与约定。

## 总览

-   框架与运行时
    -   Laravel 12（无 `Kernel`，在 `bootstrap/app.php` 配置路由/中间件）。
    -   PHP >= 8.4，Composer 2，Node + Vite 前端构建。
    -   主要依赖：Sanctum 鉴权、AdminLTE 后台 UI、Spatie Permission、PhpSpreadsheet/PhpWord、ClickHouse 客户端、阿里云 OCR SDK。
-   主要模块与入口
    -   Web 管理后台：`routes/web_admin.php`（前缀 `web-admin`）。
    -   平台公共接口：`routes/api_base.php`（前缀 `api-base`）。
    -   管理端接口：`routes/api_admin.php`（前缀 `api-admin`）。
    -   客户端接口：`routes/api_customer.php`（前缀 `api-customer`）。
    -   IoT/EMQX：`routes/api_iot.php`（前缀 `api-iot`）。
    -   计划任务与控制台：`routes/console.php`。
    -   应用引导：`bootstrap/app.php:1`。

## 开发环境与运行

-   前置条件
    -   PHP 8.4、Composer 2、Node 18+、npm 9+。
    -   数据库建议使用 PostgreSQL（大量 SQL 使用 JSON/CASE 语法，SQLite 仅用于最小化启动）。
    -   可选：Redis、MinIO/S3（文件上传）、ClickHouse（如需）。
-   初始化
    -   安装依赖：`composer install`，`npm ci`（或 `npm i`）。
    -   复制环境：`cp .env.example .env` 并填写 DB、S3、阿里云 OCR 等配置。
    -   生成应用密钥：`php artisan key:generate`。
    -   迁移最小表（用户/会话/队列/缓存）：`php artisan migrate`。
    -   创建超级管理员：`php artisan _sys:super-user:create`（依赖 `.env` 中 `SUPER_*` 与 `SUPER_ROLE_NAME`，参见 `config/setting.php:1`）。
    -   同步权限：`php artisan _sys:permission:import`（从控制器属性读取并入库）。
-   启动开发
    -   后端：`php artisan serve`、队列：`php artisan queue:listen --tries=1`。
    -   前端：`npm run dev`（或 `composer dev` 一键并行，见 `composer.json: scripts.dev`）。
    -   Vite 开发配置：`vite.config.js:1`（读取 `PORT`）。

## 目录结构要点

-   控制器：`app/Http/Controllers/**`
    -   统一继承 `app/Http/Controllers/Controller.php:1`，通过 `response()` 返回统一响应（见 `app/Http/Responses/ResponseBuilder.php:1`）。
    -   管理端控制器使用权限属性标注：
        -   类级：`#[PermissionType('中文模块名')]`（`app/Attributes/PermissionType.php:1`）。
        -   方法级：`#[PermissionAction(PermissionAction::INDEX|SHOW|ADD|EDIT|DELETE|…)]`（`app/Attributes/PermissionAction.php:1`）。
-   中间件：`app/Http/Middleware/**`
    -   权限校验：`CheckPermission` 基于控制器属性 + Spatie 权限（`app/Http/Middleware/CheckPermission.php:1`）。
    -   临时免登：`TemporaryAdmin` / `TemporaryCustomer` 支持 `MOCK_ENABLE=true`（`config/setting.php:1`）。
    -   请求/响应日志：`LogRequests`（输出到 `reqres` 渠道，`config/logging.php:1`）。
-   模型与枚举
    -   模型统一 `use ModelTrait`（`app/Models/ModelTrait.php:1`）并实现静态 `indexQuery(array $search): Builder` 用于列表查询。
    -   业务枚举为“类枚举”并可直接 Cast，提供 `LABELS`、`toCaseSQL()` 等（`app/Enum/EnumLikeBase.php:1`）。
-   服务
    -   分页/导出：`PaginateService` + `PageExcel`（请求带 `output=excel` 触发导出，`app/Services/PageExcel.php:260`）。
    -   上传与 OCR：`app/Services/Uploader.php:1`，S3 本地/远程磁盘，支持临时签名下载（`helpers/functions.php:1`）。
-   助手函数
    -   在 `AppServiceProvider` 中统一自动加载 `helpers/*.php`（`app/Providers/AppServiceProvider.php:1`）。

## 路由与权限

-   路由聚合位于 `bootstrap/app.php:1`（非传统 `RouteServiceProvider`/`Kernel`）。
-   路由分组
    -   Web 管理后台：`web` 中间件组 + `CheckPermission`（`routes/web_admin.php`）。
    -   API 组：`api` 中间件组默认附加 `LogRequests`。
    -   管理/客户 API 支持 `Sanctum` 或 `Temporary*`（`config('setting.mock.enable')`）。
-   权限模型
    -   `AuthServiceProvider` 中 `Gate::before` 赋予超级角色全权限（`app/Providers/AuthServiceProvider.php:1`）。
    -   `CheckPermission` 读取控制器类的 `PermissionType`，校验当前用户是否 `can(控制器短名)`。
    -   添加/修改控制器后运行 `php artisan _sys:permission:import` 同步权限到数据库。

## 数据层与外部服务

-   数据库
    -   默认连接见 `config/database.php:1`，开发推荐 `pgsql`；大量查询使用 `DB::query()` + 多表联查（例如 `app/Models/Rental/Sale/RentalSaleOrder.php`）。
    -   业务表（以 `rental_*` 命名）通常由外部/现有数据库提供，仓库内不包含全部迁移；本地启动需指向现成库。
-   对象存储
    -   `FILESYSTEM_DISK=s3` 时走 MinIO/S3；`Uploader` 与 `DocTplService` 会读写 `s3` 与 `local` 磁盘，并生成临时签名链接。
-   第三方
    -   阿里云 OCR 通过 `OcrServiceProvider` 注册（`app/Providers/OcrServiceProvider.php:1`），读取 `config/setting.php:1` 中的密钥。
    -   IoT/EMQX 认证：`app/Http/Controllers/Iot/Mqtt/EmqxAuthController.php` + `app/Http/Requests/Mqtt/EmqxAuthRequest.php:1`。
    -   ClickHouse 连接别名：`helpers/app.php:1` 的 `app_clickhouse()`。

## 响应规范

-   控制器通过 `response()` 取得 `ResponseBuilder` 并链式构造：
    -   `withData($data)` 支持模型/数组/分页服务。
    -   `withExtras([...])`、`appendExtras([...])` 填充额外字段（如下拉选项）。
    -   `withLang([...])`、`withOption($mixed)`、`withMessages($msg)`。
    -   `respond($status=200)` 自动根据请求 `Accept` 返回 JSON 或 Blade 视图（视图名由 `helpers/functions.php:get_view_file()` 推导）。

## 日志与调试

-   请求/响应日志：`LogRequests` 按采样/脱敏/限长策略记录到 `storage/logs/reqres-*.log`（`config/logging.php:1`）。
-   控制台日志：`Log::channel('console')`。
-   计划任务：`routes/console.php` 有若干 122.gov 接口轮询/Cookie 刷新任务，注意网络与速率限制；每日 `SmtpSelfTest` 任务会调用 `_sys:smtp:self-test` 向 `SMTP_SELF_TEST_TO`（未设置时退化为 `MAIL_FROM_ADDRESS`）发送自检邮件。

## 测试

-   运行：`composer test`（先清理配置缓存再执行 `php artisan test`）。
-   `tests/TestCase.php:1` 默认以超级管理员身份登录；需先执行 `_sys:super-user:create` 并确保数据库具备必要业务表与数据。
-   单元测试示例：`tests/Unit/MoneyHelperTest.php:1`（中文金额格式化，见 `helpers/class.php`/`helpers/money_format.php`）。
-   许多功能测试依赖 `rental_*` 业务表与工厂，请使用现成数据库或导入快照。

## 常见任务指引

-   新增管理端资源型接口
    1. 在对应路由文件增加 `Route::resource()`（`routes/api_admin.php`）。
    2. 新建控制器并继承基类，添加 `#[PermissionType]`，在动作方法上标注 `#[PermissionAction(...)]`。
    3. 在控制器中使用 `response()` 返回统一结构；列表建议经 `indexQuery()` + `PaginateService`。
    4. 运行 `php artisan _sys:permission:import` 同步权限。
-   新增枚举
    -   按 `app/Enum/Rental/VrReplacementStatus.php:1` 样式创建，定义常量与 `LABELS`；
    -   模型中 `casts` 指定该枚举；SQL 场景可用 `EnumLikeBase::toCaseSQL()` 生成标签列。
-   导出 Excel
    -   在列表接口请求参数加入 `output=excel`，由 `PageExcel` 按控制器专用列配置生成并返回 `share` 临时下载链接。

## 样式与提交

-   统一风格：`.php-cs-fixer.php` 已配置；`composer global` 安装 `friendsofphp/php-cs-fixer` 后可用。
-   提交钩子：`composer.json` 中包含 `pre-commit` 钩子（需 `cghooks` 安装并 `cghooks update`）。
-   命名与结构：保持现有命名（驼峰/下划线混合按领域约定）；控制器/模型/枚举请遵循现有目录与前缀。

## 安全与注意事项

-   切勿提交任何真实密钥/访问令牌（阿里云/MinIO/数据库）。
-   大量查询依赖 PostgreSQL 语法（如 `jsonb_build_object` 与 `->>`），本地若强制 SQLite 运行，相关功能将不可用。
-   `MOCK_ENABLE=true` 仅用于开发演示，生产务必关闭。

## 注释约定

### 总原则

-   输出、沟通与新增文字说明使用中文。
-   优先保证：正确性 > 可维护性 > 性能；任何改动必须可验证（测试/静态检查）。
-   默认不做无关重构；仅在不改变行为且能显著降低复杂度时，允许非常小的结构整理（例如提取私有方法、改变量名）。

### 代码边界

-   默认只改自研代码：app/ routes/ database/ tests/ config/
-   禁止触碰：vendor/ storage/ bootstrap/cache/ public/build/ dist/ 以及任何自动生成文件（除非明确要求）。

### 变更输出要求

-   每次只做小批次改动（一个目录或一个子模块）。
-   输出：变更文件清单 + 每个文件的改动意图摘要 。

### 注释策略（只注释关键逻辑：硬约束）

-   目标：让“非直观关键逻辑”可在 30 秒内读懂；除此之外不写注释。
-   只允许三类注释：
    1) 业务不变量/边界条件（为什么必须这样）
    2) 事务/并发/一致性（幂等、锁、重试、补偿）
    3) 复杂查询/算法的意图与约束（不是逐行解释）
-   禁止：逐行注释、复述代码字面含义、为简单 getter/setter 写注释。
-   每个函数原则上最多 0~1 段注释；只有当逻辑确实“不可自解释”时才添加。
-   PHPDoc 标签（@param/@return/@throws）保持标准写法；说明文字用中文；仅对 public API 或复杂方法补充。
