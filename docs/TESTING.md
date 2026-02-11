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
| `tests/Event/` | `PdfProxyRequestEvent`, `PdfProxyResponseEvent`, `SignatureCoordinatesSubmittedEvent`, `BatchSignRequestedEvent`, `PdfSignRequestEvent`, and event name constants (`PdfSignableEventsTest`). See [EVENTS](EVENTS.md). |
| `tests/Form/` | `SignatureBoxType`, `SignatureCoordinatesType` (options, submit, validation, view vars, overlap/sort) |
| `tests/Model/` | `SignatureBoxModel`, `SignatureCoordinatesModel` (getters/setters, serialization) |
| `tests/Twig/` | `NowoPdfSignableTwigExtension` (`nowo_pdf_signable_include_assets` once per request) |

Controller tests for `index()` use a minimal container (form factory with HttpFoundation extension, Twig, router, request stack) so that GET renders the form and POST validates and either redirects with flash or returns JSON when `Accept: application/json`.

## Code coverage

Coverage is generated with PCOV (included in the bundle Docker image). Run from the bundle root:

```bash
make test-coverage   # or: docker-compose exec -T php composer test-coverage
```

Reports:

- **HTML:** `coverage/index.html`
- **Clover XML:** `coverage.xml` (for CI / tools)

Excluded from coverage: `src/Resources` (views, config, assets), and `src/Event/PdfSignableEvents.php` (constants + private constructor only).

### Typical summary

After a full run (121 tests):

- **Lines:** ~96%
- **Methods:** ~92%
- **Classes:** ~73% (11/15; 4 partial: SignatureController, SignatureBoxType, SignatureCoordinatesType, AuditMetadata)

### Well covered (100% lines/methods)

- **Configuration**, **PdfSignableExtension**, **PdfProxyRequestEvent**, **PdfProxyResponseEvent**, **SignatureCoordinatesSubmittedEvent**, **BatchSignRequestedEvent**, **PdfSignRequestEvent**, **Models** (SignatureBoxModel, SignatureCoordinatesModel), **NowoPdfSignableBundle**, **NowoPdfSignableTwigExtension**.
- **SignatureController** — index (GET, POST redirect, POST JSON), proxy (disabled, invalid URL, allowlist, SSRF blocks, event response). The branch that performs a real HTTP fetch when the proxy event does not provide a response is not covered (would require integration/HTTP mock).
- **Form types** — Submit, options, validation, view vars and helpers (e.g. `boxesOverlap`, `getAllUnits`) are covered.

### Partially covered

- **SignatureController** (~83% lines, 4/7 methods): Uncovered code is mainly the live HTTP client path in `proxyPdf()` and a few SSRF branches (e.g. unresolved hostname, ip2long false).
- **SignatureCoordinatesType** (~97% lines, 9/11 methods): Unique-name and overlap callbacks, sort_boxes PRE_SUBMIT, buildView, and boxFromArray are covered. Named config merge (url_field/unit_field/origin_field false overriding defaults) is covered by `testNamedConfigWithHiddenFieldsOverridesDefaults`.
- **SignatureBoxType** (~99% lines, 2/3 methods): One line in `configureOptions` (allowed_pages validator) may remain uncovered depending on PHPUnit execution order.
- **AuditMetadata**: Constants-only class (excluded from coverage in practice or low coverage); see `Nowo\PdfSignableBundle\Model\AuditMetadata`.

## Adding tests

- Use PHPUnit 10 and the existing `tests/bootstrap.php`.
- For new form options or validation, extend the corresponding `tests/Form/*Test.php` and reuse `getExtensions()` / form factory setup where possible.
- For controller behaviour, extend `SignatureControllerTest` and use `createController()` / `createContainerForIndex()` to keep container and request setup consistent.
