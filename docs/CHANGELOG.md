# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

- **Tests**: `NowoPdfSignableTwigExtensionTest` covers `nowo_pdf_signable_include_assets()` (true once per request, then false; no request → always true).
- **Documentation**: [CONTRIBUTING](CONTRIBUTING.md) documents form theme assets and the Twig function for overriders. [TESTING](TESTING.md) updated with current coverage summary and exclusion of `PdfSignableEvents.php`.
- **Coverage**: `PdfSignableEvents.php` excluded from coverage (constants only); phpunit.xml.dist and TESTING.md updated.

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
  - Define preset options in `nowo_pdf_signable.configs` and reference with form option `config: 'name'`. See [CONFIGURATION.md](CONFIGURATION.md) and [USAGE.md](USAGE.md).
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

[Unreleased]: https://github.com/nowo-tech/pdfSignableBundle/compare/v1.4.1...HEAD
[1.4.1]: https://github.com/nowo-tech/pdfSignableBundle/releases/tag/v1.4.1
[1.4.0]: https://github.com/nowo-tech/pdfSignableBundle/releases/tag/v1.4.0
[1.3.0]: https://github.com/nowo-tech/pdfSignableBundle/releases/tag/v1.3.0
[1.2.0]: https://github.com/nowo-tech/pdfSignableBundle/releases/tag/v1.2.0
[1.1.0]: https://github.com/nowo-tech/pdfSignableBundle/releases/tag/v1.1.0
[1.0.0]: https://github.com/nowo-tech/pdfSignableBundle/releases/tag/v1.0.0
