# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- (None yet.)

### Changed

- (None yet.)

### Fixed

- (None yet.)

---

## [2.0.1] - 2026-02-16

### Added

- **Tests:** More coverage for PDF.js loader (`getWorkerUrl` absolute URL conversion, protocol-relative and empty-string handling, `querySelector` fallback when `currentScript` is null; `getPdfJsLib` CDN path). PHP test for default `pdfjs_worker_url` null in `SignatureCoordinatesType`. Extra `url-and-scale` tests (empty proxy, `getScaleForFitPage` with null container).

### Changed

- **PDF.js worker:** Default worker asset is now **`pdf.worker.min.js`** (was `pdf.worker.min.mjs`) so typical servers serve it with `Content-Type: application/javascript`, avoiding “Failed to fetch dynamically imported module” and “Setting up fake worker” in development. Theme and loader default to `bundles/nowopdfsignable/js/pdf.worker.min.js`. Vite build copies the worker from `node_modules/pdfjs-dist/build` to the output dir; `copy-worker` script and `postinstall` still output the same file for installs without a full build.
- **Worker URL resolution:** Relative worker URLs are converted to absolute (using `window.location.origin`) so the worker loads correctly in SPAs and when the script is served from a different base. Fallback: when `document.currentScript` has no `src` (e.g. lazy-loaded script), the loader looks for a script tag with `src` containing `pdf-signable.js` or `acroform-editor.js` and derives the worker path from it.

### Fixed

- **Translations:** Added missing AcroForm editor modal keys to 10 locales (ca, cs, de, fr, it, nl, pl, pt, ru, tr): `acroform_editor.modal_create_if_missing`, `modal_field_name`, `modal_field_name_placeholder`, `modal_hidden`, `modal_max_len`, `modal_max_len_placeholder`. Ensures `make validate-translations` passes.
- **Turkish (tr) YAML:** Fixed invalid escape in single-quoted strings (`PDF\'deki` → `"PDF'deki alan adı"`) so the file parses correctly.

### Documentation

- [USAGE](USAGE.md): Default worker asset and MIME-type note for “Setting up fake worker” / “Failed to fetch dynamically imported module”.

For upgrade steps from 2.0.0, see [UPGRADING](UPGRADING.md).

---

## [2.0.0] - 2026-02-16

### Breaking

- **Configuration structure:** Signature config must be under the `signature` node (global box options + `configs` by alias). AcroForm config must be under a single `acroform` node (replacing `acroform_editor` and `acroform_configs`). Container parameters are renamed: `nowo_pdf_signable.default_box_*`, `.configs` → `nowo_pdf_signable.signature.*`; `nowo_pdf_signable.acroform_editor.*`, `nowo_pdf_signable.acroform_configs` → `nowo_pdf_signable.acroform.*`, `nowo_pdf_signable.acroform.configs`. See [UPGRADING](UPGRADING.md) for migration steps and examples.

### Added

- **Signature config under `signature` node:** Global box defaults and named configs by alias (default alias `default`). Use form option `config: 'alias'` to apply. Container parameters: `nowo_pdf_signable.signature.*` and `nowo_pdf_signable.signature.configs`. See [UPGRADING](UPGRADING.md).
- **AcroForm config under single `acroform` node:** Replaces `acroform_editor` and `acroform_configs`. Platform options, editor defaults, `default_config_alias`, and `configs` by alias. Container parameters: `nowo_pdf_signable.acroform.*` and `nowo_pdf_signable.acroform.configs`. See [UPGRADING](UPGRADING.md).
- **AcroForm editor translations**: All UI strings in the AcroForm editor panel (demo templates) are now translatable. New translation keys under `acroform_editor.*` (domain `nowo_pdf_signable`): page title, config header, form errors intro, close aria, panel title, draft intro (with HTML), document key label/placeholder, fields label, fields from PDF, refresh button, draft label/placeholder/title, load/save/clear buttons. Translations added for all 12 locales (EN, ES, FR, DE, CA, IT, NL, PT, CS, PL, RU, TR). Demo templates `acroform_editor.html.twig` (Symfony 7 and 8) use `|trans({}, 'nowo_pdf_signable')` for every user-facing string.
- **AcroForm editor config (Type)**: New options under the **`acroform`** node to control the edit-field modal from the bundle config:
  - `label_mode` (`'input'` \| `'choice'`): label as free text or select with predefined options plus "Other".
  - `label_choices` (string[]): list of label options when `label_mode` is `'choice'` (each entry: string or `"value|Label"`).
  - `label_other_text` (string): text for the "Other" option in the label select (default `'Other'`).
  - `show_field_rect` (bool, default `true`): when `false`, the coordinates (rect) input is hidden in the edit-field modal.
  - `font_sizes` (int[]): allowed font sizes in pt; empty = number input (1–72); non-empty = select with these values.
  - `font_families` (string[]): allowed font families; empty = built-in list; non-empty = select with these options (each entry: string or `"value|Label"`).
  See [CONFIGURATION](CONFIGURATION.md) and [ACROFORM](ACROFORM.md).
