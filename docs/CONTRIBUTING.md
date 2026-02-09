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
- TypeScript: follow the existing style in `assets/pdf-signable.ts`.
- Documentation: English for code comments, PHPDoc, JSDoc and docs in `docs/`.

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
