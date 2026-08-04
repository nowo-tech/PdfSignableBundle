# Makefile for PdfSignable Bundle (tests and QA at bundle root)
COMPOSE_FILE := docker-compose.yml
# Prefer Compose V2 plugin (GitHub Actions / modern Docker Desktop); fall back to docker-compose V1 (REQ-MAKE-010).
COMPOSE_BIN := $(shell docker compose version >/dev/null 2>&1 && echo "docker compose" || echo "docker-compose")
COMPOSE     := $(COMPOSE_BIN) -f $(COMPOSE_FILE)
SERVICE_PHP := php

.PHONY: help up down build shell install assets test test-coverage coverage-check coverage-php-percent cs-check cs-fix qa validate-translations clean ensure-up rector rector-dry phpstan release-check release-check-demos composer-sync update validate assets-build assets-test assets-dev assets-watch assets-clean test-ts test-python test-poc update-deps update-deps-demos check-no-cursor-coauthor check-open-prs strip-cursor-coauthor-from-history demo-smoke check-twig-extra

help:
	@echo "PdfSignable Bundle - Development Commands"
	@echo ""
	@echo "Usage: make <target>"
	@echo ""
	@echo "Targets:"
	@echo "  up                  Start Docker container"
	@echo "  down                Stop Docker container"
	@echo "  build               Rebuild Docker image (no cache)"
	@echo "  shell               Open shell in container"
	@echo "  install             Install Composer and pnpm dependencies"
	@echo "  assets              Build bundle assets (pnpm install + pnpm run build)"
	@echo "  test                Run PHPUnit tests"
	@echo "  test-coverage       Run tests with coverage"
	@echo "  cs-check            Check code style"
	@echo "  cs-fix              Fix code style"
	@echo "  rector              Apply Rector refactoring"
	@echo "  rector-dry          Run Rector in dry-run mode"
	@echo "  phpstan             Run PHPStan static analysis"
	@echo "  qa                  Run all QA (cs-check + test)"
	@echo "  release-check       Pre-release: cs-fix, cs-check, rector-dry, phpstan, test-coverage, test-ts, demo healthchecks"
	@echo "  composer-sync       Validate composer.json and align composer.lock"
	@echo "  clean               Remove vendor, cache, coverage"
	@echo "  update              Update composer.lock (composer update)"
	@echo "  update-deps         Update bundle + demo Composer dependencies"
	@echo "  validate            Run composer validate --strict"
	@echo ""
	@echo "Bundle-specific:"
	@echo "  assets-build        Alias of assets"
	@echo "  test-ts             Run TypeScript (Vitest) tests"
	@echo "  assets-test         Alias of test-ts"
	@echo "  assets-dev          Build assets in development mode"
	@echo "  assets-watch        Watch assets for changes"
	@echo "  assets-clean        Clean built assets"
	@echo "  validate-translations  Validate translation YAML files"
	@echo "  test-python         Run Python (pytest) tests"
	@echo "  test-poc            Run PoC: blank PDF → add fields → modify (.scripts/PoC)"
	@echo ""
	@echo "Demos:"
	@echo "  (use make -C demo or make -C demo/symfonyX)"
	@echo ""

# Rebuild Docker image (no cache)
build:
	$(COMPOSE) -f docker-compose.yml build --no-cache

# Build and start container (root $(COMPOSE))
up:
	$(COMPOSE) -f docker-compose.yml build
	$(COMPOSE) -f docker-compose.yml up -d
	@sleep 2
	$(COMPOSE) exec -T php composer install --no-interaction
	@echo "Container listo."

# Stop container (root $(COMPOSE))
down:
	$(COMPOSE) -f docker-compose.yml down

# Ensure root container is running (start if not). Used by cs-fix, cs-check, qa, install, test, test-coverage.
ensure-up:
	@if ! $(COMPOSE) -f docker-compose.yml exec -T php true 2>/dev/null; then \
		echo "Starting container (root $(COMPOSE))..."; \
		$(COMPOSE) -f docker-compose.yml up -d; \
		sleep 3; \
		$(COMPOSE) -f docker-compose.yml exec -T php composer install --no-interaction; \
	fi
# Open shell in container (root $(COMPOSE))
shell:
	$(COMPOSE) -f docker-compose.yml exec php sh

# Install dependencies (runs inside root $(COMPOSE) php container)
install: ensure-up
	$(COMPOSE) exec -T php composer install --no-interaction
	$(COMPOSE) exec -T -e CI=true php pnpm install
	@echo "Dependencias instaladas (composer + pnpm)."

assets: ensure-up
	$(COMPOSE) exec -T -e CI=true php pnpm install
	$(COMPOSE) exec -T php pnpm run build

# Alias: same as assets (unified name for bundles with TS)
assets-build: assets

# Run Vitest tests for TypeScript — runs inside container
test-ts: ensure-up
	@echo "Running TypeScript tests with coverage (Vitest)..."
	$(COMPOSE) exec -T -e CI=true php pnpm install
	$(COMPOSE) exec -T php pnpm run test:coverage | tee coverage-ts.txt
	./.scripts/ts-coverage-percent.sh coverage-ts.txt
	@echo "✅ TypeScript tests with coverage done!"

# Alias of test-ts (deprecated name; use test-ts)
assets-test: test-ts

# Build assets in development mode — runs inside container
assets-dev: ensure-up
	@echo "Building assets in development mode..."
	$(COMPOSE) exec -T -e CI=true php pnpm install
	$(COMPOSE) exec -T php pnpm run build:dev
	@echo "✅ Assets built!"

