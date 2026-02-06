# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- **AJAX form submit**: Form submits via fetch; on success the page stays on the same URL and shows an alert with the submitted coordinates (JSON format). Controller returns JSON when `Accept: application/json` or `X-Requested-With: XMLHttpRequest`.
- **Translation**: `js.alert_submit_error` for submit failure messages (EN, ES).

### Changed

- **PDF viewer**: ResizeObserver loop fix — added `scrollbar-gutter: stable` and `isReRendering` flag to prevent layout oscillation when the scrollbar appears/disappears.
- **Makefile**: `make install` and `make assets` run via Docker (`docker-compose exec`) for consistency.

---

## [1.0.0] - TBD

### Added

- **Form types**
  - `SignatureCoordinatesType`: form field that renders a PDF viewer and lets users define signature boxes by click; submits `SignatureCoordinatesModel` (pdfUrl, unit, origin, signatureBoxes).
  - `SignatureBoxType`: child type for a single signature box; submits `SignatureBoxModel` (name, page, x, y, width, height).
- **Models**
  - `SignatureCoordinatesModel`: pdfUrl, unit (mm, cm, pt, px, in), origin (corners), signatureBoxes collection.
  - `SignatureBoxModel`: name, page, x, y, width, height.
- **PDF viewer**
  - Browser-based viewer using PDF.js with overlays for each signature box.
  - Click on the PDF to add a box; drag to move; drag corners to resize.
- **Configuration**
  - `nowo_pdf_signable`: `proxy_enabled` for external PDFs (avoids CORS), `example_pdf_url` for form preload.
- **Optional proxy**
  - Route and controller to proxy external PDFs and avoid CORS issues when loading PDFs from another domain.
- **Form theme**
  - Twig form theme for the signature coordinates widget (full widget: PDF + boxes).
- **Frontend assets**
  - Vite + TypeScript entry (`assets/pdf-signable.ts`) for the PDF viewer and box interaction logic.
- **Demos**
  - Dockerized demos for Symfony 7 and Symfony 8 (Bootstrap, Vite, TypeScript).
  - Demo pages: default config, fixed PDF URL, URL as dropdown, limited boxes, predefined boxes.
  - Navigation and “Configuration” cards describing the active options per page.
- **Documentation**
  - README, INSTALLATION, CONFIGURATION, USAGE (all in English).

### Compatibility

- PHP 8.1+
- Symfony 6.1+, 7.x, 8.x.

---

[Unreleased]: https://github.com/nowo-tech/pdf-signable-bundle/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/nowo-tech/pdf-signable-bundle/releases/tag/v1.0.0
