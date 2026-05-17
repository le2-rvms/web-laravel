.PHONY: dev print-env serve horizon logs vite bash

ENV_CONTEXT_FILES = \
	../docker-compose/env/L_/0loc.env \
	../docker-compose/env/L2/0loc.env \
	../docker-compose/env/L3/rc-local.env \
	../docker-compose/env/.pass.env

SERVICE_ENV_FILE = ../docker-compose/compose-L3/_/web/.env.php

LOAD_ENV = $(foreach file,$(ENV_CONTEXT_FILES),. $(file);) set -a; . $(SERVICE_ENV_FILE); set +a;
APP_ENV_OVERRIDES = DB_HOST=localhost DB_PORT=$${POSTGRES_PORT} 

dev: print-env
	$(MAKE) --no-print-directory -j2 serve

print-env:
	@env -i sh -c '$(LOAD_ENV) $(APP_ENV_OVERRIDES) env'

serve:
	@$(LOAD_ENV) $(APP_ENV_OVERRIDES) php artisan serve

horizon:
	@$(LOAD_ENV) $(APP_ENV_OVERRIDES) php artisan horizon

logs:
	@$(LOAD_ENV) $(APP_ENV_OVERRIDES) php artisan pail --timeout=0

vite:
	@$(LOAD_ENV) $(APP_ENV_OVERRIDES) pnpm run dev

bash:
	@$(LOAD_ENV) $(APP_ENV_OVERRIDES) exec $${SHELL} -l
