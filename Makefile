.PHONY: dev print-env serve horizon logs vite cli-bash

include ../docker-compose/.env
include ../docker-compose/env/L_/0local.env
include ../docker-compose/env/L2/0local.env
include ../docker-compose/env/L3/rc-local.env
include ../docker-compose/env/.pass.env

ENV_FILES = \
	../docker-compose/env/L_/0local.env \
	../docker-compose/env/L2/0local.env \
	../docker-compose/env/L3/rc-local.env \
	../docker-compose/env/.pass.env

LOAD_ENV = set -a; $(foreach file,$(ENV_FILES),. $(file);) set +a;
PRINT_ENV = echo '===== current env ====='; env; echo '=======================';
APP_ENV_OVERRIDES = DB_HOST=localhost DB_PORT=$${POSTGRES_PORT} TZ=Asia/Shanghai COMPANY_ID=${COMPANY_ID} APP_ENV=${APP_ENV} S3_PASSWORD=${S3_PASSWORD} PG_PASSWORD=${PG_PASSWORD} TIMESCALEDB_HOST=localhost TIMESCALEDB_PASSWORD=${TIMESCALEDB_PASSWORD} MOCK_ENABLE=${MOCK_ENABLE} SUPER_USER_EMAIL=${SUPER_USER_EMAIL} ALIBABA_CLOUD_ACCESS_KEY_ID=${ALIYUN_ACCESS_KEY_ID} ALIBABA_CLOUD_ACCESS_KEY_SECRET=${ALIYUN_ACCESS_KEY_SECRET} MAIL_PASSWORD=${MAIL_PASSWORD} WECOM_CORP_ID=${WECOM_CORP_ID} WECOM_APP_DELIVERY_AGENT_ID=${WECOM_APP_DELIVERY_AGENT_ID} WECOM_APP_DELIVERY_SECRET=${WECOM_APP_DELIVERY_SECRET} MQTT_AUTH_USERNAME=${MOSQUITTO_USER_APP_WEB} MQTT_AUTH_PASSWORD=${MOSQUITTO_PASSWORD_APP_WEB}

dev: print-env
	$(MAKE) --no-print-directory -j4 serve logs vite

print-env:
	$(LOAD_ENV) $(APP_ENV_OVERRIDES) env

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

bash:
	@$(LOAD_ENV) $(APP_ENV_OVERRIDES) bash