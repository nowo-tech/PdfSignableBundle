# Flex recipe for nowo-tech/pdf-signable-bundle

This folder contains the **Symfony Flex recipe** (version `1.0`) used when installing the bundle. It is intended to be submitted to [symfony/recipes-contrib](https://github.com/symfony/recipes-contrib) once the first tag (e.g. `v1.0.0`) is published, or used with a [private recipe endpoint](https://symfony.com/doc/current/setup/flex_private_recipes.html).

## Contents

- **1.0/manifest.json** — Registers the bundle, copies recipe config into the app, and shows a post-install message for `assets:install`.
- **1.0/config/packages/nowo_pdf_signable.yaml** — Bundle configuration with **all options documented** (proxy, example URL, debug; **signature** node with global box defaults and named configs by alias; audit, TSA/signing placeholders; **acroform** node with platform settings and named configs by alias). Includes example signature configs (default active; fixed_url, limited_boxes, snap_and_grid, signing_boxes, full_reference commented) and commented acroform configs. See `docs/CONFIGURATION.md` for full reference.
- **1.0/config/routes/nowo_pdf_signable.yaml** — Imports the bundle routes (`/pdf-signable` and `/pdf-signable/proxy`).

## No tag yet

No stable release or tag has been published yet. The recipe version is `1.0` so that when you release `v1.0.0`, Flex will use this recipe. Until then, install the package with `dev-main` (see [INSTALLATION.md](../../../../docs/INSTALLATION.md)); Flex will not run this recipe unless the recipe is available from a recipe server that points at this repo or at symfony/recipes-contrib after a PR.

## Submitting to recipes-contrib

1. Copy the contents of `1.0/` (manifest.json and config/) into a new PR to [symfony/recipes-contrib](https://github.com/symfony/recipes-contrib) under `nowo-tech/pdf-signable-bundle/1.0/`.
2. Ensure the repo is registered in the contrib recipes index so Flex can find it after the first release.
