# PdfSignable Bundle Demo - Symfony 7 (Docker, FrankenPHP + Caddy, HTTPS)
.PHONY: help up down shell install setup run update-bundle clean logs

help:
	@echo "PdfSignable Demo (Symfony 7) - Docker (FrankenPHP + Caddy)"
	@echo ""
	@echo "  up            - Start container (FrankenPHP)"
	@echo "  down          - Stop container"
	@echo "  shell         - Shell into PHP container"
	@echo "  install       - composer install in container"
	@echo "  setup         - install + cache:clear"
	@echo "  run           - up + setup (start and ready)"
	@echo "  update-bundle - Rebuild bundle assets and refresh symlinks/cache (use after changing bundle code)"
	@echo "  clean         - Stop and remove vendor/var"
	@echo "  logs          - View logs"
	@echo ""
	@echo "After make run: https://localhost:8001 (accept self-signed cert in browser)"

up:
	@if [ ! -f .env ]; then \
		cp .env.dist .env; \
		echo "✅ .env created from .env.dist"; \
	fi
	docker-compose build
	docker-compose up -d
	@echo "⏳ Waiting for services..."
	@sleep 3
	@echo "✅ Containers up. Run: make install && make setup"

down:
	docker-compose down
	@echo "✅ Containers stopped"

shell:
	docker-compose exec php sh

install:
	@echo "📦 Building bundle assets (pnpm)..."
	docker-compose exec -T php sh -c "cd /var/pdf-signable-bundle && CI=true pnpm install && pnpm run build"
	@echo "📦 Installing PHP dependencies..."
	docker-compose exec -T php sh -c 'sed -i '\''s|"url":"../.."|"url":"/var/pdf-signable-bundle"|g'\'' composer.json && ( test -f composer.lock && sed -i '\''s|"url":"../.."|"url":"/var/pdf-signable-bundle"|g'\'' composer.lock; true ) && rm -rf vendor/nowo-tech/pdf-signable-bundle && composer install --no-interaction && rm -rf vendor/nowo-tech/pdf-signable-bundle && ln -sfn /var/pdf-signable-bundle vendor/nowo-tech/pdf-signable-bundle && sed -i '\''s|"url":"/var/pdf-signable-bundle"|"url":"../.."|g'\'' composer.json && ( test -f composer.lock && sed -i '\''s|"url":"/var/pdf-signable-bundle"|"url":"../.."|g'\'' composer.lock; true )'
	@echo "📦 Installing bundle assets..."
	docker-compose exec -T php php bin/console assets:install
	@echo "✅ Dependencies installed"

setup: install
	docker-compose exec -T php php bin/console cache:clear
	@echo "✅ Demo ready at https://localhost:8001"

run: up
	@echo "📦 Building bundle assets (pnpm)..."
	@docker-compose exec -T php sh -c "cd /var/pdf-signable-bundle && CI=true pnpm install && pnpm run build"
	@echo "📦 Installing PHP dependencies..."
	@docker-compose exec -T php sh -c 'sed -i '\''s|"url":"../.."|"url":"/var/pdf-signable-bundle"|g'\'' composer.json composer.lock && rm -rf vendor/nowo-tech/pdf-signable-bundle && composer install --no-interaction && rm -rf vendor/nowo-tech/pdf-signable-bundle && ln -sfn /var/pdf-signable-bundle vendor/nowo-tech/pdf-signable-bundle && sed -i '\''s|"url":"/var/pdf-signable-bundle"|"url":"../.."|g'\'' composer.json composer.lock'
	@echo "📦 Installing bundle assets..."
	@docker-compose exec -T php php bin/console assets:install
	@docker-compose exec -T php php bin/console cache:clear
	@echo "✅ Demo ready: https://localhost:8001"

update-bundle:
	@echo "📦 Rebuilding bundle assets..."
	docker-compose exec -T php sh -c "cd /var/pdf-signable-bundle && CI=true pnpm install && pnpm run build"
	@echo "📦 Refreshing bundle assets in public..."
	docker-compose exec -T php php bin/console assets:install
	docker-compose exec -T php php bin/console cache:clear
	@echo "✅ Bundle code updated in demo. https://localhost:8001"

clean: down
	rm -rf vendor var/cache var/log
	@echo "✅ Cleanup done"

logs:
	docker-compose logs -f
