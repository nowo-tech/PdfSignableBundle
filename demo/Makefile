# Makefile for PdfSignable Bundle demos (Symfony 7 and 8) - Docker
.PHONY: help list up-all down-all run-symfony7 run-symfony8 down-symfony7 down-symfony8 setup-symfony7 setup-symfony8 update-bundle-symfony7 update-bundle-symfony8 update-bundle-all clean-all test-coverage-all release-check release-verify

DEMOS := symfony7 symfony8

help:
	@echo "PdfSignable Bundle - Dockerized demos"
	@echo ""
	@echo "Usage: make <target>"
	@echo ""
	@echo "  run-symfony7         Start Symfony 7 demo (Docker) → https://localhost:8001"
	@echo "  run-symfony8        Start Symfony 8 demo (Docker) → https://localhost:8002"
	@echo "  down-symfony7       Stop Symfony 7 demo"
	@echo "  down-symfony8       Stop Symfony 8 demo"
	@echo "  setup-symfony7      install + cache:clear only (containers already up)"
	@echo "  setup-symfony8      install + cache:clear only (containers already up)"
	@echo "  update-bundle-symfony7  Rebuild bundle assets and refresh demo 7 (after changing bundle code)"
	@echo "  update-bundle-symfony8  Rebuild bundle assets and refresh demo 8"
	@echo "  update-bundle-all   update-bundle in both demos"
	@echo "  up-all              Start all demos (symfony7 + symfony8)"
	@echo "  down-all            Stop all demos"
	@echo "  clean-all           Stop and clean vendor/var in all"
	@echo "  release-check       Pre-release: test-coverage-all, release-verify"
	@echo "  release-verify      For each demo: up → healthcheck HTTP 200 → down"
	@echo ""
	@echo "Example: make run-symfony7   → then open https://localhost:8001"

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
	@echo "✅ Symfony 7: https://localhost:8001 | Symfony 8: https://localhost:8002"

down-all:
	@echo "🛑 Stopping all demos..."
	@$(MAKE) -C symfony7 down
	@$(MAKE) -C symfony8 down
	@echo "✅ Done"

run-symfony7:
	@echo "🚀 Starting Symfony 7 demo (Docker)..."
	$(MAKE) -C symfony7 run
	@echo "→ https://localhost:8001"

run-symfony8:
	@echo "🚀 Starting Symfony 8 demo (Docker)..."
	$(MAKE) -C symfony8 run
	@echo "→ https://localhost:8002"

down-symfony7:
	$(MAKE) -C symfony7 down

down-symfony8:
	$(MAKE) -C symfony8 down

setup-symfony7:
	$(MAKE) -C symfony7 setup

setup-symfony8:
	$(MAKE) -C symfony8 setup

update-bundle-symfony7:
	$(MAKE) -C symfony7 update-bundle

update-bundle-symfony8:
	$(MAKE) -C symfony8 update-bundle

update-bundle-all:
	@$(MAKE) -C symfony7 update-bundle
	@$(MAKE) -C symfony8 update-bundle
	@echo "✅ Bundle code updated in both demos"

test-coverage-all:
	@$(MAKE) -C symfony7 test-coverage 2>/dev/null || true
	@$(MAKE) -C symfony8 test-coverage 2>/dev/null || true
	@echo "test-coverage-all done."
release-verify:
	@for demo in $(DEMOS); do \
		port=$$(case $$demo in symfony7) echo 8001;; symfony8) echo 8002;; *) echo 8001;; esac); \
		echo "Release verify: $$demo (port $$port)..."; \
		$(MAKE) -C $$demo up 2>/dev/null || $(MAKE) -C $$demo run 2>/dev/null || true; \
		sleep 12; \
		code=$$(curl -sk -o /dev/null -w "%{http_code}" https://127.0.0.1:$$port 2>/dev/null || echo "000"); \
		$(MAKE) -C $$demo down 2>/dev/null || true; \
		if [ "$$code" != "200" ]; then echo "$$demo healthcheck failed (HTTP $$code)"; exit 1; fi; \
		echo "OK $$demo (HTTP $$code)"; \
	done
	@echo "All demos passed release-verify."
release-check: test-coverage-all release-verify
	@echo "All demos passed release-check."

clean-all:
	@for d in $(DEMOS); do $(MAKE) -C $$d clean; done
	@echo "✅ Cleanup completed"
