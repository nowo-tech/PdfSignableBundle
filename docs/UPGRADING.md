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

### Upgrading to a future version (e.g. 1.3.0)

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
