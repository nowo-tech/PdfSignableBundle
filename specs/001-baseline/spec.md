# Feature Specification: PdfSignableBundle baseline (100% code coverage)

**Feature Branch**: `001-baseline`  
**Created**: 2026-07-07  
**Status**: Active  
**Input**: Backfill GitHub Spec Kit baseline documenting 100% of production code in `src/`.

**Related docs**: [`docs/SPEC-DRIVEN-DEVELOPMENT.md`](../../docs/SPEC-DRIVEN-DEVELOPMENT.md), [`docs/CONFIGURATION.md`](../../docs/CONFIGURATION.md), [`docs/USAGE.md`](../../docs/USAGE.md)  
**Code inventory (traceability)**: [`code-inventory.md`](code-inventory.md)

---

## Summary

**Package**: `nowo-tech/pdf-signable-bundle`  
**Configuration root**: `nowo_pdf_signable`

Symfony bundle to **define signature box coordinates on PDFs**: form types with an in-browser PDF.js viewer, drag-and-drop placement, optional PDF proxy, AcroForm field editing, validation, audit metadata, and integration events for signing workflows. Symfony 7|8 · PHP 8.2+.

---

## User Scenarios & Testing

### User Story 1 — Place signature boxes on a PDF (Priority: P1)

As a legal integrator, I add `SignatureCoordinatesType` to a form so signers visually place and resize signature areas on a PDF in the browser.

**Independent Test**: Render the demo route or embed the form type with the bundle theme; load a PDF URL; click to add boxes, drag to move, resize corners; submit and receive normalized coordinates in the model.

**Acceptance Scenarios**:

1. **Given** a valid PDF URL and bundle form theme, **When** the page loads, **Then** PDF.js renders pages and the signable editor attaches overlays.
2. **Given** the viewer is active, **When** the user adds a box, **Then** `SignatureBoxModel` entries include page, position, size, optional rotation, and name.
3. **Given** form options `prevent_box_overlap`, `min_box_width`, or `allowed_pages`, **When** the user interacts, **Then** client-side constraints enforce documented limits before submit.

---

### User Story 2 — Proxy external PDFs safely (Priority: P1)

As an integrator, I enable the proxy so browsers load third-party PDFs without CORS while SSRF and allowlist rules protect the server.

**Independent Test**: `GET /pdf-signable/proxy?url=…` returns PDF bytes for allowlisted HTTPS URLs; blocked URLs return 4xx; `PdfProxyRequestEvent` / `PdfProxyResponseEvent` fire.

**Acceptance Scenarios**:

1. **Given** `proxy_enabled=true` and empty allowlist, **When** a valid public HTTPS URL is requested, **Then** `SignatureController::proxy` streams the PDF response.
2. **Given** a non-empty `proxy_url_allowlist`, **When** URL does not match substring or `#regex`, **Then** `ProxyUrlValidator` rejects the request.
3. **Given** `proxy_enabled=false`, **When** the proxy route is hit, **Then** the endpoint returns disabled/not-found behavior per configuration.

---

### User Story 3 — Named profiles and validation (Priority: P2)

As an integrator, I define YAML presets under `signature.profiles` and reference them via `config: 'alias'` on the form type for reusable PDF URLs, units, and box rules.

**Acceptance Scenarios**:

1. **Given** `signature.profiles.fixed_url`, **When** the form uses `['config' => 'fixed_url']`, **Then** resolved options merge global defaults with alias-specific overrides.
2. **Given** `unique_box_names` enabled, **When** duplicate names are submitted, **Then** Symfony validation fails with a translated message.
3. **Given** units `mm|cm|pt|px|in` and origin corners, **When** coordinates are submitted, **Then** values are stored in the selected unit/origin system.

---

### User Story 4 — Signing and audit hooks (Priority: P2)

As an integrator, I listen to bundle events to trigger PKI/PAdES signing, batch sign, or audit enrichment without the bundle performing crypto itself.

**Acceptance Scenarios**:

1. **Given** a successful form submit, **When** `SignatureCoordinatesSubmittedEvent` dispatches, **Then** listeners receive the model including boxes and optional `AuditMetadata`.
2. **Given** `audit.fill_from_request=true`, **When** submit occurs, **Then** IP, user agent, and timestamp merge into audit metadata before dispatch.
3. **Given** `batch_sign_enabled`, **When** the user clicks batch sign, **Then** `BatchSignRequestedEvent` and per-box `PdfSignRequestEvent` dispatch for custom handlers.

---

### User Story 5 — AcroForm field editing (Priority: P3)

As an admin integrator with AcroForm enabled, I edit existing PDF form fields in-browser and persist overrides via session or custom storage.

**Acceptance Scenarios**:

1. **Given** `acroform.enabled=true`, **When** the editor loads, **Then** `AcroFormOverridesController` and `AcroFormEditorType` expose override CRUD endpoints.
2. **Given** Python tooling configured, **When** apply is requested, **Then** `AcroFormApplyScriptListener` runs the editor script and dispatches `AcroFormModifiedPdfProcessedEvent`.
3. **Given** custom storage service id, **When** overrides save, **Then** `AcroFormOverridesStorageInterface` implementation receives patches.

