COMPOSER_BIN := $(shell composer -q global config bin-dir --absolute 2>/dev/null)

.PHONY:

composer-dump:
	@composer dump-autoload

composer-global-update:
	@composer global update -vvv

composer-global-install:
	@composer global require brainmaestro/composer-git-hooks
	@composer global require friendsofphp/php-cs-fixer

composer-global-refresh-hooks:
	@$(COMPOSER_BIN)/cghooks update

composer-update:
	#composer clear-cache
	# 或指定 Composer 偏好协议为 ssh
	#composer config github-protocols ssh
	composer update -vvv

composer-version:
	php -v
	composer --version
	php artisan --version

composer-run:
	php artisan serve

npm-update:
	pnpm update

npm-dev:
	rm -f public/hot
	rm -rf public/build
	echo "Non-production: running dev."
	pnpm ls
	pnpm run dev

pnpm-config:
	npm config set registry https://registry.npmmirror.com
	pnpm config set registry https://registry.npmmirror.com
	npm config get registry
	pnpm config get registry
	cat ~/.npmrc
	pnpm store path