- **Demo — AcroForm section**: New dedicated "AcroForm" section in the demo nav and home index (Symfony 7 and 8) with six demos: AcroForm editor (default), AcroForm editor — Label as dropdown, AcroForm editor — Coordinates hidden, AcroForm editor — Custom font options, AcroForm editor — All options, AcroForm editor (min field size 24 pt).
- **AcroForm apply — font family in PDF**: The edit-field modal allows editing font size and font family for text/textarea fields. These values are now sent in the patch payload when applying to the PDF; `AcroFormFieldPatch` includes `fontFamily`; the Python apply script (`apply_acroform_patches.py`) sets the default appearance (/DA) with both font size and family, mapping common names (Arial, Helvetica, Times New Roman, Courier New, sans-serif, serif, monospace) to standard PDF fonts. See [ACROFORM_BACKEND_EXTENSION](ACROFORM_BACKEND_EXTENSION.md) and [ACROFORM](ACROFORM.md).

### Changed

- (None.)

### Fixed

- (None.)

### Documentation

- **Unified AcroForm docs:** Single [ACROFORM](ACROFORM.md) guide; [ACROFORM_FLOWS](ACROFORM_FLOWS.md) for flow diagrams; [ACROFORM_BACKEND_EXTENSION](ACROFORM_BACKEND_EXTENSION.md) for backend. Removed redundant ACROFORM_EDITOR.md and ACROFORM_EDITOR_UX.md.
- **Documentation in English:** [README](README.md), [ACROFORM](ACROFORM.md), [ACROFORM_FLOWS](ACROFORM_FLOWS.md), and [EVENTS](EVENTS.md) frontend section translated to English; [ROADMAP](ROADMAP.md) updated with AcroForm section and doc links.
- **Code comments in English:** Spanish comments in `assets/acroform-editor.ts`, `assets/pdf-signable.scss`, and `scripts/PoC/run_poc.py` translated to English.

For upgrade steps from 1.5.x, see [UPGRADING](UPGRADING.md).

---

## [1.5.4] - 2026-02-11

### Added

- **Show AcroForm option** (`show_acroform`): Form option (default `true`) to draw an outline overlay over **AcroForm/PDF form fields** so they are visible in the viewer. Uses PDF.js `getAnnotations()`; outlines are non-interactive (clicks still add signature boxes). Set `show_acroform: false` to hide them. See [USAGE](docs/USAGE.md) and [STYLES](docs/STYLES.md) (`.pdf-annotation-layer`, `.acroform-field-outline`).
- **Demo**: Recipe and demo named configs (`default`, `fixed_url`) and demo base options include `show_acroform: true` by default.

### Changed

- **Default configs**: Recipe example and demo configs (Symfony 7 and 8) set `show_acroform: true` in named configs and in the demo form base options so AcroForm fields are visible by default everywhere.

### Developer

- CHANGELOG, UPGRADING, RELEASE checklist updated for 1.5.4.

For upgrade steps from 1.5.3, see [UPGRADING](docs/UPGRADING.md).

---

## [1.5.3] - 2026-02-10

### Added

- **Extended debug logging**: When `nowo_pdf_signable.debug` is `true`, the viewer script now logs more detail to the browser console to help detect template/override issues: DOM resolution (widget, boxes list, prototype, key elements), overlay updates (box item count, missing page/coordinate fields, missing overlay container per page), add box (empty prototype, missing root or page/coordinate inputs), remove box (index and remaining count; warning if clicked item is not a direct box-item), signature pads init (canvas count; warnings when box item or signature-data input is missing), and PDF DOM built (pages and overlay containers). See [CONFIGURATION](CONFIGURATION.md) for the `debug` option.

