.DEFAULT_GOAL := help
SHELL := /bin/bash

# Docker helpers
DC       := docker compose
PHP_RUN  := $(DC) run --rm php
PHP_EXEC := $(DC) exec -T php

# Frontend runs on host (node 20+)
FE_DIR   := frontend
NPM      := npm --prefix $(FE_DIR)

.PHONY: help
help:
	@awk 'BEGIN {FS = ":.*##"; printf "\nTargets:\n"} /^[a-zA-Z_-]+:.*?##/ { printf "  \033[36m%-18s\033[0m %s\n", $$1, $$2 }' $(MAKEFILE_LIST)

# --- Environment ---
.PHONY: env
env: ## copy .env.example -> .env (if missing)
	@[ -f .env ] || cp .env.example .env && echo ".env ready"

.PHONY: up
up: env ## start stack (mysql, php, nginx)
	$(DC) up -d

.PHONY: down
down: ## stop stack
	$(DC) down

.PHONY: build
build: ## rebuild images
	$(DC) build

# --- Install ---
.PHONY: install
install: install-be install-fe ## install BE + FE deps

.PHONY: install-be
install-be: env ## composer install inside php container
	$(PHP_RUN) composer install --no-interaction --prefer-dist

.PHONY: install-fe
install-fe: ## npm ci for frontend
	@if [ -f $(FE_DIR)/package-lock.json ]; then \
	  $(NPM) ci ; \
	else \
	  $(NPM) install ; \
	fi

# --- DB ---
.PHONY: migrate
migrate: ## run Phinx migrations (dev DB)
	$(PHP_RUN) vendor/bin/phinx migrate -c config/phinx.php -e development

.PHONY: migrate-test
migrate-test: ## run Phinx migrations on test DB
	$(PHP_RUN) vendor/bin/phinx migrate -c config/phinx.php -e testing

.PHONY: seed
seed: ## seed default data
	$(PHP_RUN) vendor/bin/phinx seed:run -c config/phinx.php -e development

.PHONY: admin-seed
admin-seed: ## seed initial admin (requires INITIAL_ADMIN_EMAIL + INITIAL_ADMIN_PASSWORD in .env)
	$(PHP_RUN) vendor/bin/phinx seed:run -c config/phinx.php -e development -s InitialAdminSeeder

# --- Test ---
.PHONY: test
test: test-be test-fe ## run all tests

.PHONY: test-be
test-be: ## PHPUnit with coverage
	$(PHP_RUN) vendor/bin/phpunit --colors=never

.PHONY: test-fe
test-fe: ## Vitest with coverage
	$(NPM) run test -- --run --coverage

.PHONY: test-unit
test-unit: ## BE Unit + FE unit only (quick)
	$(PHP_RUN) vendor/bin/phpunit --testsuite=unit --colors=never
	$(NPM) run test -- --run

.PHONY: e2e
e2e: ## Playwright E2E (requires stack up)
	$(NPM) run e2e

# --- Quality ---
.PHONY: lint
lint: lint-be lint-fe ## lint BE + FE

.PHONY: lint-be
lint-be:
	$(PHP_RUN) vendor/bin/php-cs-fixer fix --dry-run --diff

.PHONY: lint-fe
lint-fe:
	$(NPM) run lint

.PHONY: fmt
fmt: ## auto-format BE + FE
	$(PHP_RUN) vendor/bin/php-cs-fixer fix
	$(NPM) run format

.PHONY: typecheck
typecheck: typecheck-be typecheck-fe ## PHPStan + tsc

.PHONY: typecheck-be
typecheck-be:
	$(PHP_RUN) vendor/bin/phpstan analyse --no-progress --memory-limit=1G

.PHONY: typecheck-fe
typecheck-fe:
	$(NPM) run typecheck

.PHONY: ci
ci: lint typecheck test ## must be green before commit