# Watch assets for changes — runs inside container (interactive)
assets-watch: ensure-up
	@echo "Watching assets for changes..."
	$(COMPOSE) exec -e CI=true php sh -c "pnpm install && pnpm run watch"

# Clean built assets — runs inside container
assets-clean: ensure-up
	@echo "Cleaning built assets..."
	$(COMPOSE) exec -T php pnpm run clean
	@echo "✅ Assets cleaned!"

# Run tests (runs inside root $(COMPOSE) php container)
# Run tests (no -T so TTY is allocated and PHPUnit can show colors in console)
test: ensure-up
	$(COMPOSE) exec php composer test

test-python: ensure-up
	$(COMPOSE) exec -T php python3 -m pip install --break-system-packages -q pypdf pytest pytest-cov
	$(COMPOSE) exec -T php python3 -m pytest .scripts/test -v \
		--cov=extract_acroform_fields \
		--cov=apply_acroform_patches \
		--cov=process_modified_pdf \
		--cov-report=term-missing

test-poc: ensure-up
	$(COMPOSE) exec -T php sh -c 'apt-get update -qq && apt-get install -y -qq python3-pip >/dev/null 2>&1; python3 -m pip install --break-system-packages -q pypdf 2>/dev/null; python3 .scripts/PoC/run_poc.py'

# Run tests with coverage (no -T so coverage is shown in console with colors)
test-coverage: ensure-up
	$(COMPOSE) exec php composer test-coverage | tee coverage-php.txt
	./.scripts/php-coverage-percent.sh coverage-php.txt

# Check code style (runs inside root $(COMPOSE) php container)
cs-check: ensure-up
	$(COMPOSE) -f docker-compose.yml exec -T php composer cs-check

# Fix code style (runs inside root $(COMPOSE) php container)
cs-fix: ensure-up
	$(COMPOSE) -f docker-compose.yml exec -T php composer cs-fix

# Run all QA (runs inside root $(COMPOSE) php container)
qa: ensure-up
	$(COMPOSE) -f docker-compose.yml exec -T php composer qa

rector: ensure-up
	$(COMPOSE) -f docker-compose.yml exec -T php composer rector

rector-dry: ensure-up
	$(COMPOSE) -f docker-compose.yml exec -T php composer rector-dry

phpstan: ensure-up
	$(COMPOSE) -f docker-compose.yml exec -T php composer phpstan

composer-sync: ensure-up
	$(COMPOSE) -f docker-compose.yml exec -T php composer validate --strict
	$(COMPOSE) -f docker-compose.yml exec -T php composer update --no-install

# Update composer.lock
update: ensure-up
	$(COMPOSE) -f docker-compose.yml exec -T php composer update --no-interaction

# Validate composer.json
validate: ensure-up
	$(COMPOSE) -f docker-compose.yml exec -T php composer validate --strict


check-twig-extra:
	@chmod +x .scripts/check-twig-extra.sh
	@./.scripts/check-twig-extra.sh
release-check: check-no-cursor-coauthor check-open-prs check-twig-extra ensure-up composer-sync cs-fix cs-check rector-dry phpstan coverage-check test-ts test-python release-check-demos

coverage-check: test-coverage
	@chmod +x .scripts/coverage-fail-under.sh
	@./.scripts/coverage-fail-under.sh coverage-php.txt 99

check-open-prs:
	@chmod +x .scripts/check-open-prs.sh
	@GH_REPO=nowo-tech/PdfSignableBundle ./.scripts/check-open-prs.sh

demo-smoke:
	@if [ -f demo/Makefile ]; then $(MAKE) -C demo release-verify; else echo "No demo/Makefile"; exit 1; fi

release-check-demos:
	@$(MAKE) -C demo release-check

validate-translations: ensure-up
	$(COMPOSE) exec -T php php .scripts/validate-translations-yaml.php

# Clean vendor and cache
clean:
	rm -rf vendor
	rm -rf .phpunit.cache
	rm -rf coverage
	rm -f coverage.xml
	rm -f .php-cs-fixer.cache



setup-hooks:
	@chmod +x .githooks/pre-commit 2>/dev/null || true
	@chmod +x .githooks/commit-msg 2>/dev/null || true
	@git config core.hooksPath .githooks
	@echo "✅ Git hooks installed (.githooks — includes commit-msg for REQ-GIT-001)."

# REQ-MAKE-008: update-deps (REQ-MAKE-008)
BUNDLE_ROOT := $(abspath $(dir $(lastword $(MAKEFILE_LIST))))
# Optional: monorepo helper absent on standalone GitHub Actions checkout (REQ-MAKE-009).
-include $(BUNDLE_ROOT)/../.scripts/Makefile.update-deps.mk
check-no-cursor-coauthor:
	@chmod +x .scripts/check-no-cursor-coauthor.sh
	@./.scripts/check-no-cursor-coauthor.sh HEAD

strip-cursor-coauthor-from-history:
	@chmod +x .scripts/strip-cursor-coauthor-from-history.sh
	@./.scripts/strip-cursor-coauthor-from-history.sh main

twig-lint: ensure-up
	@$(COMPOSE) exec -T $(SERVICE_PHP) composer twig:lint || $(COMPOSE) exec -T $(SERVICE_PHP) ./vendor/bin/twig-cs-fixer lint --config=.twig-cs-fixer.php