### Fixed

- **Box item fallback**: The script finds box rows by `[data-pdf-signable="box-item"]` with fallback to `.signature-box-item`, so overridden themes that only use the class (e.g. `.signature-box-item`) without the attribute still work and overlays are drawn correctly. [USAGE](USAGE.md) notes the fallback.

### Developer

- CHANGELOG, UPGRADING, and RELEASE checklist updated for 1.5.3.

For upgrade steps from 1.5.2, see [UPGRADING](docs/UPGRADING.md).

---

## [1.5.2] - 2026-02-10

### Added

- **Workflow documentation**: [WORKFLOW.md](docs/WORKFLOW.md) with Mermaid diagrams for architecture, page load and init, load PDF, add signature box, drag/resize overlay, coordinate sync, and form submit. Linked from [USAGE](docs/USAGE.md) and [README](README.md).
- **Data attributes for overrides**: The viewer script finds elements by `data-pdf-signable` attributes (e.g. `data-pdf-signable="widget"`, `"page"`, `"x"`) instead of CSS classes, so you can change or remove classes in overridden templates for styling without breaking the JS. Form types and theme add these attributes; [USAGE](docs/USAGE.md) documents the full table and override notes.
- **Recipe**: `.symfony/recipe` config file is a complete example with all options commented and explained in English.

### Changed

- **Override form theme**: [USAGE](docs/USAGE.md) now explains that if the overridden form theme is not applied (form fields still from bundle), add the theme explicitly in `config/packages/twig.yaml` under `form_themes` so Twig uses your overridden template.

### Fixed

- **Widget init**: Script finds the widget by `[data-pdf-signable="widget"]` with fallback to `.nowo-pdf-signable-widget`, so overridden themes that do not add the attribute still work.
- **Page field on add box**: When the page field is an input and the template override omits the expected class, the script now finds it by `[data-pdf-signable="page"]` or by input/select whose `name` ends with `[page]`, so the page number is set correctly when clicking on the PDF.
- **Mermaid diagrams**: [WORKFLOW.md](docs/WORKFLOW.md) sequence diagram labels simplified (no commas or special characters in arrow text) so Mermaid parsers accept them.

### Developer

- CHANGELOG, UPGRADING, and RELEASE checklist updated for 1.5.2.

For upgrade steps from 1.5.1, see [UPGRADING](docs/UPGRADING.md).

---

## [1.5.1] - 2026-02-11

### Fixed

- **Named config merge order**: When using a named config (e.g. `config: 'fixed_url'`), the named config now **overrides** the form type’s resolver defaults. Previously the merge order was reversed, so options like `url_field: false`, `show_load_pdf_button: false`, `unit_field: false`, and `origin_field: false` from the YAML config were overwritten by the defaults and had no effect. If you use a named config with these options set to `false`, they now apply correctly (URL row, Load PDF button, and unit/origin selectors are hidden).
- **Form theme visibility**: The Twig form theme now evaluates `url_field`, `show_load_pdf_button`, `unit_field`, and `origin_field` so that when they are `false` (or the string `'false'`), the corresponding UI is hidden. View options are passed as strict booleans from `buildView` for consistent behaviour.

### Changed

- **Demo**: The Symfony 7 and 8 demos’ Makefile forces `vendor/nowo-tech/pdf-signable-bundle` to be a **symlink** to the mounted repo (`/var/pdf-signable-bundle`) after `composer install`, so the container always uses the live bundle code. Path repository in the demos’ `composer.json` has `"options": {"symlink": true}`.

### Developer

- **Tests**: New test `SignatureCoordinatesTypeTest::testNamedConfigWithHiddenFieldsOverridesDefaults` ensures that a named config with `url_field`, `show_load_pdf_button`, `unit_field`, and `origin_field` set to `false` overrides resolver defaults in both form building and view options. Preset `fixed_url` added to the test form factory. [TESTING](TESTING.md) updated (121 tests, coverage note for named config merge).

For upgrade steps from 1.5.0, see [UPGRADING](UPGRADING.md).

---

## [1.5.0] - 2026-02-10

### Added

