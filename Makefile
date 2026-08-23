.PHONY: up share stop-share logs-share up-stripe check-stripe-env logs-stripe stop-stripe runtime-build runtime-smoke check-runtime-key up-runtime stop-runtime logs-runtime up-observability down down-clean shell bootstrap test test-backend e2e check-docs logs serve backup restore verify-restore retention-purge web-install web-dev web-check demo-seed demo-seed-empty

# Sur certaines installations, Docker nécessite sudo (socket root-only).
# Override : DOCKER=docker make test
DOCKER ?= docker
COMPOSE = $(DOCKER) compose -f implementation/docker-compose.yml
COMPOSE_STRIPE = $(DOCKER) compose --env-file implementation/app/.env -f implementation/docker-compose.yml

up:
	$(COMPOSE) up -d --build

share:
	bash ./implementation/scripts/share-quick-tunnel.sh

stop-share:
	$(COMPOSE) --profile share stop quick-tunnel

logs-share:
	$(COMPOSE) --profile share logs -f quick-tunnel

check-stripe-env:
	@grep -Eq '^SUBSCRIPTIONS_STRIPE_SECRET_KEY=sk_test_.+' implementation/app/.env || { echo "SUBSCRIPTIONS_STRIPE_SECRET_KEY must contain a Stripe test key in implementation/app/.env."; exit 78; }
	@grep -Eq '^SUBSCRIPTIONS_STRIPE_WEBHOOK_SECRET=whsec_.+' implementation/app/.env || { echo "SUBSCRIPTIONS_STRIPE_WEBHOOK_SECRET must contain the Stripe CLI signing secret in implementation/app/.env."; exit 78; }

up-stripe: check-stripe-env
	$(COMPOSE_STRIPE) --profile stripe up -d stripe-listener

logs-stripe:
	$(COMPOSE_STRIPE) --profile stripe logs -f stripe-listener

stop-stripe:
	$(COMPOSE_STRIPE) --profile stripe stop stripe-listener

runtime-build:
	$(COMPOSE) --profile runtime build api

check-runtime-key:
	@if [ -z "$(RUNTIME_APP_KEY)" ]; then echo "RUNTIME_APP_KEY is required."; exit 78; fi

runtime-smoke: check-runtime-key
	bash ./implementation/scripts/smoke-runtime.sh

up-runtime: check-runtime-key
	$(COMPOSE) --profile runtime up -d --build api worker scheduler

stop-runtime:
	$(COMPOSE) --profile runtime stop api worker scheduler

logs-runtime:
	$(COMPOSE) --profile runtime logs -f api worker scheduler

up-observability:
	OTEL_TRACES_EXPORTER=otlp $(COMPOSE) --profile observability up -d --build --force-recreate app jaeger otel-collector

down:
	$(COMPOSE) down

down-clean:
	$(COMPOSE) down -v

shell:
	$(COMPOSE) exec app bash

bootstrap:
	./implementation/scripts/bootstrap.sh

fix-permissions:
	./implementation/scripts/fix-permissions.sh

test: test-backend e2e

test-backend:
	./implementation/scripts/run-tests.sh

e2e:
	./implementation/scripts/run-e2e.sh

migrate-test:
	./implementation/scripts/migrate-and-test.sh

migrate-fresh:
	./implementation/scripts/migrate-fresh.sh

check-docs:
	./scripts/check-all.sh

logs:
	$(COMPOSE) logs -f

serve:
	$(COMPOSE) exec app php artisan serve --host=0.0.0.0 --port=8000

backup:
	./implementation/scripts/backup-postgres.sh

restore:
	@if [ -z "$(BACKUP)" ]; then echo "Usage: make restore BACKUP=implementation/backups/atlas-....dump"; exit 1; fi
	./implementation/scripts/restore-postgres.sh "$(BACKUP)"

verify-restore:
	./implementation/scripts/verify-restore-canary.sh $(if $(BACKUP),"$(BACKUP)",)

retention-purge:
	$(COMPOSE) exec app php artisan atlas:retention:purge $(if $(DRY_RUN),--dry-run,)

web-install:
	$(COMPOSE) exec app bash -c 'cd /workspace/implementation/app && npm install'

web-dev:
	$(COMPOSE) exec app bash -c 'cd /workspace/implementation/app && npm run dev -- --host 0.0.0.0'

web-check:
	$(COMPOSE) exec -T app bash -c 'cd /workspace/implementation/app && npm run typecheck && npm run build'

demo-seed:
	$(COMPOSE) exec app php artisan atlas:demo:seed

demo-seed-empty:
	$(COMPOSE) exec app php artisan atlas:demo:seed --profile=empty
