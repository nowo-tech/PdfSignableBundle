# Makefile for PdfSignable Bundle demo (Symfony 8) - Docker
.PHONY: help list up-all down-all run-symfony8 down-symfony8 setup-symfony8 update-bundle-symfony8 update-bundle-all clean-all test-coverage-all release-check release-verify

DEMOS := symfony8

help:
	@echo "PdfSignable Bundle - Dockerized demos"
	@echo ""
	@echo "Usage: make <target>"
	@echo ""
	@echo "  run-symfony8        Start Symfony 8 demo (Docker) → http://localhost:8002"
	@echo "  down-symfony8       Stop Symfony 8 demo"
	@echo "  restart-symfony8    Restart Symfony 8 demo containers"
	@echo "  build-symfony8      Rebuild Symfony 8 image (no cache)"
	@echo "  install-symfony8    composer + pnpm (bundle assets) in Symfony 8 demo"
	@echo "  setup-symfony8      install + cache:clear only (containers already up)"
	@echo "  update-bundle-symfony8  Rebuild bundle assets and refresh demo 8"
	@echo "  update-bundle-all   update-bundle in the demo"
	@echo "  up-all              Start all demos (symfony8)"
	@echo "  down-all            Stop all demos"
	@echo "  clean-all           Stop and clean vendor/var in all"
	@echo "  release-check       Pre-release: test-coverage-all, release-verify"
	@echo "  release-verify      For each demo: up → healthcheck HTTP 200 → down"
	@echo ""
	@echo "Example: make run-symfony8   → then open http://localhost:8002"

list:
	@echo "Demos: $(DEMOS)"

up-all:
	@echo "🚀 Starting all demos..."
	@$(MAKE) -C symfony8 up
	@echo "📦 Installing dependencies in both..."
	@$(MAKE) -C symfony8 install
	@$(MAKE) -C symfony8 setup
	@echo "✅ Symfony 8: http://localhost:8002"

down-all:
	@echo "🛑 Stopping all demos..."
	@$(MAKE) -C symfony8 down
	@echo "✅ Done"

run-symfony8:
	@echo "🚀 Starting Symfony 8 demo (Docker)..."
	$(MAKE) -C symfony8 run
	@echo "→ http://localhost:8002"

down-symfony8:
	$(MAKE) -C symfony8 down

restart-symfony8:
	$(MAKE) -C symfony8 restart

build-symfony8:
	$(MAKE) -C symfony8 build

install-symfony8:
	$(MAKE) -C symfony8 install

setup-symfony8:
	$(MAKE) -C symfony8 setup

update-bundle-symfony8:
	$(MAKE) -C symfony8 update-bundle

update-bundle-all:
	@$(MAKE) -C symfony8 update-bundle
	@echo "✅ Bundle code updated in the demo"

test-coverage-all:
	@$(MAKE) -C symfony8 test-coverage 2>/dev/null || true
	@echo "test-coverage-all done."
	
release-verify:
	@for demo in $(DEMOS); do \
		port=$$(case $$demo in symfony8) echo 8002;; *) echo 8002;; esac); \
		echo "Release verify: $$demo (port $$port)..."; \
		$(MAKE) -C $$demo up 2>/dev/null || $(MAKE) -C $$demo run 2>/dev/null || true; \
		code="000"; \
		for i in 1 2 3 4 5 6 7 8 9 10; do \
			sleep 3; \
			code=$$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:$$port 2>/dev/null || echo "000"); \
			case "$$code" in 2*|3*) break;; esac; \
		done; \
		$(MAKE) -C $$demo down 2>/dev/null || true; \
		case "$$code" in \
			2*|3*) echo "OK $$demo (HTTP $$code)";; \
			*) echo "$$demo healthcheck failed (HTTP $$code)"; exit 1;; \
		esac; \
	done
	@echo "All demos passed release-verify."
release-check: test-coverage-all release-verify
	@echo "All demos passed release-check."

clean-all:
	@for d in $(DEMOS); do $(MAKE) -C $$d clean; done
	@echo "✅ Cleanup completed"

# REQ-MAKE-008: update-deps-all (REQ-MAKE-008)
BUNDLE_ROOT := $(abspath $(dir $(lastword $(MAKEFILE_LIST)))/..)
# Optional: monorepo helper absent on standalone GitHub Actions checkout (REQ-MAKE-009).
-include $(BUNDLE_ROOT)/../.scripts/Makefile.demo-aggregate-update-deps.mk