- **Guides and grid**: Form options **`show_grid`** (default `false`) and **`grid_step`** (e.g. `5` in form unit). When `show_grid` is true, a grid overlay is drawn on each page in the viewer (canvas above PDF, below signature overlays) to help align boxes; coordinates are unchanged. See [USAGE](USAGE.md) and [STYLES](STYLES.md) (`.pdf-grid-overlay`).
- **Viewer lazy load**: Form option **`viewer_lazy_load`** (default `false`). When `true`, PDF.js and the signable script are not loaded at page bottom; a small inline script uses **IntersectionObserver** and loads them when the widget enters the viewport. Useful for long pages with multiple widgets. See [USAGE](USAGE.md).
- **Advanced signing (structure and extension points)**: Bundle now provides structures and events for PKI/PAdES, timestamp, audit trail, and batch signing without adding third-party dependencies. Your app adds keys, TSA URL, and signing service. See [SIGNING_ADVANCED](SIGNING_ADVANCED.md).
  - **Audit**: Config `audit.fill_from_request` (default `true`) merges `submitted_at`, `ip`, `user_agent` into the model before dispatch. **`AuditMetadata`** class with recommended keys (`tsa_token`, `user_id`, etc.). Config placeholders **`tsa_url`** and **`signing_service_id`** (bundle does not call them; use in listeners).
  - **Events**: **`BATCH_SIGN_REQUESTED`** (when form is submitted with “Sign all”, `batch_sign=1`); **`PDF_SIGN_REQUEST`** (when your code requests a digital signature; listener can set response). See [EVENTS](EVENTS.md).
  - **Batch signing**: Form option **`batch_sign_enabled`** shows a “Sign all” button; your listener subscribes to `BATCH_SIGN_REQUESTED` to perform the actual signing.
- **Single inclusion of CSS and JS per request**: When multiple `SignatureCoordinatesType` widgets are rendered on the same page, the form theme includes the PDF viewer CSS (`pdf-signable.css`), PDF.js, and `pdf-signable.js` only once. A new Twig extension (`NowoPdfSignableTwigExtension`) exposes `nowo_pdf_signable_include_assets()` used by the theme. See [USAGE](USAGE.md) and [STYLES](STYLES.md).
- **Demo**: "Guides and grid" and "Viewer lazy load" demo pages (19 demos in total); sidebar and offcanvas highlight the current route; home index lists all demos including the new two.

### Changed

- **Larger resize and rotate handles**: Corner resize handles on signature box overlays are now 12×12 px (was 8×8 px); the rotation handle is 16×16 px (was 12×12 px) for easier grabbing. See [STYLES](STYLES.md).

### Fixed

- **Rotated signature boxes**: Drag constraints now use the axis-aligned bounding box of the rotated rectangle, so boxes can be moved flush to all page edges (left, right, top, bottom) at any rotation angle (e.g. -90°, 45°). Previously, rotated boxes could not be placed against the left (and sometimes top) edge.

### Developer

- **Tests**: `NowoPdfSignableTwigExtensionTest` covers `nowo_pdf_signable_include_assets()` (true once per request, then false; no request → always true). `BatchSignRequestedEventTest` and `PdfSignRequestEventTest` added; `PdfSignableEventsTest` extended with `BATCH_SIGN_REQUESTED` and `PDF_SIGN_REQUEST` constants.
- **Documentation**: [CONTRIBUTING](CONTRIBUTING.md) documents form theme assets and the Twig function for overriders. [TESTING](TESTING.md) updated with current coverage summary (120 tests) and exclusion of `PdfSignableEvents.php`.
- **Coverage**: `PdfSignableEvents.php` excluded from coverage (constants only); phpunit.xml.dist and TESTING.md updated.

For upgrade steps from 1.4.x, see [UPGRADING](UPGRADING.md).

---

## [1.4.1] - 2026-02-09

### Fixed

- **Translations**: Added `signing.consent_label` and `signing.consent_required` to all locales (CA, CS, DE, FR, IT, NL, PL, PT, RU, TR). Fixed YAML escaping in Catalan, French and Italian (single quotes in single-quoted strings).
- **Tests**: `SignatureCoordinatesTypeTest::testEnableSignatureCaptureAndDisclaimerPassedToView` no longer submits the same form twice; uses a separate form instance for the null-consent assertion to avoid `AlreadySubmittedException`.

