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
	#composer --version
	composer diagnose
	php artisan --version

composer-run:
	php artisan serve

