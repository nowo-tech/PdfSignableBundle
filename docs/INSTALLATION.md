# Installation

## Requirements

- PHP 8.1+
- Symfony 6.1+, 7.x or 8.x
- Extensions: form, http-client, twig, translation, yaml

## Composer

```bash
composer require nowo-tech/pdf-signable-bundle
```

## Register the bundle

With Symfony Flex the bundle is registered automatically. Otherwise, add to `config/bundles.php`:

```php
return [
    // ...
    Nowo\PdfSignableBundle\NowoPdfSignableBundle::class => ['all' => true],
];
```

## Routes

Import the bundle routes in `config/routes.yaml` (or `config/routes/`):

```yaml
nowo_pdf_signable:
    resource: '@NowoPdfSignableBundle/Resources/config/routes.yaml'
```

The bundle defines the prefix `/pdf-signable`; routes are `/pdf-signable` (form page) and `/pdf-signable/proxy` (PDF proxy).

## Assets

The bundle ships a built JavaScript file (PDF viewer and signature boxes). Install it into your `public/` directory:

```bash
php bin/console assets:install
```

This copies `Resources/public/js/pdf-signable.js` to `public/bundles/nowopdfsignable/js/`.

## Base template

The bundle views extend `base.html.twig` by default. Ensure your application has a base template (e.g. `templates/base.html.twig`) with blocks `title`, `stylesheets`, `body` and `javascripts`.
