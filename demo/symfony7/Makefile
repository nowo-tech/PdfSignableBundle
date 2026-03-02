# PdfSignable Bundle Demo - Symfony 7 (Docker, FrankenPHP + Caddy, HTTPS)
.PHONY: help up down restart build shell install setup run update-bundle clean logs ensure-up test test-coverage

help:
	@echo "PdfSignable Demo (Symfony 7) - Docker (FrankenPHP + Caddy)"
	@echo ""
	@echo "  up            - Start container (FrankenPHP)"
	@echo "  down          - Stop container"
	@echo "  restart       - Restart container (docker compose restart)"
	@echo "  build         - Rebuild image (no cache)"
	@echo "  shell         - Shell into PHP container"
	@echo "  install       - composer install in container"
	@echo "  setup         - install + cache:clear"
	@echo "  run           - up + setup (start and ready)"
	@echo "  update-bundle - Rebuild bundle assets and refresh symlinks/cache (use after changing bundle code)"
	@echo "  test          - Run tests"
	@echo "  test-coverage - Run tests with coverage (console output)"
	@echo "  clean         - Stop and remove vendor/var"
	@echo "  logs          - View logs"
	@echo ""
	@echo "After make run: http://localhost:8001 (HTTP on port 80)"

up:
	@if [ ! -f .env ]; then \
		cp .env.dist .env; \
		echo "✅ .env created from .env.dist"; \
	fi
	docker-compose up -d --build
	@echo "⏳ Waiting for container to be ready..."
	@sleep 5
	@echo "📦 Installing dependencies (composer + pnpm)..."
	@$(MAKE) install
	@echo "✅ Containers up. Run: make setup for cache:clear"

down:
	docker-compose down
	@echo "✅ Containers stopped"

restart: ensure-up
	docker-compose restart
	@echo "✅ Containers restarted"

build:
	docker-compose build --no-cache
	@echo "✅ Image rebuilt"

ensure-up:
	@if ! docker-compose exec -T php true 2>/dev/null; then \
		echo "Starting container..."; \
		docker-compose up -d; \
		sleep 5; \
	fi

shell: ensure-up
	docker-compose exec php sh

install: ensure-up
	@echo "📦 Building bundle assets (pnpm)..."
	docker-compose exec -T php sh -c "cd /var/pdf-signable-bundle && CI=true pnpm install && pnpm run build"
	@echo "📦 Installing PHP dependencies..."
	docker-compose exec -T -e COMPOSER_MEMORY_LIMIT=-1 php sh -c 'sed -i '\''s|"url":"../.."|"url":"/var/pdf-signable-bundle"|g'\'' composer.json && ( test -f composer.lock && sed -i '\''s|"url":"../.."|"url":"/var/pdf-signable-bundle"|g'\'' composer.lock; true ) && rm -rf vendor/nowo-tech/pdf-signable-bundle && composer install --no-interaction && rm -rf vendor/nowo-tech/pdf-signable-bundle && ln -sfn /var/pdf-signable-bundle vendor/nowo-tech/pdf-signable-bundle && sed -i '\''s|"url":"/var/pdf-signable-bundle"|"url":"../.."|g'\'' composer.json && ( test -f composer.lock && sed -i '\''s|"url":"/var/pdf-signable-bundle"|"url":"../.."|g'\'' composer.lock; true )'
	@echo "📦 Installing bundle assets..."
	docker-compose exec -T php php bin/console assets:install
	@echo "✅ Dependencies installed"

setup: install
	docker-compose exec -T php php bin/console cache:clear
	@echo "✅ Demo ready at http://localhost:8001"

run: up
	@echo "📦 Building bundle assets (pnpm)..."
	@docker-compose exec -T php sh -c "cd /var/pdf-signable-bundle && CI=true pnpm install && pnpm run build"
	@echo "📦 Installing PHP dependencies..."
	@docker-compose exec -T php sh -c 'sed -i '\''s|"url":"../.."|"url":"/var/pdf-signable-bundle"|g'\'' composer.json composer.lock && rm -rf vendor/nowo-tech/pdf-signable-bundle && composer install --no-interaction && rm -rf vendor/nowo-tech/pdf-signable-bundle && ln -sfn /var/pdf-signable-bundle vendor/nowo-tech/pdf-signable-bundle && sed -i '\''s|"url":"/var/pdf-signable-bundle"|"url":"../.."|g'\'' composer.json composer.lock'
	@echo "📦 Installing bundle assets..."
	@docker-compose exec -T php php bin/console assets:install
	@docker-compose exec -T php php bin/console cache:clear
	@echo "✅ Demo ready: http://localhost:8001"

test: ensure-up
	docker-compose exec -T php composer test 2>/dev/null || docker-compose exec -T php php bin/console list

test-coverage: ensure-up
	docker-compose exec -T php composer test-coverage 2>/dev/null || docker-compose exec -T php composer test

update-bundle: ensure-up
	@echo "📦 Rebuilding bundle assets..."
	docker-compose exec -T php sh -c "cd /var/pdf-signable-bundle && CI=true pnpm install && pnpm run build"
	@echo "📦 Refreshing bundle assets in public..."
	docker-compose exec -T php php bin/console assets:install
	docker-compose exec -T php php bin/console cache:clear
	@echo "✅ Bundle code updated in demo. http://localhost:8001"

clean: down
	rm -rf vendor var/cache var/log
	@echo "✅ Cleanup done"

logs:
	docker-compose logs -f
