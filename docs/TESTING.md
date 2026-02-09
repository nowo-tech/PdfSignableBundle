# Tests and code coverage

## Running tests

From the bundle root (with Docker):

```bash
make up
make install
make test          # PHPUnit
make test-coverage # PHPUnit + HTML (coverage/) and Clover (coverage.xml). Requires PCOV.
make qa            # cs-check + test
```

Or locally: `composer test`, `composer test-coverage`, `composer qa`.

## Test structure

| Directory | What is tested |
|-----------|----------------|
| `tests/Bundle/` | `NowoPdfSignableBundle` (extension presence) |
| `tests/Controller/` | `SignatureController`: index (GET/POST, JSON/redirect), proxy (disabled, invalid URL, allowlist, SSRF) |
| `tests/DependencyInjection/` | `Configuration`, `PdfSignableExtension` (YAML and service wiring) |
| `tests/Event/` | `PdfProxyRequestEvent`, `PdfProxyResponseEvent`, `SignatureCoordinatesSubmittedEvent`, event names |
| `tests/Form/` | `SignatureBoxType`, `SignatureCoordinatesType` (options, submit, validation, view vars, overlap/sort) |
| `tests/Model/` | `SignatureBoxModel`, `SignatureCoordinatesModel` (getters/setters, serialization) |

Controller tests for `index()` use a minimal container (form factory with HttpFoundation extension, Twig, router, request stack) so that GET renders the form and POST validates and either redirects with flash or returns JSON when `Accept: application/json`.

## Code coverage

Coverage is generated with PCOV (included in the bundle Docker image). Reports:

- **HTML:** `coverage/index.html`
- **Clover XML:** `coverage.xml` (for CI / tools)

Typical summary after a full run:

- **Lines:** ~84%
- **Methods:** ~85%
- **Classes:** ~67% (8/12; some form/controller branches uncovered)

### Well covered

- **Configuration**, **PdfSignableExtension**, **Events**, **Models** — 100% lines/methods where applicable.
- **SignatureController** — index (GET, POST redirect, POST JSON), proxy (disabled, invalid URL, allowlist, SSRF blocks, event response). The branch that performs a real HTTP fetch when the proxy event does not provide a response is not covered (would require integration/HTTP mock).
- **Form types** — Submit, options, validation, view vars and helpers (e.g. `boxesOverlap`, `getAllUnits`) are covered.

### Partially covered

- **SignatureController:** Remaining uncovered code is mainly the live HTTP client path in `proxyPdf()` and edge cases in SSRF/allowlist.
- **SignatureCoordinatesType:** Some internal helpers and config-merge paths are not fully exercised.
- **SignatureBoxType:** One method (e.g. a code path in `buildForm`) may show as uncovered depending on options used in tests.

## Adding tests

- Use PHPUnit 10 and the existing `tests/bootstrap.php`.
- For new form options or validation, extend the corresponding `tests/Form/*Test.php` and reuse `getExtensions()` / form factory setup where possible.
- For controller behaviour, extend `SignatureControllerTest` and use `createController()` / `createContainerForIndex()` to keep container and request setup consistent.
