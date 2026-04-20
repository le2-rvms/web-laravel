# RVMS API

该项目当前只保留 Laravel API 服务，不再提供 `web-admin` 浏览器后台页面。

## 保留入口

- `/status`
- `/api-base/*`
- `/api-admin/*`
- `/api-customer/*`
- `routes/console.php`

## 安装与运行

```bash
composer install
php artisan about
php artisan route:list
```

## 测试

```bash
php artisan test --testsuite=Unit
```
