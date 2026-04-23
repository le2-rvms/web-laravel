COMPOSER_BIN := $(shell composer -q global config bin-dir --absolute 2>/dev/null)

.PHONY:

composer-global-update:
	@composer global update -vvv

composer-global-install:
	@composer global require brainmaestro/composer-git-hooks
	@composer global require friendsofphp/php-cs-fixer

composer-global-refresh-hooks:
	@$(COMPOSER_BIN)/cghooks update
