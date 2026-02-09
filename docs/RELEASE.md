# Release process

## Creating v1.0.0 (or next version)

1. **Ensure everything is ready**
   - [CHANGELOG.md](CHANGELOG.md) has `[1.0.0]` (or the target version) with date and full entry.
   - Tests pass: `make test` or `composer test`.
   - Code style: `make cs-check` or `composer cs-check`.

2. **Commit and push** any last changes to the default branch.

3. **Create and push the tag**
   ```bash
   git tag -a v1.0.0 -m "Release v1.0.0"
   git push origin v1.0.0
   ```

4. **GitHub Actions** (if [.github/workflows/release.yml](../.github/workflows/release.yml) is configured) will create the GitHub Release from the tag and attach the changelog entry.

5. **Packagist** (if the package is registered) will pick up the new tag; users can then `composer require nowo-tech/pdf-signable-bundle`.

## After releasing

- In [CHANGELOG.md](CHANGELOG.md), add a new `## [Unreleased]` section at the top for the next version (already present; add new changes there).
- Optionally bump `version` in `composer.json` to the next dev (e.g. `1.1.0-dev`) for development.
