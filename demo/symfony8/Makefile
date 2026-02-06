# PdfSignable Bundle Demo - Symfony 8 (Docker)
.PHONY: help up down shell install setup run clean logs

help:
	@echo "PdfSignable Demo (Symfony 8) - Docker"
	@echo ""
	@echo "  up      - Start containers (PHP-FPM + Nginx)"
	@echo "  down    - Stop containers"
	@echo "  shell   - Shell into PHP container"
	@echo "  install - composer install in container"
	@echo "  setup   - install + cache:clear"
	@echo "  run     - up + setup (start and ready)"
	@echo "  clean   - Stop and remove vendor/var"
	@echo "  logs    - View logs"
	@echo ""
	@echo "After make run: http://localhost:8001"

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
	docker-compose exec -T php sh -c "cd /workspace && pnpm install && pnpm run build"
	@echo "📦 Installing PHP dependencies..."
	docker-compose exec -T php composer install --no-interaction
	@echo "📦 Installing bundle assets..."
	docker-compose exec -T php php bin/console assets:install --symlink
	@echo "✅ Dependencies installed"

setup: install
	docker-compose exec -T php php bin/console cache:clear
	@echo "✅ Demo ready at http://localhost:8001"

run: up
	@echo "📦 Building bundle assets (pnpm)..."
	@docker-compose exec -T php sh -c "cd /workspace && pnpm install && pnpm run build"
	@echo "📦 Installing PHP dependencies..."
	@docker-compose exec -T php composer install --no-interaction
	@echo "📦 Installing bundle assets..."
	@docker-compose exec -T php php bin/console assets:install --symlink
	@docker-compose exec -T php php bin/console cache:clear
	@echo "✅ Demo ready: http://localhost:8001"

clean: down
	rm -rf vendor var/cache var/log
	@echo "✅ Cleanup done"

logs:
	docker-compose logs -f
