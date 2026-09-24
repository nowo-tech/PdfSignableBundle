# FrankenPHP worker mode audit (kernel not reset between requests)

| Field | Value |
|-------|-------|
| Package | `nowo-tech/pdf-signable-bundle` (`symfony-bundle`) |
| Audited revision | `v3.1.7` (W-01 fix on top of `v3.1.6` / `749abc9`) |
| Audit date | 2026-09-24 |
| Method | Manual review of every file under `src/` (controllers, listeners, form types, Twig extension, storage, checker, command, DI extension, compiler passes, `Resources/config`) |
| **Verdict** | ✅ **Compatible** — safe under FrankenPHP worker with **kernel not reset** between requests (scenario B). No per-request state in services; W-01 temp-file leak fixed in 3.1.7. Residual Low notes (W-02 peak buffer, W-03 DNS) are ops/hardening, not cross-request state leaks. |

## Execution model assumed

FrankenPHP worker mode boots the Symfony kernel once per worker and serves many requests with the same container. This audit assumes the **strict** variant: the kernel is **not** rebooted between requests, so every shared service, static property and PHP global survives from one request to the next. Two scenarios are evaluated:

- **A — kernel not rebooted, `services_resetter` still runs:** services tagged `kernel.reset` (or implementing `ResetInterface`) are reset between requests.
- **B — no reset at all:** nothing is reset; any per-request state kept in a service leaks into the next request.

A bundle that is safe under **B** is safe under **A** and under classic mode / PHP-FPM.

## Summary

| Area | Status | Notes |
|------|--------|-------|
| Mutable state in shared services | ✅ | Every service has only `private readonly` constructor properties (config values, collaborators, optional test closures) |
| Static properties / `static` locals | ✅ | None. Static methods (`PythonProcessEnv::build()`, `*::fromArray()`, `SignatureCoordinatesType::getAllUnits()`) are pure |
| `ResetInterface` / `kernel.reset` coverage | ✅ N/A | Nothing to reset |
| Request / user / locale captured in services | ✅ | `SessionAcroFormOverridesStorage` and the Twig extension read `RequestStack::getCurrentRequest()` on every call; per-request flags live in request attributes |
| Superglobals, `$_ENV`, `putenv`, `ini_set`, `setlocale`, timezone | ✅ | None written. `PythonProcessEnv::build()` only reads `getenv()` to build a child-process env array |
| Doctrine / EntityManager | ✅ N/A | No persistence; overrides are stored in the user session by default |
| Output, headers, `exit`, shutdown functions | ✅ | None; all output goes through `Response` objects |
| Resources (files, sockets, cURL) held open | ✅ | Temp files created inside `try` and removed in `finally` with `is_string` / `is_file` guards (W-01 fixed in 3.1.7) |
| Memory growth across requests | ✅ | No caches or accumulating arrays; large PDFs are request-local (see W-02 for peak usage) |
| Blocking I/O and timeouts | ⚠️ Low | HTTP fetches and Python processes have explicit, configurable timeouts; SSRF DNS lookup has none (W-03) |
| Third-party static state | ✅ | Symfony HttpClient / Process only; no PDF library with global settings |
| PHPStan FrankenPHP rulesets | ✅ | `ruleset-classic.neon` + `ruleset-worker.neon` included in `phpstan.neon.dist` |

## Services reviewed

| Service | Shared | Mutable state | Scenario A | Scenario B |
|---------|--------|---------------|------------|------------|
| `Controller\SignatureController` | yes | none | ✅ | ✅ |
| `Controller\AcroFormOverridesController` (public) | yes | none | ✅ | ✅ |
| `AcroForm\Storage\SessionAcroFormOverridesStorage` | yes | none; session resolved per call (`src/AcroForm/Storage/SessionAcroFormOverridesStorage.php:36`, `51`, `61`) | ✅ | ✅ |
| `Proxy\ProxyUrlValidator` | yes | none (`readonly` allowlist) | ✅ | ✅ |
| `EventListener\AcroFormApplyScriptListener` | yes | none | ✅ | ✅ |
| `EventListener\DependencyCheckListener` | yes | none; result cached in request attributes (`src/EventListener/DependencyCheckListener.php:49-57`) | ✅ | ✅ |
| `Checker\DependencyChecker` | yes | none | ✅ | ✅ |
| `Twig\NowoPdfSignableTwigExtension` | yes | none; "assets included" flag stored on the request (`src/Twig/NowoPdfSignableTwigExtension.php:163-175`) | ✅ | ✅ |
| `Command\CheckDependenciesCommand` | yes (CLI only) | none | ✅ | ✅ |
| 4 form types + `SignatureCoordinatesTypeExtension` | yes | stateless (config-only `readonly` properties; form listeners are `static` closures created in `buildForm()`) | ✅ | ✅ |

