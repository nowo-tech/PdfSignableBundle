# Makefile for PdfSignable Bundle (tests and QA at bundle root)
.PHONY: help up down shell install assets test test-coverage cs-check cs-fix qa validate-translations clean

help:
	@echo "PdfSignable Bundle - Development"
	@echo ""
	@echo "  up                  Start Docker container"
	@echo "  down                Stop Docker container"
	@echo "  shell               Open shell in container"
	@echo "  install             composer & pnpm install (local or in container)"
	@echo "  assets              Build bundle assets (pnpm install + pnpm run build)"
	@echo "  test                Run PHPUnit tests"
	@echo "  test-ts             Run TypeScript (Vitest) tests"
	@echo "  test-python         Run Python (pytest) tests"
	@echo "  test-poc            Run PoC: blank PDF → add fields → modify (scripts/PoC)"
	@echo "  test-coverage       Run tests with coverage"
	@echo "  cs-check            Code style check"
	@echo "  cs-fix              Code style fix"
	@echo "  qa                  cs-check + test"
	@echo "  validate-translations  Validate translation YAML files"
	@echo "  clean               Remove vendor, cache, coverage"

# Build and start container (root docker-compose)
up:
	docker-compose -f docker-compose.yml build
	docker-compose -f docker-compose.yml up -d
	@sleep 2
	docker-compose exec -T php composer install --no-interaction
	@echo "Container listo."

# Stop container (root docker-compose)
down:
	docker-compose -f docker-compose.yml down

# Ensure root container is running (start if not). Used by cs-fix, cs-check, qa, install, test, test-coverage.
ensure-up:
	@if ! docker-compose -f docker-compose.yml exec -T php true 2>/dev/null; then \
		echo "Starting container (root docker-compose)..."; \
		docker-compose -f docker-compose.yml up -d; \
		sleep 3; \
		docker-compose -f docker-compose.yml exec -T php composer install --no-interaction; \
	fi
# Open shell in container (root docker-compose)
shell:
	docker-compose -f docker-compose.yml exec php sh

# Install dependencies (runs inside root docker-compose php container)
install: ensure-up
	docker-compose exec -T php composer install --no-interaction
	docker-compose exec -T -e CI=true php pnpm install
	@echo "Dependencias instaladas (composer + pnpm)."

assets: ensure-up
	docker-compose exec -T php pnpm install
	docker-compose exec -T php pnpm run build

# Run tests (runs inside root docker-compose php container)
test: ensure-up
	docker-compose exec -T php composer test

test-ts:
	docker-compose up -d
	docker-compose exec -T php pnpm install
	docker-compose exec -T php pnpm test

test-python:
	docker-compose up -d
	docker-compose exec -T php python3 -m pip install --break-system-packages -q pypdf pytest
	docker-compose exec -T php python3 -m pytest scripts/test -v

test-poc:
	docker-compose up -d
	docker-compose exec -T php sh -c 'apt-get update -qq && apt-get install -y -qq python3-pip >/dev/null 2>&1; python3 -m pip install --break-system-packages -q pypdf 2>/dev/null; python3 scripts/PoC/run_poc.py'

test-coverage:
	docker-compose up -d
	docker-compose exec -T php composer test-coverage

# Check code style (runs inside root docker-compose php container)
cs-check: ensure-up
	docker-compose -f docker-compose.yml exec -T php composer cs-check

# Fix code style (runs inside root docker-compose php container)
cs-fix: ensure-up
	docker-compose -f docker-compose.yml exec -T php composer cs-fix

# Run all QA (runs inside root docker-compose php container)
qa: ensure-up
	docker-compose -f docker-compose.yml exec -T php composer qa

validate-translations:
	docker-compose up -d
	docker-compose exec -T php php scripts/validate-translations-yaml.php

# Clean vendor and cache
clean:
	rm -rf vendor
	rm -rf .phpunit.cache
	rm -rf coverage
	rm -f coverage.xml
	rm -f .php-cs-fixer.cache
