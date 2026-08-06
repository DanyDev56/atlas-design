.PHONY: up down down-clean shell bootstrap test check-docs logs serve

# Sur certaines installations, Docker nécessite sudo (socket root-only).
# Override : DOCKER=docker make test
DOCKER ?= docker
COMPOSE = $(DOCKER) compose -f implementation/docker-compose.yml

up:
	$(COMPOSE) up -d --build

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
