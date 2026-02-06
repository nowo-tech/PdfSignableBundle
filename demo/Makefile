# Makefile for PdfSignable Bundle demos (Symfony 7 and 8) - Docker
.PHONY: help list up-all down-all run-symfony7 run-symfony8 down-symfony7 down-symfony8 setup-symfony7 setup-symfony8 clean-all

DEMOS := symfony7 symfony8

help:
	@echo "PdfSignable Bundle - Dockerized demos"
	@echo ""
	@echo "Usage: make <target>"
	@echo ""
	@echo "  run-symfony7    Start Symfony 7 demo (Docker) → http://localhost:8000"
	@echo "  run-symfony8    Start Symfony 8 demo (Docker) → http://localhost:8001"
	@echo "  down-symfony7   Stop Symfony 7 demo"
	@echo "  down-symfony8   Stop Symfony 8 demo"
	@echo "  setup-symfony7  install + cache:clear only (containers already up)"
	@echo "  setup-symfony8  install + cache:clear only (containers already up)"
	@echo "  up-all          Start all demos (symfony7 + symfony8)"
	@echo "  down-all        Stop all demos"
	@echo "  clean-all       Stop and clean vendor/var in all"
	@echo ""
	@echo "Example: make run-symfony7   → then open http://localhost:8000"

list:
	@echo "Demos: $(DEMOS)"

up-all:
	@echo "🚀 Starting all demos..."
	@$(MAKE) -C symfony7 up
	@$(MAKE) -C symfony8 up
	@echo "📦 Installing dependencies in both..."
	@$(MAKE) -C symfony7 install
	@$(MAKE) -C symfony8 install
	@$(MAKE) -C symfony7 setup
	@$(MAKE) -C symfony8 setup
	@echo "✅ Symfony 7: http://localhost:8000 | Symfony 8: http://localhost:8001"

down-all:
	@echo "🛑 Stopping all demos..."
	@$(MAKE) -C symfony7 down
	@$(MAKE) -C symfony8 down
	@echo "✅ Done"

run-symfony7:
	@echo "🚀 Starting Symfony 7 demo (Docker)..."
	$(MAKE) -C symfony7 run
	@echo "→ http://localhost:8000"

run-symfony8:
	@echo "🚀 Starting Symfony 8 demo (Docker)..."
	$(MAKE) -C symfony8 run
	@echo "→ http://localhost:8001"

down-symfony7:
	$(MAKE) -C symfony7 down

down-symfony8:
	$(MAKE) -C symfony8 down

setup-symfony7:
	$(MAKE) -C symfony7 setup

setup-symfony8:
	$(MAKE) -C symfony8 setup

clean-all:
	@for d in $(DEMOS); do $(MAKE) -C $$d clean; done
	@echo "✅ Cleanup completed"