---

### Edge Cases

- Invalid or unreachable PDF URL: viewer shows error state; proxy logs failure and returns 502 where applicable.
- Large PDFs / lazy load: `viewer_lazy_load` defers page rendering until visible.
- Touch devices: pinch zoom and two-finger pan via shared touch helpers.
- Missing optional dependencies (Python, extensions): `DependencyCheckListener` shows debug alert; CLI `nowo:pdf-signable:check-dependencies` reports status.
- AcroForm disabled: acroform routes and UI are not registered.

---

## Requirements

### Bundle & DI

- **FR-BUNDLE-001**: `NowoPdfSignableBundle` MUST register `ProxyUrlAllowlistValidationPass`, expose alias `nowo_pdf_signable`, and resolve bundle path for `@NowoPdfSignableBundle` templates.
- **FR-CFG-001**: `Configuration` MUST define `nowo_pdf_signable` with `proxy_enabled`, `proxy_url_allowlist`, `example_pdf_url`, `debug`, `signature` (defaults + `profiles`), `audit`, `tsa_url`, `signing_service_id`, and `acroform` trees.
- **FR-CFG-002**: `PdfSignableExtension` MUST load `services.yaml`, set `%nowo_pdf_signable.*%` parameters, and conditionally register acroform services when enabled.
- **FR-DI-001**: `services.yaml` and `routes.yaml` MUST wire controllers, form types, Twig extension, event listeners, and proxy route prefix documented in `docs/CONFIGURATION.md`.

### Security & proxy

- **FR-SEC-001**: `ProxyUrlValidator` and `ProxyUrlAllowlistValidationPass` MUST block private IPs, invalid schemes, and URLs outside the configured allowlist when non-empty.
- **FR-PROXY-001**: `SignatureController::proxy` MUST honor `proxy_enabled`, validate URL, dispatch proxy events, and stream PDF bytes with appropriate cache/content headers.

### Form types & models

- **FR-FORM-001**: `SignatureCoordinatesType` with `SignatureBoxType` and `SignatureCoordinatesTypeExtension` MUST support documented options: units, origin, URL modes, box limits, snap/grid, rotation, batch sign, named profiles, and validation constraints.
- **FR-MDL-001**: `SignatureCoordinatesModel` and `SignatureBoxModel` MUST represent PDF URL, unit/origin, and a collection of boxes with normalized scalar fields for persistence.
- **FR-MDL-002**: `AuditMetadata` MUST hold optional evidence fields (IP, user agent, timestamps, TSA token placeholders) filled per config/listeners.
- **FR-MDL-003**: `AcroFormPageModel` MUST represent per-page AcroForm field state for the editor.

### Controllers

- **FR-CTRL-001**: `SignatureController` MUST expose demo index (GET/POST) with `SignatureCoordinatesType`, optional JSON responses, flash messages, and coordinate submit event dispatch.

### Events

- **FR-EVT-001**: Signing lifecycle events (`PdfProxyRequestEvent`, `PdfProxyResponseEvent`, `SignatureCoordinatesSubmittedEvent`, `BatchSignRequestedEvent`, `PdfSignRequestEvent`) MUST use constants from `PdfSignableEvents`.
- **FR-EVT-002**: AcroForm events (`AcroFormApplyRequestEvent`, `AcroFormModifiedPdfProcessedEvent`) MUST fire around apply/processing hooks.

### AcroForm backend

- **FR-ACRO-001**: `AcroFormOverrides` and `AcroFormOverridesStorageInterface` with default `SessionAcroFormOverridesStorage` MUST persist field override patches keyed by document.
- **FR-ACRO-002**: `AcroFormFieldEdit` and `AcroFormFieldPatch` MUST model editable field geometry and properties.
- **FR-ACRO-003**: `PdfAcroFormEditorInterface`, `PythonProcessEnv`, and `AcroFormApplyScriptListener` MUST invoke external Python tooling and surface `AcroFormEditorException` on failure.
- **FR-ACRO-004**: `AcroFormOverridesController`, `AcroFormEditorType`, and `AcroFormFieldEditType` MUST expose HTTP/form APIs for override management.

### CLI & dependencies

- **FR-CLI-001**: `nowo:pdf-signable:check-dependencies` and `DependencyChecker` MUST report optional runtime dependencies; `DependencyCheckListener` MAY show a dev-only alert.

### Twig

- **FR-TWIG-001**: `NowoPdfSignableTwigExtension` MUST expose documented Twig functions/filters for asset URLs and viewer config.
- **FR-TWIG-002**: Form theme templates MUST render PDF viewer partial, box widgets, and Encore/vite asset tags for signable JS/CSS.
- **FR-TWIG-003**: Demo templates MUST render the index page and optional dependency debug alert.
- **FR-TWIG-004**: AcroForm templates MUST render editor root and edit modal partials.

### Frontend — signable editor

