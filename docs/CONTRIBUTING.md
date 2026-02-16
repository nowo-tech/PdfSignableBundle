# Contributing to PdfSignable Bundle

Thank you for considering contributing.

## Development setup

1. Clone the repository and install dependencies:

   ```bash
   composer install
   ```

2. Run tests and code style checks:

   ```bash
   composer test
   composer cs-check
   composer qa   # both
   ```

   Optionally use Docker from the bundle root:

   ```bash
   make up && make install
   make test && make cs-check
   make validate-translations   # ensure all translation YAML files have the same keys
   ```

## Code style

- PHP: [PHP-CS-Fixer](https://github.com/FriendsOfPHP/PHP-CS-Fixer) with the bundled config (Symfony rules, strict types). Run `composer cs-fix` to fix.
- TypeScript: follow the existing style in `assets/signable-editor.ts`.
- Documentation: English for code comments, PHPDoc, JSDoc and docs in `docs/`.

## Form theme and assets

- The form theme (`src/Resources/views/form/theme.html.twig`) includes the PDF viewer CSS and JS (PDF.js, pdf-signable.js) only once per request using the Twig function `nowo_pdf_signable_include_assets()` from `Nowo\PdfSignableBundle\Twig\NowoPdfSignableTwigExtension`. If you override `signature_coordinates_widget` and still want to output the bundle assets, use the same function so multiple widgets on the same page do not duplicate the link/scripts.

## Pull requests

- Target the `master` (or `main`) branch.
- Keep changes focused; prefer several small PRs over one large one.
- Ensure tests pass and code style is clean (`composer qa`).
- Update the [Changelog](CHANGELOG.md) under `[Unreleased]` for user-facing changes.

## Reporting issues

- Use the [GitHub issue tracker](https://github.com/nowo-tech/pdf-signable-bundle/issues).
- For security issues, see [SECURITY.md](SECURITY.md).

## License

By contributing, you agree that your contributions will be licensed under the [MIT License](../LICENSE).
