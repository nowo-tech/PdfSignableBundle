# Upgrade Guide

This guide explains how to upgrade the PdfSignable Bundle between versions. For a list of changes in each version, see [CHANGELOG.md](CHANGELOG.md).

## General upgrade process

1. **Back up configuration**  
   Back up `config/packages/nowo_pdf_signable.yaml` (or wherever you configure the bundle) before upgrading.

2. **Check the changelog**  
   Review [CHANGELOG.md](CHANGELOG.md) for the target version to see new features, changes, and breaking changes.

3. **Update the package**  
   Run:
   ```bash
   composer update nowo-tech/pdf-signable-bundle
   ```

4. **Apply configuration changes**  
   If the new version introduces or changes config options, update your config accordingly (see version-specific sections below).

5. **Rebuild frontend assets (if you use the bundle’s assets)**  
   If your app uses the bundle’s Vite/TS entry (e.g. `pdf-signable.ts`), run your build again:
   ```bash
   pnpm run build
   ```

6. **Clear cache**  
   ```bash
   php bin/console cache:clear
   ```

7. **Test**  
   Verify the PDF signable form and viewer (and proxy, if used) work as expected.

---

## Upgrading by version

### Upgrading to 1.5.3

**Release date**: 2026-02-10

#### What's new (no breaking changes)

- **Box item fallback**: Overridden themes that use only the class `.signature-box-item` (without `data-pdf-signable="box-item"`) are now supported; the viewer still finds box rows and draws overlays correctly.
- **Debug logging**: With `nowo_pdf_signable.debug: true`, the viewer logs more detail (DOM resolution, overlay updates, add/remove box, signature pads init, PDF DOM) to help detect missing or wrong elements in overridden templates.

#### Upgrade steps

1. Run `composer update nowo-tech/pdf-signable-bundle`.
2. Rebuild assets if you use the bundle’s JS: `make assets` or `pnpm run build`; then `php bin/console cache:clear`.
3. Optional: set `debug: true` in config when debugging template overrides; check the browser console for `[PdfSignable]` messages.

---

### Upgrading to 1.5.2

**Release date**: 2026-02-12

#### What's new (no breaking changes)

