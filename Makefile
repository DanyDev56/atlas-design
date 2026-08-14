.PHONY: up up-observability down down-clean shell bootstrap test check-docs logs serve backup restore verify-restore retention-purge web-install web-dev web-check demo-seed

# Sur certaines installations, Docker nécessite sudo (socket root-only).
# Override : DOCKER=docker make test
DOCKER ?= docker
COMPOSE = $(DOCKER) compose -f implementation/docker-compose.yml

up:
	$(COMPOSE) up -d --build

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

test:
	./implementation/scripts/run-tests.sh

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
