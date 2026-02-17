# Release process

## Creating a new version (e.g. v1.5.0)

1. **Ensure everything is ready**
   - [CHANGELOG.md](CHANGELOG.md) has the target version (e.g. `[1.5.0]`) with date and full entry; `[Unreleased]` is at the top and empty or updated for the next cycle.
   - [UPGRADING.md](UPGRADING.md) has a section “Upgrading to X.Y.Z” with what’s new, breaking changes (if any), and upgrade steps.
   - Tests pass: `make test` or `composer test`.
   - Code style: `make cs-check` or `composer cs-check`.
   - Assets built: `make assets` (so `src/Resources/public/js/pdf-signable.js` is up to date).
   - Translations: `make validate-translations` passes.

2. **Commit and push** any last changes to your default branch (e.g. `main` or `master`):
   ```bash
   git add -A
   git commit -m "Prepare v1.5.0 release"
   git push origin HEAD
   ```

3. **Create and push the tag**
   ```bash
   git tag -a v1.5.0 -m "Release v1.5.0"
   git push origin v1.5.0
   ```

4. **GitHub Actions** (if [.github/workflows/release.yml](../.github/workflows/release.yml) is configured) will create the GitHub Release from the tag.

5. **Packagist** (if the package is registered) will pick up the new tag; users can then `composer require nowo-tech/pdf-signable-bundle`. See [Registering on Packagist](#registering-on-packagist) below if the package is not yet on Packagist.

## Registering on Packagist

For the bundle to be **discoverable and installable** via `composer require nowo-tech/pdf-signable-bundle` without adding a custom repository, it must be registered on [Packagist](https://packagist.org).

1. **Create an account** at [packagist.org](https://packagist.org) (or log in with GitHub).
2. **Submit the package**: go to [Submit](https://packagist.org/packages/submit) and enter the **repository URL**:
   - `https://github.com/nowo-tech/pdfSignableBundle`
   - or `https://github.com/nowo-tech/pdfSignableBundle.git`
3. Packagist will fetch `composer.json` from the root of the repository. Ensure:
   - **name** is `nowo-tech/pdf-signable-bundle` (lowercase, hyphen-separated).
   - **description** is set (required for publication).
   - There is **no `version`** field in `composer.json` (Packagist infers versions from Git tags, e.g. `v1.0.0`, `v2.0.2`).
4. After submission, Packagist will **auto-update** when you push new tags (or you can trigger an update from the package page).
5. Validate before submitting: run `composer validate` in the bundle root (and fix any errors).

Once registered, the package appears in search, and users can install it with `composer require nowo-tech/pdf-signable-bundle` without any extra repository configuration.

## After releasing

- Keep `## [Unreleased]` at the top of [CHANGELOG.md](CHANGELOG.md) for the next version; add new changes there.
- Optionally bump `version` in `composer.json` to the next dev (e.g. `1.5.0-dev`) for development.

---

## Ready for v2.0.2 (2026-02-16)

- [x] CHANGELOG: [2.0.2] with date; [Unreleased] at top; links updated.
- [x] UPGRADING: “Upgrading to 2.0.2" with release date and upgrade steps; version table updated.
- [x] RELEASE: this checklist for v2.0.2.
- [ ] Run locally: `make test` and `make cs-check`.
- [ ] Run locally: `make assets` (bundle JS + worker built).
- [ ] Run locally: `make validate-translations`.
- [ ] Run locally: `composer validate --strict` (root `composer.json`).
- [ ] Commit and push: `git add -A && git commit -m "Prepare v2.0.2 release" && git push origin HEAD`
- [ ] Create and push tag: `git tag -a v2.0.2 -m "Release v2.0.2"` then `git push origin v2.0.2`

---

## Next release (e.g. v2.0.3)

- [ ] CHANGELOG: Move [Unreleased] entries into `[X.Y.Z]` with date; add new empty [Unreleased] at top; update version links at bottom.
- [ ] UPGRADING: Add section "Upgrading to X.Y.Z" with release date, what's new, breaking changes (if any), and upgrade steps; update version compatibility table.
- [ ] RELEASE: Replace "Next release" checklist with "Ready for vX.Y.Z" and complete the steps above.
- [ ] Run `make test`, `make cs-check`, `make assets`, `make validate-translations`.
- [ ] Tag and push; create GitHub Release if workflow is configured.
