# Installation

## Requirements

- **PHP** 8.1+
- **Symfony** 6.1+, 7.x or 8.x
- **PHP extensions:** form, http-client, twig, translation, validator, yaml

**Optional — AcroForm Apply / Process / Extract (Python):** If you use the bundle’s **Apply to PDF**, **Process**, or **fields extract** endpoints with the included Python scripts (`scripts/apply_acroform_patches.py`, `scripts/process_modified_pdf.py`, `scripts/extract_acroform_fields.py`), you need **Python 3.9+** and, for the apply and extract scripts, the **pypdf** package (`pip install pypdf`). The process script is a stub; replace it with your own and install any Python deps you need. If you implement Apply or Process in PHP (e.g. via `AcroFormApplyRequestEvent` or a service implementing `PdfAcroFormEditorInterface`), no Python is required. See [ACROFORM_BACKEND_EXTENSION](ACROFORM_BACKEND_EXTENSION.md) and [CONFIGURATION](CONFIGURATION.md#acroform).

## Composer

Install the bundle (stable, from Packagist or your configured repository):

```bash
composer require nowo-tech/pdf-signable-bundle
```

**Development / unreleased:** To use the latest `main` (or default) branch before the next tag:

```bash
composer config repositories.pdf-signable-bundle vcs https://github.com/nowo-tech/pdfSignableBundle
composer require nowo-tech/pdf-signable-bundle:dev-main
```

Use `dev-main` or `dev-master` to match the repository’s default branch.

## Register the bundle

With Symfony Flex the bundle is registered automatically. Otherwise, add to `config/bundles.php`:

```php
return [
    // ...
    Nowo\PdfSignableBundle\NowoPdfSignableBundle::class => ['all' => true],
];
```

## Routes

Import the bundle routes in `config/routes.yaml` (or `config/routes/`) and set your prefix (e.g. `/pdf-signable`). One import registers both signature and AcroForm routes:

```yaml
nowo_pdf_signable:
    resource: '@NowoPdfSignableBundle/Resources/config/routes.yaml'
    prefix: /pdf-signable
```

Resulting routes (with `prefix: /pdf-signable`): form page and proxy under that prefix; with AcroForm enabled, also `/pdf-signable/acroform/overrides` (GET/POST/DELETE), optionally `/pdf-signable/acroform/apply` (POST) when `allow_pdf_modify` is true, and optionally `/pdf-signable/acroform/process` (POST) when `process_script` is set.

## Assets

The bundle ships a built JavaScript file (PDF viewer and signature boxes). Install it into your `public/` directory:

```bash
php bin/console assets:install
```

This copies `Resources/public/js/pdf-signable.js` (and `acroform-editor.js` when using the AcroForm editor) to `public/bundles/nowopdfsignable/js/`.

## Base template

The bundle views extend `base.html.twig` by default. Ensure your application has a base template (e.g. `templates/base.html.twig`) with blocks `title`, `stylesheets`, `body` and `javascripts`.

## See also

- [Configuration](CONFIGURATION.md) — Proxy, example URL, named configs, audit and signing placeholders.
- [Usage](USAGE.md) — Form options and examples; [overriding bundle templates](USAGE.md#overriding-bundle-templates) (form theme, signature index). Use directory `templates/bundles/NowoPdfSignable/` (name without the `Bundle` suffix).