---

## [1.4.0] - 2026-02-09

### Added

- **Signing in boxes**: Draw or upload a signature image per box. Options `enable_signature_capture` (draw pad) and `enable_signature_upload` (file input); image stored in `SignatureBoxModel::signatureData` and shown in the PDF overlay. See [USAGE](USAGE.md#signing-in-boxes-draw-or-image).
- **Legal disclaimer**: `signing_legal_disclaimer` and `signing_legal_disclaimer_url` form options to show a notice above the viewer (e.g. “simple signature, not qualified”). See [USAGE](USAGE.md#legal-disclaimer).
- **Consent checkbox**: `signing_require_consent` (default `false`) and `signing_consent_label`. When `true`, a required checkbox is shown; value in `SignatureCoordinatesModel::getSigningConsent()`. Translations: `signing.consent_label`, `signing.consent_required` (EN, ES).
- **Timestamp per box**: `SignatureBoxModel::getSignedAt()` / `setSignedAt()` (ISO 8601). Set by the frontend when the user draws or uploads; backend can overwrite with server time for stronger evidence. Exported in `toArray()` / `fromArray()` as `signed_at`.
- **Audit metadata**: `SignatureCoordinatesModel::getAuditMetadata()` / `setAuditMetadata()` (e.g. `signed_at`, `ip`, `user_agent`) for evidence; included in `toArray()`. See [USAGE](USAGE.md#making-the-signature-more-legally-robust).
- **Signing-only mode** (`signing_only`): When `true`, each box row shows only the **box name** (read-only) and the **signature capture** (draw/upload); coordinate fields and unit/origin are hidden (values still submitted). Use for predefined boxes where the user only signs. See [USAGE](USAGE.md).
- **Signature pad improvements**: Canvas resized to display size with `devicePixelRatio` for sharp rendering; smooth strokes via `quadraticCurveTo`; **pressure-sensitive line width** (1–6 px) on touch (`Touch.force`) and mouse (`MouseEvent.pressure`) when supported. Signature capture row uses full form width.
- **Demo**: “Signing options (AutoFirma, legal)” info page with links to AutoFirma, bundle roadmap (eIDAS/qualified), and USAGE; all demo texts in English.
- **Demo layout**: Burger menu replaced by a **sidebar (aside)** with all demo links (By configuration, Define areas, Signing, Bundle route); on small screens a “Demos” button opens an offcanvas with the same nav.

### Changed

- **Demo**: All demo UI copy is in English. Sidebar and offcanvas list all demo pages; predefined sign-only demo uses `signing_only: true` (name + signature only, no coordinate fields).

### Fixed

- **TypeScript**: `MouseEvent.pressure` access fixed for strict DOM types (cast to `MouseEvent & { pressure?: number }`).

### Developer

- **Tests**: `SignatureCoordinatesTypeTest` asserts `signing_only` default and when true; consent checkbox submit uses `'1'`/`null`. `SignatureBoxModelTest` and `SignatureCoordinatesModelTest` cover `signedAt`, `signingConsent`, `auditMetadata` and toArray/fromArray.

For upgrade steps from 1.3.x, see [UPGRADING](UPGRADING.md).

---

## [1.3.0] - 2026-02-09

### Added

- **PDF viewer zoom**: Toolbar with **zoom out** (−), **zoom in** (+) and **fit width** (translated). PDF loads by default at fit-to-width; zoom range 0.5×–3×. Toolbar appears in the top-right of the viewer when a PDF is loaded. See [USAGE](USAGE.md).
- **Debug option** (`nowo_pdf_signable.debug`): When `true`, the frontend emits `console.log` / `console.warn` in the browser (e.g. PDF load, add/remove box). Default `false`. See [CONFIGURATION](CONFIGURATION.md).
- **Translations**: Zoom toolbar labels (`js.zoom_in`, `js.zoom_out`, `js.zoom_fit`) in all supported languages (EN, ES, FR, DE, IT, PT, TR, CA, CS, NL, PL, RU).

### Changed

- **Viewer**: Initial PDF scale is always fit-to-width (unchanged behaviour; ResizeObserver also uses fit-to-width on resize).

### Fixed

- (None in this release.)

### Developer

- **Tests**: `ConfigurationTest` now covers `debug` default and override.

For upgrade steps from 1.2.x, see [UPGRADING](UPGRADING.md).

---

## [1.2.0] - 2026-02-10

### Added

- **Optional rotation** (`enable_rotation`): form option (default `false`). When `true`, each box has an **angle** field (degrees) and the viewer shows a **rotate handle** above each overlay; when `false`, the angle field is not rendered and boxes are not rotatable. See [USAGE](USAGE.md).
- **Default values per box name** (`box_defaults_by_name`): form option to pre-fill width, height, x, y, angle when the user selects a name (dropdown or input). See [USAGE](USAGE.md) and [ROADMAP](ROADMAP.md).
- **Demos**: added rotation, defaults-by-name, and allow-overlap demo pages (16 demo pages in total for Symfony 7 and 8).

### Fixed

- **CI**: translation validation step now runs after `composer install` so `vendor/autoload.php` exists (fixes failure in GitHub Actions).

For upgrade steps from 1.1.x, see [UPGRADING](UPGRADING.md).

---

## [1.1.0] - 2026-02-10

### Added

- **Page restriction** (`allowed_pages`): form option to limit which pages boxes can be placed on; page field becomes a dropdown. See [USAGE](USAGE.md).
- **Proxy URL allowlist** (`proxy_url_allowlist`): bundle config to restrict which URLs the proxy can fetch (substring or regex). See [CONFIGURATION](CONFIGURATION.md) and [SECURITY](SECURITY.md).
- **Box order** (`sort_boxes`): form option to sort boxes by page, then Y, then X on submit.
- **Non-overlapping boxes** (`prevent_box_overlap`): default is now `true`; validation on submit and **frontend enforcement** (drag/resize that would overlap is reverted and a translated message is shown). Set to `false` to allow overlapping boxes.
- **Translations**: added CA, CS, NL, PL, RU (12 languages total: EN, ES, FR, DE, IT, PT, TR, CA, CS, NL, PL, RU).
- **Translation validation**: `scripts/validate-translations-yaml.php` now checks that all translation files have the same keys as the reference (English) file; `composer validate-translations` runs it.
- **Demos**: added page restriction, sorted boxes, no-overlap, and latest features (combined) demos (13 demo pages in total for Symfony 7 and 8).

### Changed

- **SECURITY**: Proxy no longer leaks exception messages to the client on 502/errors; SSRF mitigation blocks private/local URLs before fetch. Success flash in demos shows coordinates as an HTML list (user names escaped); see [SECURITY](SECURITY.md).
- **`prevent_box_overlap`** default changed from `false` to `true`. If you relied on overlapping boxes being allowed by default, set `'prevent_box_overlap' => false` when adding the form type. See [UPGRADING](UPGRADING.md).

### Compatibility

- PHP 8.1+
- Symfony 6.1+, 7.x, 8.x.

---

## [1.0.0] - 2026-02-09

First stable release.

### Added

- **Form types**
  - `SignatureCoordinatesType`: form field that renders a PDF viewer and lets users define signature boxes by click; submits `SignatureCoordinatesModel` (pdfUrl, unit, origin, signatureBoxes).
  - `SignatureBoxType`: child type for a single signature box; submits `SignatureBoxModel` (name, page, x, y, width, height). Name as text or dropdown (`name_mode: choice`); first choice pre-selected when empty.
- **Models**
  - `SignatureCoordinatesModel`: pdfUrl, unit (mm, cm, pt, px, in), origin (corners), signatureBoxes collection.
  - `SignatureBoxModel`: name, page, x, y, width, height.
- **PDF viewer**
  - Browser-based viewer using PDF.js with overlays for each signature box. Click on the PDF to add a box; drag to move; drag corners to resize.
  - Overlay color by box name (deterministic); disambiguator label when same name on multiple boxes (e.g. `signer_1 (1)`, `signer_1 (2)`).
  - ResizeObserver loop fix: `scrollbar-gutter: stable` and `isReRendering` flag to prevent layout oscillation.
- **Configuration**
  - `nowo_pdf_signable`: `proxy_enabled` for external PDFs (avoids CORS), `example_pdf_url` for form preload, optional `configs` (named presets).
- **Named configurations**
  - Define preset options in `nowo_pdf_signable.signature.configs` and reference with form option `config: 'name'`. See [CONFIGURATION.md](CONFIGURATION.md) and [USAGE.md](USAGE.md).
- **Optional proxy**
  - Route and controller to proxy external PDFs (`/pdf-signable/proxy`). Events: `PdfProxyRequestEvent`, `PdfProxyResponseEvent`. See [EVENTS.md](EVENTS.md).
- **Form theme**
  - Twig form theme for the signature coordinates widget (full widget: PDF + boxes). Reusable `SignatureBoxType` layout.
- **Frontend assets**
  - Vite + TypeScript entry (`assets/pdf-signable.ts`). Built file at `Resources/public/js/pdf-signable.js`. Form submits as normal POST; JS re-indexes collection before submit.
- **Validation**
  - Required box name (`NotBlank` on `SignatureBoxType`). `unique_box_names`: `true` (all unique), `false` (no check), or array (e.g. `['signer_1', 'witness']`) for per-name uniqueness. See [USAGE.md](USAGE.md).
- **Events**
  - `SignatureCoordinatesSubmittedEvent` (after valid form submit), `PdfProxyRequestEvent` (before proxy fetch), `PdfProxyResponseEvent` (after proxy fetch). See [EVENTS.md](EVENTS.md).
- **Translation**
  - EN, ES, FR, DE, IT, PT, TR (e.g. `signature_box_type.name.required`, `signature_boxes.unique_names_message`, `js.alert_submit_error`). Script `scripts/validate-translations-yaml.php` for CI.
- **Demos**
  - Dockerized demos for Symfony 7 and 8 (Bootstrap, Vite, TypeScript). Twelve demo pages: no config, default, fixed_url, overridden, URL as dropdown, limited boxes, same signer multiple, unique per name, page restriction, sorted boxes, no-overlap, predefined boxes. Home and burger menu list all; each page shows configuration in bullets. Flash message with coordinates (plain text).
- **Documentation**
  - README, [INSTALLATION.md](INSTALLATION.md), [CONFIGURATION.md](CONFIGURATION.md), [USAGE.md](USAGE.md), [EVENTS.md](EVENTS.md), [UPGRADING.md](UPGRADING.md), [ROADMAP.md](ROADMAP.md), [CONTRIBUTING.md](CONTRIBUTING.md), [SECURITY.md](SECURITY.md) (all in `docs/`, English). “Same signer, multiple locations” and backend grouping example in USAGE.

### Changed

- **NotBlank**: Uses named argument `message:` (array form no longer supported in recent Symfony Validator).
- **Makefile**: `make install`, `make assets`, `make validate-translations` run via Docker for consistency.

### Compatibility

- PHP 8.1+
- Symfony 6.1+, 7.x, 8.x.

---

[Unreleased]: https://github.com/nowo-tech/pdfSignableBundle/compare/v2.0.1...HEAD
[2.0.1]: https://github.com/nowo-tech/pdfSignableBundle/releases/tag/v2.0.1
[2.0.0]: https://github.com/nowo-tech/pdfSignableBundle/releases/tag/v2.0.0
[1.5.4]: https://github.com/nowo-tech/pdfSignableBundle/releases/tag/v1.5.4
[1.5.3]: https://github.com/nowo-tech/pdfSignableBundle/releases/tag/v1.5.3
[1.5.2]: https://github.com/nowo-tech/pdfSignableBundle/releases/tag/v1.5.2
[1.5.1]: https://github.com/nowo-tech/pdfSignableBundle/releases/tag/v1.5.1
[1.5.0]: https://github.com/nowo-tech/pdfSignableBundle/releases/tag/v1.5.0
[1.4.1]: https://github.com/nowo-tech/pdfSignableBundle/releases/tag/v1.4.1
[1.4.0]: https://github.com/nowo-tech/pdfSignableBundle/releases/tag/v1.4.0
[1.3.0]: https://github.com/nowo-tech/pdfSignableBundle/releases/tag/v1.3.0
[1.2.0]: https://github.com/nowo-tech/pdfSignableBundle/releases/tag/v1.2.0
[1.1.0]: https://github.com/nowo-tech/pdfSignableBundle/releases/tag/v1.1.0
[1.0.0]: https://github.com/nowo-tech/pdfSignableBundle/releases/tag/v1.0.0
