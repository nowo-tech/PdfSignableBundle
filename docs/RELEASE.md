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

## Released v2.0.5 (2026-04-15)

Tag `v2.0.5` published from commit `d847e85` (prepare release). Follow-up checklist items (`make test`, etc.) remain recommended on each release.

---

## Ready for v3.0.0 (2026-07-01)

- [x] CHANGELOG: [3.0.0] with date; [Unreleased] at top; links updated.
- [x] UPGRADING: “Upgrading to 3.0.0” with breaking changes and upgrade steps; compatibility table updated.
- [x] RELEASE: this checklist for v3.0.0.
- [x] README and INSTALLATION: PHP 8.2+ and Symfony 7+.
- [ ] Run locally: `make test` and `make cs-check`.
- [ ] Run locally: `make validate-translations`.
- [ ] Run locally: `composer validate --strict` (root `composer.json`).
- [ ] Commit and push: `git add -A && git commit -m "Prepare v3.0.0 release" && git push origin HEAD`
- [ ] Create and push tag: `git tag -a v3.0.0 -m "Release v3.0.0"` then `git push origin v3.0.0`

---

## Released v2.0.7 (2026-07-01)

Tag `v2.0.7` published from commit `54a36cd` (prepare release).

---

## Released v2.0.6 (2026-05-14)

Tag `v2.0.6` published from commit `2191d01` (prepare release).

---

## Next release (e.g. v3.0.1)

- [ ] CHANGELOG: Move [Unreleased] entries into `[X.Y.Z]` with date; add new empty [Unreleased] at top; update version links at bottom.
- [ ] UPGRADING: Add section "Upgrading to X.Y.Z" with release date, what's new, breaking changes (if any), and upgrade steps; update version compatibility table.
- [ ] RELEASE: Replace "Next release" checklist with "Ready for vX.Y.Z" and complete the steps above.
- [ ] Run `make test`, `make cs-check`, `make assets`, `make validate-translations`.
- [ ] Tag and push; create GitHub Release if workflow is configured.
