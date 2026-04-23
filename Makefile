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

# --- Deployment: dothome free hosting ---
BUNDLE_DIR := deploy/build
BUNDLE_ROOT := $(BUNDLE_DIR)/public_html
BUNDLE_ZIP  := deploy/samdogs-bundle.zip

.PHONY: dothome-bundle
dothome-bundle: ## build FTP-ready bundle for samdogs.dothome.co.kr (deploy/samdogs-bundle.zip)
	@echo '==> Cleaning $(BUNDLE_DIR)'
	rm -rf $(BUNDLE_DIR) $(BUNDLE_ZIP)
	mkdir -p $(BUNDLE_ROOT)/api $(BUNDLE_ROOT)/app

	@echo '==> Building frontend (static export)'
	cd $(FE_DIR) && BUILD_TARGET=dothome NEXT_PUBLIC_API_BASE=/api npm run build
	cp -r $(FE_DIR)/out/. $(BUNDLE_ROOT)/

	@echo '==> Copying backend entry scripts'
	cp backend/deploy/entries/index.php    $(BUNDLE_ROOT)/api/index.php
	cp backend/deploy/entries/precheck.php $(BUNDLE_ROOT)/api/precheck.php
	cp backend/deploy/entries/install.php  $(BUNDLE_ROOT)/api/install.php

	@echo '==> Copying backend code (src, config, database)'
	cp -r backend/src      $(BUNDLE_ROOT)/app/src
	cp -r backend/config   $(BUNDLE_ROOT)/app/config
	cp -r backend/database $(BUNDLE_ROOT)/app/database
	cp backend/composer.json backend/composer.lock $(BUNDLE_ROOT)/app/

	@echo '==> Installing production composer deps into app/vendor'
	docker compose run --rm --no-deps \
	  -u $(shell id -u):$(shell id -g) \
	  -e COMPOSER_HOME=/tmp/composer-host \
	  -v $(PWD)/$(BUNDLE_ROOT)/app:/bundle \
	  -w /bundle \
	  php composer install --no-dev --optimize-autoloader --no-interaction --no-progress

	@echo '==> Writing .htaccess + .env.example templates'
	cp deploy/templates/htaccess-root $(BUNDLE_ROOT)/.htaccess
	cp deploy/templates/htaccess-app  $(BUNDLE_ROOT)/app/.htaccess
	cp deploy/templates/htaccess-api  $(BUNDLE_ROOT)/api/.htaccess
	cp deploy/templates/env.production.example $(BUNDLE_ROOT)/.env.example

	@echo '==> Zipping bundle to $(BUNDLE_ZIP)'
	cd $(BUNDLE_DIR) && zip -qr ../$(notdir $(BUNDLE_ZIP)) public_html

	@echo ''
	@echo '==> Bundle ready:'
	@echo '    $(BUNDLE_ROOT)/  (raw tree)'
	@echo '    $(BUNDLE_ZIP)    (zipped — upload via FTP)'
	@echo ''
	@echo '다음 단계:'
	@echo '  1) 닷홈 관리패널에서 PHP 8.4 + MySQL DB 생성'
	@echo '  2) FTP 로 public_html/ 내용 전체를 도큐먼트 루트에 업로드'
	@echo '  3) public_html/.env.example 을 참고해 .env 를 작성/업로드'
	@echo '  4) 브라우저로 https://samdogs.dothome.co.kr/api/precheck.php 열어 점검'
	@echo '  5) 모두 통과 시 /api/install.php 로 관리자 계정 생성'
	@echo '  6) FTP 에서 api/precheck.php, api/install.php 삭제'

.PHONY: dothome-clean
dothome-clean: ## remove deploy bundle artifacts
	rm -rf $(BUNDLE_DIR) $(BUNDLE_ZIP)