Models and events (`SignatureCoordinatesModel`, `SignatureBoxModel`, `AcroFormOverrides`, `AcroFormFieldPatch`, `*Event`) are created per request and never stored in a service. Compiler passes (`TwigPathsPass`, `ProxyUrlAllowlistValidationPass`) only run at container compile time.

## Findings

### W-01 — Temp file leaked when the second `tempnam()` of a pair fails — **Fixed in 3.1.7**

- **Where (pre-fix):** `AcroFormOverridesController::process()` and `AcroFormApplyScriptListener` created two temp files and returned early if either was `false`, **before** entering `try/finally`.
- **Fix:** initialize temps to `false`, create both inside `try`, return on failure so `finally` unlinks whichever path is a string (`is_string` + `is_file`). Covered by unit/integration tests that assert the first file is gone when the second create fails.
- **Worker impact after fix:** no stray files accumulate across requests on this path.

### W-02 — Remote PDF bodies are fully buffered before any size check (Low)

- **Where:** `SignatureController` proxy (no size limit), `AcroFormOverridesController` apply / fields extract. Size is compared with `max_pdf_size` only after `getContent()` has loaded the body where that check exists; apply via `pdf_url` may not compare at all.
- **Worker impact:** a large upstream file raises **peak** memory of the worker thread. Memory is released at the end of the request (no cross-request growth), but crossing `memory_limit` kills the worker.
- **Recommendation:** stream with abort past `max_pdf_size`, or check `Content-Length` before reading. Ops: cap upstream size / FrankenPHP `max_wait_time`.

### W-03 — SSRF host resolution has no bundle-level timeout (Low)

- **Where:** `ProxyUrlValidator` (`gethostbyname()`), called before every proxy/apply/extract fetch.
- **Worker impact:** a slow resolver blocks the worker thread for the system resolver timeout. No state is leaked.
- **Recommendation:** keep resolver `options timeout:1 attempts:2` in the container and cap waiting requests with FrankenPHP `max_wait_time`.

### W-04 — `HttpClient::create()` fallback builds a new client per request (Info)

- **Where:** `SignatureController`, `AcroFormOverridesController` when no `HttpClientInterface` is autowired.
- **Worker impact:** loses connection reuse; does not leak state.
- **Recommendation:** enable framework `http_client` so the shared client is injected.

### W-05 — Debug dependency check spawns a Python process on bundle GET pages (Info)

- **Where:** `DependencyCheckListener` → `DependencyChecker` when `nowo_pdf_signable.debug: true`.
- **Worker impact:** result cached per request only; no leak across requests.
- **Recommendation:** keep `debug: false` in production.

No other findings. AcroForm overrides live in the current user's session, resolved on every call, so there is no cross-user leak path in the bundle.

## Usage recommendations in worker mode

- No special configuration or reset hook is needed for **kernel reset false**.
- Keep `http_timeout`, `process_timeout` and `process_script_timeout` short enough for your worker count; each Python run or remote fetch pins a worker thread for its duration.
- Keep `debug: false` in production (W-05).
- A custom `acroform.overrides_storage` or `acroform.editor_service_id` service must not keep document data, patches or the current user in properties between requests (or must implement `ResetInterface`). Always resolve the session/user per call, as `SessionAcroFormOverridesStorage` does.
- Listeners on `PdfSignableEvents::*` receive the `Request` and PDF bytes; do not store them in listener properties.
- The demo (`demo/symfony8`) runs in worker mode by default (`FRANKENPHP_MODE=worker`).

## Re-audit triggers

Re-run this audit when a change adds: properties to controllers, listeners, the storage or the Twig extension; a non-session overrides storage; a PHP PDF library (TCPDF, FPDI, mPDF, etc.) with global settings; caching of fetched PDFs or DNS results; or any use of `$_SERVER` / `$_ENV` at runtime.
