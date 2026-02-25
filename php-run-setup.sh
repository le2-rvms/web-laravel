#!/bin/sh
set -eux

env
# composer clear
composer clearcache
pwd
# chown -R www-data:www-data ./
# find . -path ./.git -prune -o -exec chown -h www-data:www-data {} +
composer config -g repo.packagist composer https://mirrors.tencent.com/composer/
composer config -g --list | grep repositories

php -v
composer --version

APP_ENV="${APP_ENV:-dev}"

if [ "$APP_ENV" = "production" ] || [ "$APP_ENV" = "staging" ]; then
    echo "执行生产环境部署..."
    composer install --no-scripts --prefer-dist --no-interaction --no-progress --optimize-autoloader --classmap-authoritative
    php artisan about
    php artisan --version
#    php artisan optimize:clear
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache
else
    echo "执行开发环境脚本..."
    # composer config github-protocols https
     composer update -vvv
#    composer install
    php artisan --version
    php artisan about
#    php artisan optimize:clear  # 这个时候数据表还没有准备好
fi
