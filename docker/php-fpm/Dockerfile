ARG IMAGE_PREFIX

FROM ${IMAGE_PREFIX}php-fpm-alpine

ARG APP_ENV

COPY config/ /tmp/

RUN if [ "$APP_ENV" = "production" ]; then \
        mv /tmp/php.ini-prod $PHP_INI_DIR/conf.d/php.ini; \
        rm -f /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini; \
    else \
        mv /tmp/php.ini-dev $PHP_INI_DIR/conf.d/php.ini; \
    fi

# USER
ARG USER_ID=1000
ARG GROUP_ID=1000

RUN deluser www-data 2>/dev/null || true; \
    delgroup www-data 2>/dev/null || true; \
    addgroup -g "${GROUP_ID}" -S www-data; \
    adduser  -u "${USER_ID}" -D -S -G www-data -h /home/www-data www-data; \
    \
    # 目录与权限 \
    mkdir -p /home/www-data /www /var/www /www/vendor /www/storage/app/private/share; \
    chown -R www-data:www-data /home/www-data /www /var/www /www/vendor /www/storage/app/private/share