- **Data attributes**: The viewer script finds elements by `data-pdf-signable` attributes instead of CSS classes. Form types and the bundle theme add these automatically. When overriding templates, keep these attributes on the same elements (see [USAGE](USAGE.md#data-attributes-required-when-overriding)); you can change classes for styling.
- **Widget fallback**: If your override does not add `data-pdf-signable="widget"` to the root div, the script still finds the widget by the class `.nowo-pdf-signable-widget`.
- **Page field**: The script finds the page input by `data-pdf-signable="page"` or by `name` ending with `[page]`, so the page number is set when adding a box even if your template override changed the field’s class.
- **Form theme override**: If your overridden form theme is not applied (only the page layout changes), add the theme in `config/packages/twig.yaml` under `form_themes` (see [USAGE](USAGE.md#overriding-the-form-theme)).
- **Workflow doc**: [WORKFLOW.md](WORKFLOW.md) describes init, load PDF, add/drag box, coordinate sync, and submit with Mermaid diagrams.

#### Upgrade steps

1. Run `composer update nowo-tech/pdf-signable-bundle`.
2. If you override the form theme or signature box widget, add the `data-pdf-signable` attributes to the same elements (or rely on the widget/page fallbacks). See [USAGE](USAGE.md#data-attributes-required-when-overriding).
3. Rebuild assets if you use the bundle’s JS: `make assets` or `pnpm run build`; then `php bin/console cache:clear`.

---

### Upgrading to 1.5.1

**Release date**: 2026-02-11

#### What's new (no breaking changes)

- **Named config merge fix**: When you use a named config (e.g. `config: 'fixed_url'` in your form), the config’s values now correctly **override** the form type’s defaults. If your named config sets `url_field: false`, `show_load_pdf_button: false`, `unit_field: false`, or `origin_field: false`, those options now take effect (URL row, Load PDF button, and unit/origin selectors are hidden).
- **Form theme**: Visibility of the URL row, Load PDF button, and unit/origin row now correctly respects the above options when they are `false`.

#### Upgrade steps

1. Run `composer update nowo-tech/pdf-signable-bundle`.
2. Clear cache: `php bin/console cache:clear`.
3. If you use a named config with `url_field`, `unit_field`, or `origin_field` set to `false`, verify that the form now hides those elements as intended.

---

### Upgrading to 1.5.0

**Release date**: 2026-02-10

#### What's new (no breaking changes)

- **Guides and grid**: Form options `show_grid` (default `false`) and `grid_step` (e.g. `5` in form unit). When enabled, a grid overlay is drawn on each page in the viewer. See [USAGE](USAGE.md).
- **Viewer lazy load**: Form option `viewer_lazy_load` (default `false`). When `true`, PDF.js and the signable script load when the widget enters the viewport (IntersectionObserver). See [USAGE](USAGE.md).
- **Single inclusion of assets**: CSS and JS for the PDF viewer are output only once per request when you have multiple signature-coordinates widgets on the same page. If you override `signature_coordinates_widget` and inject the bundle assets yourself, use the Twig function `nowo_pdf_signable_include_assets()` so behaviour stays consistent (see [CONTRIBUTING](CONTRIBUTING.md)).
- **Larger handles**: Resize handles (corners) and the rotation handle on signature boxes are slightly larger (12×12 px and 16×16 px) for easier use.
- **Rotated boxes**: Drag limits for rotated signature boxes now use the visual bounding box, so you can place boxes flush to all page edges (left, right, top, bottom) at any angle (e.g. -90°, 45°).
- **Advanced signing (optional)**: New config `audit.fill_from_request` (default `true`), optional placeholders `tsa_url` and `signing_service_id`; new events `BATCH_SIGN_REQUESTED` and `PDF_SIGN_REQUEST`; form option `batch_sign_enabled` and “Sign all” button. See [SIGNING_ADVANCED](SIGNING_ADVANCED.md) and [EVENTS](EVENTS.md). No breaking changes; existing config remains valid.

#### Upgrade steps

1. Run `composer update nowo-tech/pdf-signable-bundle`.
2. If you use the bundle’s assets, run `pnpm run build` (or `make assets`) and `php bin/console assets:install`.
3. Clear cache: `php bin/console cache:clear`.

---

### Upgrading to 1.4.0

**Release date**: 2026-02-09

#### What's new

- **Signing in boxes**: Draw or upload a signature per box (`enable_signature_capture`, `enable_signature_upload`); image in `SignatureBoxModel::signatureData`. See [USAGE](USAGE.md#signing-in-boxes-draw-or-image).
- **Legal disclaimer**: `signing_legal_disclaimer` and `signing_legal_disclaimer_url` form options. See [USAGE](USAGE.md#legal-disclaimer).
- **Consent checkbox**: `signing_require_consent` and `signing_consent_label`; value in `SignatureCoordinatesModel::getSigningConsent()`.
- **Timestamp per box**: `SignatureBoxModel::getSignedAt()` / `setSignedAt()` (ISO 8601); in toArray/fromArray as `signed_at`.
- **Audit metadata**: `SignatureCoordinatesModel::getAuditMetadata()` / `setAuditMetadata()`; in toArray. See [USAGE](USAGE.md#making-the-signature-more-legally-robust).
- **Signing-only mode**: `signing_only: true` shows only box name (read-only) and signature capture; coordinates hidden but submitted.
- **Signature pad**: High-DPI canvas, smooth strokes, pressure-sensitive line width; full-width signature row.
- **Demo**: "Signing options" page; sidebar with all demos; offcanvas on small screens; all copy in English.

#### Breaking changes

None.

#### Upgrade steps

1. Run `composer update nowo-tech/pdf-signable-bundle`.
2. If you use the bundle's assets, run `pnpm run build` (or `make assets`) and `php bin/console assets:install`.
3. Clear cache: `php bin/console cache:clear`.
4. Optional: enable `enable_signature_capture` / `enable_signature_upload`, `signing_require_consent`, `signing_only`, or legal disclaimer (see [USAGE](USAGE.md)).

---

### Upgrading to 1.4.1

**Release date**: 2026-02-09

Patch release: consent translations (`signing.consent_label`, `signing.consent_required`) added to all locales (CA, CS, DE, FR, IT, NL, PL, PT, RU, TR). No breaking changes. Run `composer update nowo-tech/pdf-signable-bundle` and clear cache as usual.

---

### Upgrading to 1.3.0

**Release date**: 2026-02-09

#### What’s new

- **PDF viewer zoom**: Toolbar with zoom out (−), zoom in (+) and fit width (translated). PDF loads at fit-to-width; zoom range 0.5×–3×. Toolbar in the top-right of the viewer when a PDF is loaded. See [USAGE](USAGE.md).
- **Debug option** (`nowo_pdf_signable.debug`): When `true`, the frontend emits console logs in the browser (PDF load, add/remove box, errors). Default `false`. See [CONFIGURATION](CONFIGURATION.md).
- **Translations**: Zoom labels (`js.zoom_in`, `js.zoom_out`, `js.zoom_fit`) in all supported languages.

#### Breaking changes

None.

#### Upgrade steps

1. Run `composer update nowo-tech/pdf-signable-bundle`.
2. If you use the bundle’s assets, run `pnpm run build` (or `make assets`) and `php bin/console assets:install`.
3. Clear cache: `php bin/console cache:clear`.
4. Optional: set `debug: true` in `nowo_pdf_signable` for development console logging.

---

### Upgrading to 1.1.0

**Release date**: 2026-02-10

#### What’s new

- **Page restriction** (`allowed_pages`): limit which pages boxes can be placed on; page field becomes a dropdown.
- **Proxy URL allowlist** (`proxy_url_allowlist`): restrict which URLs the proxy can fetch (see [CONFIGURATION](CONFIGURATION.md)).
- **Box order** (`sort_boxes`): sort boxes by page, then Y, then X on submit.
- **Non-overlapping boxes** (`prevent_box_overlap`): now **default `true`** with frontend enforcement (drag/resize that would overlap is reverted). Five new translations (CA, CS, NL, PL, RU). Translation validation script checks key parity. Security: proxy no longer leaks exception messages; SSRF mitigation for private/local URLs.

#### Breaking changes / behavior changes

- **`prevent_box_overlap`** default changed from `false` to `true`. If your app allowed overlapping boxes and relied on the previous default, set it explicitly when creating the form:
  ```php
  $builder->add('signatureCoordinates', SignatureCoordinatesType::class, [
      'prevent_box_overlap' => false,
  ]);
  ```

#### Upgrade steps

1. Run `composer update nowo-tech/pdf-signable-bundle`.
2. If you use the bundle’s assets, run `pnpm run build` (or `make assets`) and `php bin/console assets:install`.
3. Optional: add `proxy_url_allowlist` to your config if you want to restrict proxy URLs (see [CONFIGURATION](CONFIGURATION.md)).
4. If you need to allow overlapping boxes, add `'prevent_box_overlap' => false` to your form options.
5. Clear cache: `php bin/console cache:clear`.

---

### Upgrading to 1.0.0

**Release date**: 2026-02-09

#### What’s in this version

- First stable release. Form types `SignatureCoordinatesType` and `SignatureBoxType`, models `SignatureCoordinatesModel` and `SignatureBoxModel`, PDF viewer (PDF.js + overlays), optional proxy, Twig form theme, Vite/TypeScript assets.
- Named configs, events, validation (`unique_box_names` global or per-name), translations (EN, ES, FR, DE, IT, PT, TR).
- Demos for Symfony 7 and 8 with multiple configuration variants (no config, default, fixed URL, overridden, URL as dropdown, limited boxes, same signer multiple, unique per name, page restriction, sorted boxes, no-overlap, predefined boxes).

#### Breaking changes

None — this is the initial stable release.

#### Upgrade steps (from dev version)

If you were using `dev-main` or `dev-master`:

1. Update your constraint in `composer.json` to `^1.0` (or `>=1.0 <2.0`).
2. Run:
   ```bash
   composer update nowo-tech/pdf-signable-bundle
   ```
3. Follow [INSTALLATION.md](INSTALLATION.md) (register bundle, routes, config) if you had a custom setup.
4. Optionally configure `nowo_pdf_signable` as in [CONFIGURATION.md](CONFIGURATION.md).
5. Clear cache: `php bin/console cache:clear`.
6. If you use the bundle’s assets, run `php bin/console assets:install` and ensure the PDF signable script is loaded on the page where the form is rendered.

---

### Upgrading to 1.2.0

**Release date**: 2026-02-10

#### What’s new

- **Optional rotation** (`enable_rotation`): form option (default `false`). When `true`, each box has an **angle** field and the viewer shows a rotate handle above each overlay; when `false`, the angle field is not rendered and boxes are not rotatable. See [USAGE](USAGE.md).
- **Default values per box name** (`box_defaults_by_name`): form option to pre-fill width, height, x, y, angle when the user selects a name (dropdown or input). See [USAGE](USAGE.md).
- **Demos**: rotation, defaults-by-name, and allow-overlap demo pages (16 demo pages in total for Symfony 7 and 8).

#### Breaking changes

None.

#### Upgrade steps

1. Run `composer update nowo-tech/pdf-signable-bundle`.
2. If you use the bundle’s assets, run `pnpm run build` (or `make assets`) and `php bin/console assets:install`.
3. Clear cache: `php bin/console cache:clear`.
4. Optional: set `'enable_rotation' => true` and/or `box_defaults_by_name` on your form if you want rotation or name-based defaults.

---

### Upgrading to a future version (e.g. 1.6.0)

When a new version is released, a new subsection will be added here with:

- **What’s new** (features, options, behavior changes).
- **Breaking changes** (if any): config renames, removed options, API or form model changes.
- **Upgrade steps**: exact config/code changes and any new dependencies or commands.

Always read [CHANGELOG.md](CHANGELOG.md) for the target version before upgrading.

---

## Troubleshooting

### Configuration errors after upgrade

- Run `php bin/console debug:config nowo_pdf_signable` and compare with [CONFIGURATION.md](CONFIGURATION.md).
- Remove deprecated or renamed options and add any new required ones.

### Form or viewer not working after upgrade

- Clear cache: `php bin/console cache:clear`.
- Rebuild frontend assets if you use the bundle’s Vite/TypeScript entry.
- Ensure your Twig form theme still includes the bundle’s theme (e.g. `form_themes: ['@NowoPdfSignable/form/theme.html.twig']` or equivalent).

### Proxy or PDF loading issues

- Check that the proxy route is registered (`/pdf-signable/proxy`) and that `proxy_enabled: true` in `nowo_pdf_signable` config.
- See [USAGE.md](USAGE.md) and [CONFIGURATION.md](CONFIGURATION.md) for proxy and URL options.

---

## Version compatibility

| Bundle version | Symfony      | PHP   | Notes |
|----------------|-------------|-------|-------|
| 1.5.x          | 6.1+, 7.x, 8.x | 8.1+ | 1.5.0: guides and grid, viewer lazy load, advanced signing, single asset inclusion, larger handles, rotated box drag fix, 19 demos. 1.5.1: named config merge fix, demo symlink. 1.5.2: element lookup by data-pdf-signable (with class/name fallbacks), WORKFLOW.md, override form theme note, recipe complete example. 1.5.3: box-item class fallback (.signature-box-item), extended debug logging for template/override issues. |
| 1.4.x          | 6.1+, 7.x, 8.x | 8.1+ | Signing in boxes (draw/upload), consent, signedAt, auditMetadata, signing_only, signature pad, demo sidebar. 1.4.1: consent translations in all locales, test fix. |
| 1.3.x          | 6.1+, 7.x, 8.x | 8.1+ | PDF viewer zoom (in/out/fit), debug config, zoom translations. |
| 1.2.x          | 6.1+, 7.x, 8.x | 8.1+ | Optional rotation (enable_rotation), box_defaults_by_name, 16 demos. |
| 1.1.x          | 6.1+, 7.x, 8.x | 8.1+ | Page restriction, proxy allowlist, sort_boxes, prevent_box_overlap default true, 12 languages. |
| 1.0.x          | 6.1+, 7.x, 8.x | 8.1+ | First stable release. |
| dev-main       | 6.1+, 7.x, 8.x | 8.1+ | Development; use only if you need unreleased changes. See [INSTALLATION.md](INSTALLATION.md). |

---

## See also

- [CHANGELOG.md](CHANGELOG.md) — Full version history.
- [INSTALLATION.md](INSTALLATION.md) — Install and register the bundle.
- [CONFIGURATION.md](CONFIGURATION.md) — Configuration reference.
- [USAGE.md](USAGE.md) — Using the form type and proxy in your app.
- [SIGNING_ADVANCED.md](SIGNING_ADVANCED.md) — PKI, timestamp, audit, and batch signing (from 1.5.0).
