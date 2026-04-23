COMPOSER_BIN := $(shell composer -q global config bin-dir --absolute 2>/dev/null)
REPO_ROOT := $(shell git -C $(CURDIR) rev-parse --show-toplevel 2>/dev/null)

.PHONY:

composer-dump:
	@composer dump-autoload

composer-global-update:
	@composer global update -vvv

composer-global-install:
	@composer global require brainmaestro/composer-git-hooks
	@composer global require friendsofphp/php-cs-fixer

composer-global-refresh-hooks:
	cd $(REPO_ROOT) && $(COMPOSER_BIN)/cghooks update
