# PdfSignable Bundle Demo - Symfony 8 (Docker, FrankenPHP + Caddy, HTTPS)
# Prefer Compose V2 plugin (GitHub Actions / modern Docker Desktop); fall back to docker-compose V1 (REQ-MAKE-010).
COMPOSE := $(shell docker compose version >/dev/null 2>&1 && echo "docker compose" || echo "docker-compose")
SERVICE_PHP := php

.PHONY: help up down shell install setup run ensure-up update-bundle clean logs update-deps test-e2e demo-screenshots

help:
	@echo "PdfSignable Demo (Symfony 8) - Docker (FrankenPHP + Caddy)"
	@echo ""
	@echo "  up            - Start container (FrankenPHP)"
	@echo "  down          - Stop container"
	@echo "  restart       - Restart container (docker compose restart)"
	@echo "  build         - Rebuild image (no cache)"
	@echo "  shell         - Shell into PHP container"
	@echo "  install       - composer install in container"
	@echo "  setup         - install + cache:clear"
	@echo "  run           - up + setup (start and ready)"
	@echo "  ensure-up     - Ensure containers are up (start if needed)"
	@echo "  update-bundle - Rebuild bundle assets and refresh symlinks/cache (use after changing bundle code)"
	@echo "  test          - Run tests"
	@echo "  test-coverage - Run tests with coverage (console output)"
	@echo "  clean         - Stop and remove vendor/var"
	@echo "  logs          - View logs"
	@echo "  test-e2e      - Playwright e2e (Docker, on the php container network, https://localhost)"
	@echo "  demo-screenshots - Regenerate ../../docs/images/demo/*.webp (Playwright + sharp)"
	@echo ""
	@echo "After make run: http://localhost:8002 (HTTP on port 80)"

