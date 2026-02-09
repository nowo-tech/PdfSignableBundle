# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

_Nothing yet._

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

[Unreleased]: https://github.com/nowo-tech/pdf-signable-bundle/compare/v1.2.0...HEAD
[1.2.0]: https://github.com/nowo-tech/pdf-signable-bundle/releases/tag/v1.2.0
[1.1.0]: https://github.com/nowo-tech/pdf-signable-bundle/releases/tag/v1.1.0
[1.0.0]: https://github.com/nowo-tech/pdf-signable-bundle/releases/tag/v1.0.0