- **FR-UI-SIGN-001**: Entry modules (`signable-editor.ts`, `index.ts`) MUST bootstrap PDF.js via shared loader and attach to form DOM roots.
- **FR-UI-SIGN-002**: `box-drag.ts` and `box-overlays.ts` MUST implement add/move/resize with DOM overlays synced to hidden form fields.
- **FR-UI-SIGN-003**: `coordinates.ts` MUST convert between viewer pixels and form unit/origin.
- **FR-UI-SIGN-004**: `grid.ts` MUST implement snap-to-grid when enabled.
- **FR-UI-SIGN-005**: `font-auto-size.ts` MUST shrink text to fit box bounds when configured.
- **FR-UI-SIGN-006**: `signature-pad.ts` MUST capture drawn signatures into box payloads when enabled.
- **FR-UI-SIGN-007**: `thumbnails.ts` MUST render page strip navigation.
- **FR-UI-SIGN-008**: `touch.ts` MUST support touch drag and gesture handoff on tablets.

### Frontend — AcroForm editor

- **FR-UI-ACRO-001**: AcroForm entry/bootstrap/config MUST initialize field overlays on AcroForm-enabled forms.
- **FR-UI-ACRO-002**: `strings.ts` MUST supply translatable UI strings for the AcroForm modal.
- **FR-UI-ACRO-003**: `acroform-move-resize.ts` MUST move/resize AcroForm fields and emit patches to the backend.

### Frontend — shared & build

- **FR-UI-SHR-001**: `shared/index.ts`, `constants.ts`, `types.ts` MUST export shared viewer types and constants.
- **FR-UI-SHR-002**: `pdfjs-loader.ts` and `pdf.worker.min.js` MUST load PDF.js worker safely.
- **FR-UI-SHR-003**: `url-and-scale.ts` MUST resolve proxy URLs and viewport scale factors.
- **FR-UI-SHR-004**: `zoom-toolbar.ts` MUST provide zoom in/out/fit controls with i18n hooks.
- **FR-UI-SHR-005**: `shared/touch.ts` MUST implement pinch zoom and two-finger pan.
- **FR-UI-SHR-006**: `logger.ts` / `pdfSignableLogger.ts` MUST gate console logging on `debug` config.
- **FR-UI-SHR-007**: `pdf-signable.scss` MUST style viewer, boxes, grid guides, and toolbars.
- **FR-BUILD-001**: Maintainers MUST rebuild `Resources/public/js/pdf-signable.js`, `pdf-signable.css`, and `acroform-editor.js` when TypeScript/SCSS sources change (Vitest covers co-located `*.test.ts`).

### Internationalization

- **FR-I18N-001**: Translation catalogs under `Resources/translations/nowo_pdf_signable.*.yaml` MUST cover form labels, validation messages, and viewer strings referenced from PHP/JS.

---

## Key Entities

- **SignatureCoordinatesModel**: PDF URL, unit, origin, collection of boxes, optional audit metadata.
- **SignatureBoxModel**: Name, page, x/y/width/height, rotation, optional default value/signature image payload.
- **AuditMetadata**: Request-derived and listener-enriched evidence fields for signing workflows.
- **AcroFormOverrides**: Document key → list of field patches stored via pluggable storage.

---

## Success Criteria

- **SC-001**: 100% of production files in `src/` appear in [`code-inventory.md`](code-inventory.md) with requirement IDs (**93/93** mapped; Vitest `*.test.ts` excluded).
- **SC-002**: Configuration keys in `docs/CONFIGURATION.md` match `Configuration.php`.
- **SC-003**: PHPUnit, PHPStan, and Vitest pass in CI (`composer qa`).
- **SC-004**: Proxy rejects disallowed URLs; allowlist regex entries validated at compile time.
- **SC-005**: Submit flow dispatches `SignatureCoordinatesSubmittedEvent` with model data matching form POST.

---

## Assumptions

- Integrators register bundle routes and form theme in the application.
- PDF.js and built JS assets are published to `public/bundles/nowopdfsignable/` via Symfony asset commands documented in INSTALLATION.
- PKI/PAdES signing, TSA calls, and HSM integration are implemented in application listeners using bundle events and config placeholders (`tsa_url`, `signing_service_id`).
- Python AcroForm tooling is optional and host-provided when AcroForm apply is used.
- Demos under `demo/` illustrate integration but are not Packagist API.

---

## Explicit non-goals

- Performing cryptographic PDF signing inside the bundle.
- Guaranteeing PDF/A compliance or long-term signature validity.
- Server-side rendering of PDF pages (viewer is client-side PDF.js only).

---

## Validation

| Check | Command |
| --- | --- |
| Full QA | `composer qa` or `make release-check` |
| PHP tests | `vendor/bin/phpunit` |
| Static analysis | `vendor/bin/phpstan analyse` |
| TS tests | `pnpm test` (Vitest, co-located under `Resources/assets/`) |
| Code inventory | `find src -type f ! -name '*.test.ts' ! -name README.md \| wc -l` must match inventory total |

When changing behavior, update this spec, [`code-inventory.md`](code-inventory.md), tests, and integrator docs (`USAGE.md`, `CONFIGURATION.md`, `CHANGELOG.md`).
