# 外部依赖清单

当前仓库已完成 Laravel 13 代码迁移，但以下资源仍需在部署或联调环境中提供，业务功能才能完整运行。

## 必需资源

- 业务数据库
  - 旧系统依赖既有业务表，仓库内不包含完整 schema。
  - 默认主库配置走 `pgsql`，关键环境变量为 `DB_*`。
- Redis
  - 用于队列、缓存、Reverb 扩展场景。
  - 关键环境变量为 `REDIS_*`、`QUEUE_CONNECTION`、`CACHE_STORE`。
- 对象存储
  - 文件上传与分享下载依赖 `local` / `s3` 磁盘配置。
  - 关键环境变量为 `FILESYSTEM_DISK`、`AWS_*`。
- Reverb
  - 实时广播依赖 Reverb 服务和应用密钥。
  - 关键环境变量为 `REVERB_*`、`BROADCAST_CONNECTION`。

## 按功能启用

- 阿里云短信
  - `App\Providers\DysmsapiProvider` 依赖阿里云凭证链。
- 阿里云 OCR
  - `App\Providers\OcrServiceProvider` 依赖阿里云凭证链。
- WeCom 投递
  - 关键环境变量为 `WECOM_*`。
- MQTT
  - 关键环境变量为 `MQTT_*`。
- TimescaleDB
  - IoT 相关场景可使用 `timescaledb` 连接。

## 初始化建议

- 复制 `.env.example` 为 `.env` 并填写真实连接信息。
- 先执行 `php artisan about`、`php artisan route:list`、`php artisan schedule:list` 验证应用启动。
- 接入真实业务库后，再执行超级管理员创建、权限导入及接口烟雾测试。
