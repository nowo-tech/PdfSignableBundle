# Upgrade Guide

This guide explains how to upgrade the PdfSignable Bundle between versions. For a list of changes in each version, see [CHANGELOG.md](CHANGELOG.md).

**Note:** Version **2.0.0** is a **breaking** release for configuration: the YAML structure changes (signature under `signature`, AcroForm under a single `acroform` node). If you are on 1.5.x or earlier, read the [Upgrading to 2.0.0](#upgrading-to-200-2026-02-16) section before updating.

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
   If your app uses the bundle’s Vite/TS entry (e.g. `signable-editor.ts`), run your build again:
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

### Upgrading to 2.0.0 (2026-02-16)

**Release date**: 2026-02-16

**Breaking release:** This version changes the configuration structure. Signature options move under a `signature` node; AcroForm options are merged into a single `acroform` node (replacing `acroform_editor` and `acroform_configs`). You must migrate your YAML and any PHP that reads container parameters. Form types, models, events and Twig APIs are unchanged. See sections and examples below.

#### Container parameter renames (if you inject or read parameters in PHP)

- **Signature:** `nowo_pdf_signable.default_box_width` → `nowo_pdf_signable.signature.default_box_width` (and same for `default_box_height`, `lock_box_width`, `lock_box_height`, `min_box_width`, `min_box_height`). `nowo_pdf_signable.configs` → `nowo_pdf_signable.signature.configs`.
- **AcroForm:** `nowo_pdf_signable.acroform_editor.<key>` → `nowo_pdf_signable.acroform.<key>` (e.g. `acroform_editor.enabled` → `acroform.enabled`). `nowo_pdf_signable.acroform_configs` → `nowo_pdf_signable.acroform.configs`.

#### Breaking: Signature config under `signature` node (global + configs by alias)

- **Before:** Top-level keys: `default_box_width`, `default_box_height`, `lock_box_width`, `lock_box_height`, `min_box_width`, `min_box_height`, and `configs` (named presets for the signature form).
- **After:** A single **`signature`** node containing:
  - **`default_config_alias`** (string, default `'default'`): alias used when form option `config` is not set; resolved from `signature.configs[alias]`.
  - **Global box options:** `default_box_width`, `default_box_height`, `lock_box_width`, `lock_box_height`, `min_box_width`, `min_box_height` (same meaning as before).
  - **`configs`** (map alias → form options): named configs (e.g. `default: {}`, `fixed_url: { pdf_url: ... }`). Use form option `config: 'alias'` to apply.

**Migration:** Move the six box options and `configs` under `signature`:

```yaml
# Before
nowo_pdf_signable:
  default_box_width: null
  default_box_height: null
  lock_box_width: false
  lock_box_height: false
  min_box_width: null
  min_box_height: null
  configs:
    default: {}
    fixed_url: { pdf_url: '%env(EXAMPLE_PDF_URL)%', ... }

# After
nowo_pdf_signable:
  signature:
    default_config_alias: default   # optional
    default_box_width: null
    default_box_height: null
    lock_box_width: false
    lock_box_height: false
    min_box_width: null
    min_box_height: null
    configs:
      default: {}
      fixed_url: { pdf_url: '%env(EXAMPLE_PDF_URL)%', ... }
```

- **Container parameters:** All `nowo_pdf_signable.default_box_*`, `nowo_pdf_signable.lock_box_*`, `nowo_pdf_signable.min_box_*` and `nowo_pdf_signable.configs` become `nowo_pdf_signable.signature.default_box_*`, `nowo_pdf_signable.signature.lock_box_*`, `nowo_pdf_signable.signature.min_box_*` and `nowo_pdf_signable.signature.configs`. If you read these in PHP, update the parameter names.

#### Breaking: AcroForm config merged into single `acroform` node

- **Before:** Two top-level keys: `acroform_editor` (platform + editor defaults) and `acroform_configs` (named configs).
- **After:** A single **`acroform`** node containing:
  - **Platform / global** (not per-alias): `enabled`, `overrides_storage`, `document_key_mode`, `allow_pdf_modify`, `editor_service_id`, `max_pdf_size`, `max_patches`, `fields_extractor_script`, `apply_script`, `apply_script_command`, `process_script`, `process_script_command`.
  - **Editor defaults** (global; overridable per config alias): `min_field_width`, `min_field_height`, `label_mode`, `label_choices`, `label_other_text`, `show_field_rect`, `font_sizes`, `font_families`.
  - **`default_config_alias`** (string, default `'default'`): alias used when form option `config` is not set; resolved from `acroform.configs[alias]`.
  - **`configs`** (map alias → options): named configs (e.g. `default: {}`, `label_dropdown: { label_mode: choice, ... }`, `with_fonts: { font_sizes: [...] }`). Use form option `config: 'alias'` to apply.

**Migration:** Replace `acroform_editor:` and `acroform_configs:` with one block:

```yaml
# Before
nowo_pdf_signable:
  acroform_editor:
    enabled: true
    label_mode: input
    # ...
  acroform_configs:
    label_dropdown: { label_mode: choice, ... }

# After
nowo_pdf_signable:
  acroform:
    enabled: true
    # platform: fields_extractor_script, apply_script, process_script, ...
    # default_config_alias: default   # optional
    label_mode: input
    # ... (editor defaults)
    configs:
      default: {}
      label_dropdown: { label_mode: choice, ... }
```

- **Container parameters:** All `nowo_pdf_signable.acroform_editor.*` and `nowo_pdf_signable.acroform_configs` become `nowo_pdf_signable.acroform.*` and `nowo_pdf_signable.acroform.configs`. If you read these in PHP (e.g. in a controller), update the parameter names.

#### What's new (no breaking changes besides config)

- **AcroForm apply — font family in PDF**: When applying patches to the PDF, `fontSize` and `fontFamily` are now sent in the payload and written to the PDF default appearance (/DA) by the Python apply script. `AcroFormFieldPatch` includes `fontFamily`.
- **Demo AcroForm section**: Dedicated "AcroForm" section in the demo nav with seven demos showcasing the new options.

#### Upgrade steps

1. Run `composer update nowo-tech/pdf-signable-bundle`.
2. **Migrate signature config:** Move top-level `default_box_width`, `default_box_height`, `lock_box_width`, `lock_box_height`, `min_box_width`, `min_box_height` and `configs` under a `signature:` node as shown above. If you read these in PHP, update parameter names to `nowo_pdf_signable.signature.*` and `nowo_pdf_signable.signature.configs`.
3. **Migrate AcroForm config:** Replace `acroform_editor` and `acroform_configs` with a single `acroform` node as shown above. Ensure `configs` includes at least a `default` entry if you rely on the default alias. Update parameter names: `nowo_pdf_signable.acroform_editor.*` → `nowo_pdf_signable.acroform.*`, `nowo_pdf_signable.acroform_configs` → `nowo_pdf_signable.acroform.configs`.
4. Rebuild assets if you use the bundle's JS; then `php bin/console cache:clear`.

For the full list of options in the new structure, see [CONFIGURATION.md](CONFIGURATION.md). For the full list of changes in this version, see [CHANGELOG.md](CHANGELOG.md).

---

### Upgrading to 2.0.2 (2026-02-16)

**Release date:** 2026-02-16

**Patch release:** No breaking changes. Improves first-time setup (copy-paste example for routes in the bundle’s routes YAML), adds optional validation of `proxy_url_allowlist` regex patterns in dev (compiler pass warns on invalid patterns), extends test coverage, and marks environment-dependent tests with `@group integration`.

#### What's new

- **Routes:** The bundle’s `Resources/config/routes.yaml` now includes a comment with a ready-to-paste block for your app’s `config/routes.yaml` (resource + prefix).
- **Allowlist validation (dev only):** When `kernel.debug` is true, invalid regex entries (prefix `#`) in `proxy_url_allowlist` trigger a PHP warning at container compile. Fix or remove invalid patterns; production is unaffected.
- **Tests:** Additional tests for proxy validator (SSRF, allowlist), dependency listener cache, bundle build, command output, models and config. Two `DependencyCheckerTest` methods are in `@group integration` for optional CI exclusion.
- **ProxyUrlValidator (SSRF):** Fixed handling when the URL has no valid host (`parse_url` returns `false`) and improved IPv6 literal blocking (bracket stripping, early `fe80:` check) so tests and SSRF mitigation work in all environments.
- **Packagist:** `composer.json` no longer includes the `repository` property, so `composer validate --strict` passes (Packagist uses the repo URL from the package registration).

#### Upgrade steps (from 2.0.0 or 2.0.1)

1. Run `composer update nowo-tech/pdf-signable-bundle`.
2. Clear cache: `php bin/console cache:clear`.

No config or asset changes required. See [CHANGELOG.md](CHANGELOG.md) for the full list of changes.

---

### Upgrading to 2.0.1 (2026-02-16)

**Release date:** 2026-02-16

**Patch release:** No breaking changes. Fixes translations (missing AcroForm modal keys in 10 locales, Turkish YAML), PDF.js worker loading (default worker is now `.js` for correct MIME type; absolute URL resolution and script fallback), and adds tests.

#### Upgrade steps (from 2.0.0)

1. Run `composer update nowo-tech/pdf-signable-bundle`.
2. Rebuild assets if you use the bundle’s JS so the default worker `pdf.worker.min.js` is emitted: `pnpm run build` in the bundle repo, or `php bin/console assets:install` in your app to copy updated public files.
3. Optional: run `make validate-translations` in the bundle repo.
4. Clear cache: `php bin/console cache:clear`.

If you override `pdfjs_worker_url`, you can point it to `bundles/nowopdfsignable/js/pdf.worker.min.js` or leave it null for the theme default. See [CHANGELOG.md](CHANGELOG.md) for the full list of changes.

---

### Upgrading to 1.5.4

**Release date**: 2026-02-11

#### What's new (no breaking changes)

- **Show AcroForm** (`show_acroform`): New form option (default `true`). When enabled, the viewer draws an outline over PDF AcroForm/form fields so they are visible. Uses PDF.js `getAnnotations()`; outlines do not block clicks (you can still add signature boxes). Set `show_acroform: false` to hide them. See [USAGE](USAGE.md).
- **Defaults**: Recipe example and demo named configs include `show_acroform: true` so AcroForm visibility is on by default when using those presets.

#### Upgrade steps

1. Run `composer update nowo-tech/pdf-signable-bundle`.
2. Rebuild assets if you use the bundle’s JS: `make assets` or `pnpm run build`; then `php bin/console cache:clear`.
3. Optional: set `show_acroform: false` on your form or in a named config if you do not want AcroForm outlines.

---

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

### Upgrading to a future version (e.g. 2.0.0)

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
| 2.0.x          | 6.1+, 7.x, 8.x | 8.1+ | **2.0.0 breaking:** Signature under `signature` node; AcroForm under single `acroform` node. **2.0.1:** PDF.js worker default `.js` (MIME fix), worker URL absolute/fallback, translations (AcroForm modal keys + tr YAML), tests. **2.0.2:** Routes YAML copy-paste example, allowlist regex validation in dev (compiler pass), extended tests, `@group integration` for env-dependent tests. |
| 1.5.x          | 6.1+, 7.x, 8.x | 8.1+ | 1.5.0: guides and grid, viewer lazy load, advanced signing, single asset inclusion, larger handles, rotated box drag fix, 19 demos. 1.5.1: named config merge fix, demo symlink. 1.5.2: element lookup by data-pdf-signable (with class/name fallbacks), WORKFLOW.md, override form theme note, recipe complete example. 1.5.3: box-item class fallback (.signature-box-item), extended debug logging. 1.5.4: show_acroform option (default true), AcroForm outline overlay; recipe and demos set show_acroform: true in signature.configs / acroform.configs. |
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
