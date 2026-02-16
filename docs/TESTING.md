# Tests and code coverage

## Running tests

From the bundle root (with Docker):

```bash
make up
make install
make test          # PHPUnit
make test-ts       # TypeScript (Vitest) — utils, config, acroform-move-resize
make test-python   # Python (pytest) — AcroForm scripts (extract, apply, process)
make test-coverage # PHPUnit + HTML (coverage/) and Clover (coverage.xml). Requires PCOV.
make qa            # cs-check + test
```

Or locally: `composer test`, `composer test-coverage`, `composer qa` for PHP; `pnpm test` for TypeScript; `python3 -m pytest scripts/test -v` for Python.

## Test structure

| Directory | What is tested |
|-----------|----------------|
| `tests/Bundle/` | `NowoPdfSignableBundle` (extension presence) |
| `tests/Controller/` | `SignatureController`: index (GET/POST, JSON/redirect), proxy (disabled, invalid URL, allowlist, SSRF). `AcroFormOverridesController`: overrides GET/POST/DELETE (enabled/disabled, document_key validation, storage), apply (missing PDF, too many patches, invalid patch, event response, editor response, 501 when no editor). |
| `tests/AcroForm/` | `AcroFormFieldPatch` (fromArray/toArray, empty fieldId, empty fontFamily), `AcroFormOverrides`, `AcroFormEditorException`, `PythonProcessEnv` (build, PATH unset), `SessionAcroFormOverridesStorage` (get/set/remove). |
| `tests/DependencyInjection/` | `Configuration`, `PdfSignableExtension` (YAML and service wiring) |
| `tests/Event/` | `PdfProxyRequestEvent`, `PdfProxyResponseEvent`, `SignatureCoordinatesSubmittedEvent`, `BatchSignRequestedEvent`, `PdfSignRequestEvent`, `AcroFormApplyRequestEvent`, `AcroFormModifiedPdfProcessedEvent`, and event name constants (`PdfSignableEventsTest`). See [EVENTS](EVENTS.md). |
| `tests/EventListener/` | `AcroFormApplyScriptListener` (early return when hasResponse or script null/empty, error when script not found or path is directory, integration test with real script when available). |
| `tests/Form/` | `SignatureBoxType`, `SignatureCoordinatesType` (options, submit, validation, view vars, overlap/sort), `SignatureCoordinatesTypeExtension` |
| `tests/Model/` | `SignatureBoxModel`, `SignatureCoordinatesModel`, `AcroFormPageModel` (getters/setters, serialization, empty string), `AuditMetadata` (constants) |
| `tests/Twig/` | `NowoPdfSignableTwigExtension` (`nowo_pdf_signable_include_assets` once per request) |
| `assets/**/*.test.ts` | TypeScript unit tests (Vitest): `signable-editor/utils`, `signable-editor/constants`, `signable-editor/coordinates`, `signable-editor/box-drag` (getRotatedAabbSize, boxesOverlap), `acroform-editor/config`, `acroform-editor/strings`, `acroform-editor/acroform-move-resize`, `shared/constants`, `shared/url-and-scale`, `shared/pdfjs-loader` |
| `scripts/test/` | Python unit tests (pytest): AcroForm scripts (`extract_acroform_fields` — parse_font_size_from_da, CLI, stdin; `apply_acroform_patches` — patches empty/rect/font, CLI, dry-run, empty object in array; `process_modified_pdf` — copy input, document-key, output writable). Requires `pypdf` and `pytest`. |

Controller tests for `index()` use a minimal container (form factory with HttpFoundation extension, Twig, router, request stack) so that GET renders the form and POST validates and either redirects with flash or returns JSON when `Accept: application/json`. AcroForm controller tests use mocks for storage, event dispatcher, and optional editor; they do not require the demo app or a running server.

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

After a full run (239 PHP tests, 38 TypeScript tests):

- **Lines:** ~84%
- **Methods:** ~85%
- **Classes:** ~62% (15/24)

### Well covered (100% lines/methods)

- **Configuration**, **PdfSignableExtension**, **PdfProxyRequestEvent**, **PdfProxyResponseEvent**, **SignatureCoordinatesSubmittedEvent**, **BatchSignRequestedEvent**, **PdfSignRequestEvent**, **AcroFormApplyRequestEvent**, **AcroFormModifiedPdfProcessedEvent**, **SignatureCoordinatesTypeExtension**, **Models** (SignatureBoxModel, SignatureCoordinatesModel), **NowoPdfSignableBundle**, **NowoPdfSignableTwigExtension**.
- **SignatureController** — index (GET, POST redirect, POST JSON), proxy (disabled, invalid URL, allowlist, SSRF blocks, event response). The branch that performs a real HTTP fetch when the proxy event does not provide a response is not covered (would require integration/HTTP mock).
- **AcroFormOverridesController** — overrides GET/POST/DELETE, loadOverrides (storage null, invalid document_key, fields in body), extractFields (missing pdf), process (disabled, script not file 503, missing/invalid pdf_content 400), apply (disabled, validation, validation result from event, storage, event, editor, AcroFormEditorException, 501). The branches that run Python scripts or fetch PDF by URL are not covered (would require integration/HTTP mock).
- **Form types** — Submit, options, validation, view vars and helpers (e.g. `boxesOverlap`, `getAllUnits`) are covered.

### Partially covered

- **SignatureController** (~83% lines, 4/7 methods): Uncovered code is mainly the live HTTP client path in `proxyPdf()` and a few SSRF branches (e.g. unresolved hostname, ip2long false).
- **SignatureCoordinatesType** (~97% lines, 9/11 methods): Unique-name and overlap callbacks, sort_boxes PRE_SUBMIT, buildView, and boxFromArray are covered. Named config merge (url_field/unit_field/origin_field false overriding defaults) is covered by `testNamedConfigWithHiddenFieldsOverridesDefaults`.
- **SignatureBoxType** (~99% lines, 2/3 methods): One line in `configureOptions` (allowed_pages validator) may remain uncovered depending on PHPUnit execution order.
- **AuditMetadata**: Constants-only class (excluded from coverage in practice or low coverage); see `Nowo\PdfSignableBundle\Model\AuditMetadata`.

## Demo

The bundle includes Symfony 7 and 8 demo applications under `demo/symfony7` and `demo/symfony8`. They are not exercised by PHPUnit; run them manually (e.g. `composer install` in the demo dir and the Symfony web server) to try the signature form, proxy, and AcroForm demo. With `acroform.enabled: true` in the demo config, the overrides and apply endpoints are available for integration testing from the frontend or tools like curl.

## Adding tests

- Use PHPUnit 10 and the existing `tests/bootstrap.php`.
- For new form options or validation, extend the corresponding `tests/Form/*Test.php` and reuse `getExtensions()` / form factory setup where possible.
- For controller behaviour, extend `SignatureControllerTest` or `AcroFormOverridesControllerTest` and use their `createController()` / `createContainerForIndex()` (or storage/event mocks) to keep setup consistent.
