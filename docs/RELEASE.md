# Release process

## Creating a new version (e.g. v1.4.1)

1. **Ensure everything is ready**
   - [CHANGELOG.md](CHANGELOG.md) has the target version (e.g. `[1.4.1]`) with date and full entry; `[Unreleased]` is at the top and empty or updated for the next cycle.
   - [UPGRADING.md](UPGRADING.md) has a section “Upgrading to X.Y.Z” with what’s new, breaking changes (if any), and upgrade steps.
   - Tests pass: `make test` or `composer test`.
   - Code style: `make cs-check` or `composer cs-check`.
   - Assets built: `make assets` (so `src/Resources/public/js/pdf-signable.js` is up to date).
   - Translations: `make validate-translations` passes.

2. **Commit and push** any last changes to your default branch (e.g. `main` or `master`):
   ```bash
   git add -A
   git commit -m "Prepare v1.4.1 release"
   git push origin HEAD
   ```

3. **Create and push the tag**
   ```bash
   git tag -a v1.4.1 -m "Release v1.4.1"
   git push origin v1.4.1
   ```

4. **GitHub Actions** (if [.github/workflows/release.yml](../.github/workflows/release.yml) is configured) will create the GitHub Release from the tag.

5. **Packagist** (if the package is registered) will pick up the new tag; users can then `composer require nowo-tech/pdf-signable-bundle`.

## After releasing

- Keep `## [Unreleased]` at the top of [CHANGELOG.md](CHANGELOG.md) for the next version; add new changes there.
- Optionally bump `version` in `composer.json` to the next dev (e.g. `1.4.0-dev`) for development.

---

## Ready for v1.4.1 (2026-02-09)

- [x] CHANGELOG: [1.4.1] with translation fixes (consent in all locales, YAML escaping), test fix (no double submit).
- [x] UPGRADING: “Upgrading to 1.4.1” and version table.
- [x] RELEASE: checklist updated for v1.4.1; validate-translations in steps.
- [x] `make validate-translations` passes (12 files).
- [ ] Run `make test` and `make cs-check`.
- [ ] Run `make assets` (bundle JS built).
- [ ] Commit, push, then: `git tag -a v1.4.1 -m "Release v1.4.1"` and `git push origin v1.4.1`.
