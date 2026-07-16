# Contributing to PdfSignable Bundle

Thank you for considering contributing.


## Code of Conduct

This project follows the [Contributor Covenant Code of Conduct](../CODE_OF_CONDUCT.md). By participating, you are expected to uphold it. Please report unacceptable behavior to **hectorfranco@nowo.tech**.

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
- TypeScript: follow the existing style in `src/Resources/assets/signable-editor.ts`.
- Documentation: English for code comments, PHPDoc, JSDoc and docs in `docs/`.

## Form theme and assets

- The form theme (`src/Resources/views/form/theme.html.twig`) includes the PDF viewer CSS and JS (PDF.js, pdf-signable.js) only once per request using the Twig function `nowo_pdf_signable_include_assets()` from `Nowo\PdfSignableBundle\Twig\NowoPdfSignableTwigExtension`. If you override `signature_coordinates_widget` and still want to output the bundle assets, use the same function so multiple widgets on the same page do not duplicate the link/scripts.

## Pull requests

- Target the `master` (or `main`) branch.
- Keep changes focused; prefer several small PRs over one large one.
- Ensure tests pass and code style is clean (`composer qa`).
- Update the [Changelog](CHANGELOG.md) under `[Unreleased]` for user-facing changes.

## Reporting issues

- Use the [GitHub issue tracker](https://github.com/nowo-tech/PdfSignableBundle/issues).
- For security issues, see [SECURITY.md](SECURITY.md).

## License

By contributing, you agree that your contributions will be licensed under the [MIT License](../LICENSE).

## Git hooks (REQ-GIT-001)

Do **not** add `Co-authored-by: Cursor` or `cursoragent@cursor.com` trailers to commit messages.

```bash
make setup-hooks
make check-no-cursor-coauthor
```

`make setup-hooks` installs `.githooks/commit-msg` (or sets `core.hooksPath` to `.githooks`). Run it once per clone before your first commit.
If CI fails because trailers are already on the remote, see [GITHUB_CI.md](GITHUB_CI.md) (REQ-GIT-001) and run `make strip-cursor-coauthor-from-history` before `git push --force-with-lease`.
