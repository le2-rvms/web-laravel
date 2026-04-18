.PHONY: dev print-env serve horizon logs vite cli-bash

include ../docker-compose/.env

ENV_FILES = \
	../docker-compose/env/L_/0local.env \
	../docker-compose/env/L2/0local.env \
	../docker-compose/env/L3/rc-local.env \
	../docker-compose/env/.pass.env

LOAD_ENV = set -a; $(foreach file,$(ENV_FILES),. $(file);) set +a;
PRINT_ENV = echo '===== current env ====='; env | sort; echo '=======================';
APP_ENV_OVERRIDES = DB_HOST=localhost DB_PORT=$${POSTGRES_PORT}

dev: print-env
	@$(MAKE) --no-print-directory -j4 serve horizon logs vite

print-env:
	@$(LOAD_ENV) $(PRINT_ENV)

serve:
	@$(LOAD_ENV) $(APP_ENV_OVERRIDES) php artisan serve

horizon:
	@$(LOAD_ENV) $(APP_ENV_OVERRIDES) php artisan horizon

logs:
	@$(LOAD_ENV) $(APP_ENV_OVERRIDES) php artisan pail --timeout=0

vite:
	@$(LOAD_ENV) $(APP_ENV_OVERRIDES) npm run dev

cli-bash:
	@$(LOAD_ENV) $(APP_ENV_OVERRIDES) exec "$${SHELL:-/bin/bash}" -l