up:
	@if [ ! -f .env ]; then cp .env.example .env 2>/dev/null || true; fi
	$(COMPOSE) up -d
	@echo "Waiting for container to be ready..."
	@sleep 5
	@echo "Installing dependencies..."
	@$(COMPOSE) exec -T -e COMPOSER_MEMORY_LIMIT=-1 php composer install --no-interaction
	@$(MAKE) install
	@PORT=$$(grep "^PORT=" .env 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	[ -z "$$PORT" ] && PORT=$$(grep "^PORT=" .env.example 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	echo "Demo started at: http://localhost:$$PORT"
down:
	$(COMPOSE) down
	@echo "✅ Containers stopped"

restart: ensure-up
	$(COMPOSE) restart
	@echo "✅ Containers restarted"

build:
	$(COMPOSE) build --no-cache
	@echo "✅ Image rebuilt"

ensure-up:
	@if ! $(COMPOSE) exec -T php true 2>/dev/null; then \
		echo "Starting containers..."; \
		[ ! -f .env ] && cp .env.example .env || true; \
		$(COMPOSE) up -d; \
		sleep 5; \
		$(COMPOSE) exec -T php sh -c "composer install --no-interaction || true"; \
	fi

shell: ensure-up
	$(COMPOSE) exec php sh

install:
	@echo "📦 Building bundle assets (pnpm)..."
	$(COMPOSE) exec -T php sh -c "cd /var/pdf-signable-bundle && CI=true pnpm install && pnpm run build"
	@echo "📦 Installing PHP dependencies..."
	$(COMPOSE) exec -T php sh -c 'sed -i '\''s|"url":"../.."|"url":"/var/pdf-signable-bundle"|g'\'' composer.json && ( test -f composer.lock && sed -i '\''s|"url":"../.."|"url":"/var/pdf-signable-bundle"|g'\'' composer.lock; true ) && rm -rf vendor/nowo-tech/pdf-signable-bundle && composer install --no-interaction && rm -rf vendor/nowo-tech/pdf-signable-bundle && ln -sfn /var/pdf-signable-bundle vendor/nowo-tech/pdf-signable-bundle && sed -i '\''s|"url":"/var/pdf-signable-bundle"|"url":"../.."|g'\'' composer.json && ( test -f composer.lock && sed -i '\''s|"url":"/var/pdf-signable-bundle"|"url":"../.."|g'\'' composer.lock; true )'
	@echo "📦 Installing bundle assets..."
	$(COMPOSE) exec -T php php bin/console assets:install
	@echo "✅ Dependencies installed"

setup: install
	$(COMPOSE) exec -T php php bin/console cache:clear
	@echo "✅ Demo ready at http://localhost:8002"

run: up
	@echo "📦 Building bundle assets (pnpm)..."
	@$(COMPOSE) exec -T php sh -c "cd /var/pdf-signable-bundle && CI=true pnpm install && pnpm run build"
	@echo "📦 Installing PHP dependencies..."
	@$(COMPOSE) exec -T -e COMPOSER_MEMORY_LIMIT=-1 php sh -c 'sed -i '\''s|"url":"../.."|"url":"/var/pdf-signable-bundle"|g'\'' composer.json composer.lock && rm -rf vendor/nowo-tech/pdf-signable-bundle && composer install --no-interaction && rm -rf vendor/nowo-tech/pdf-signable-bundle && ln -sfn /var/pdf-signable-bundle vendor/nowo-tech/pdf-signable-bundle && sed -i '\''s|"url":"/var/pdf-signable-bundle"|"url":"../.."|g'\'' composer.json composer.lock'
	@echo "📦 Installing bundle assets..."
	@$(COMPOSE) exec -T php php bin/console assets:install
	@$(COMPOSE) exec -T php php bin/console cache:clear
	@echo "✅ Demo ready: http://localhost:8002"

test: ensure-up
	$(COMPOSE) exec -T php composer test 2>/dev/null || $(COMPOSE) exec -T php php bin/console list

test-coverage: ensure-up
	$(COMPOSE) exec -T php composer test-coverage 2>/dev/null || $(COMPOSE) exec -T php composer test

update-bundle: ensure-up
	@echo "📦 Rebuilding bundle assets..."
	$(COMPOSE) exec -T php sh -c "cd /var/pdf-signable-bundle && CI=true pnpm install && pnpm run build"
	@echo "📦 Refreshing bundle assets in public..."
	$(COMPOSE) exec -T php php bin/console assets:install
	$(COMPOSE) exec -T php php bin/console cache:clear
	@echo "✅ Bundle code updated in demo. http://localhost:8002"

clean: down
	rm -rf vendor var/cache var/log
	@echo "✅ Cleanup done"

logs:
	$(COMPOSE) logs -f


# REQ-DEMO-013: Playwright e2e + screenshots (official image). The demo serves HTTPS only
# (Caddy `tls internal`), so Playwright joins the php container's network namespace and targets
# https://localhost (ignoreHTTPSErrors in playwright.config.ts). No port/TLS changes needed.
PLAYWRIGHT_IMAGE ?= mcr.microsoft.com/playwright:v1.63.0-jammy
PHP_CONTAINER ?= pdf-signable-symfony8

test-e2e: ensure-up
	@mkdir -p e2e/node_modules
	docker run --rm --network container:$(PHP_CONTAINER) \
		-v "$(CURDIR)/e2e:/e2e" -w /e2e \
		-e DEMO_BASE_URL=https://localhost \
		$(PLAYWRIGHT_IMAGE) \
		bash -lc 'npm install --no-fund --no-audit && npx playwright test --project=chromium'

demo-screenshots: ensure-up
	@mkdir -p ../../docs/images/demo e2e/node_modules
	docker run --rm --network container:$(PHP_CONTAINER) \
		-v "$(CURDIR)/e2e:/e2e" \
		-v "$(CURDIR)/../../docs/images/demo:/screenshots" \
		-w /e2e \
		-e DEMO_BASE_URL=https://localhost \
		-e SCREENSHOT_DIR=/screenshots \
		$(PLAYWRIGHT_IMAGE) \
		bash -lc 'npm install --no-fund --no-audit && npx playwright test --project=screenshots && node scripts/to-webp.mjs /screenshots'
	@echo "Screenshots written under docs/images/demo/ (WebP)"

# REQ-MAKE-008: update-deps (REQ-MAKE-008)
BUNDLE_ROOT := $(abspath $(dir $(lastword $(MAKEFILE_LIST)))/../..)
# Optional: monorepo helper absent on standalone GitHub Actions checkout (REQ-MAKE-009).
-include $(BUNDLE_ROOT)/../.scripts/Makefile.demo-update-deps.mk
