# Roadmap — Possible improvements

This document lists **possible improvements** and ideas for future versions of the bundle. They are not commitments or a fixed plan; they serve as a reference for contributions and prioritisation.

---

## Form and coordinates functionality

- **Default values per box name**  
  Allow config (or form type options) to define default size/position per `name` (e.g. `signer_1` always 150×40, `witness` 120×30).

- **Page restriction**  
  Option to limit which pages boxes can be placed on (e.g. page 1 only, or range 1–3) via `allowed_pages` or `page_choices`.

- **Box order**  
  Option to sort the collection by page and then by position (Y/X) when serialising or displaying in the overlay.

- **Export/import coordinates**  
  Helpers or standard format (JSON/YAML) to export the coordinates model and import it into another form or environment.

- **Customisable constraints**  
  Allow injecting additional constraints on the collection or each box (e.g. validate that boxes do not overlap).

---

## User experience (PDF viewer)

- **Keyboard shortcuts**  
  Keys for “Add box”, “Undo last box”, “Delete selected”, “Zoom in/out”.

- **Guides and grid**  
  Option to show guides or grid on the canvas (e.g. every 10 mm) to align boxes.

- **Snap to grid / snap between boxes**  
  When dragging, snap boxes to a grid or align with other boxes’ edges.

- **Print preview**  
  Option to see how boxes would look on a rendered PDF (no interaction).

- **Touch support**  
  Improve gestures on tablets (pinch zoom, two-finger drag) in the viewer.

- **PDF loading indicator**  
  Clear spinner or progress bar while the document loads or the proxy is used.

---

## Backend and integration

- **Optional persistence**  
  Service or interface to persist/retrieve `SignatureCoordinatesModel` (e.g. in DB or cache) without requiring the project to implement it.

- **REST API / JSON**  
  Endpoint(s) to send/receive coordinates as JSON (useful for SPAs or mobile apps using the same backend).

- **More events**  
  Events such as “before form load”, “before collection validation”, or “after named config applied” for advanced integration.

- **Proxy rate limiting**  
  Option to limit requests per IP or per user to the proxy endpoint (prevent abuse).

- **Proxy PDF cache**  
  Option to cache proxy responses (by URL, configurable TTL) and reduce external calls.

---

## Frontend and PDF.js

- **Password-protected PDF support**  
  Allow passing a password (or flow to prompt for it) when the PDF is protected.

- **Text search in PDF**  
  Option to search text inside the document (highlight and optionally “go to page”).

- **Multiple views (thumbnails)**  
  Thumbnail panel for pages to jump quickly and see which page each box is on.

- **Themes (light/dark)**  
  CSS variable or option so the overlay and UI follow the application theme.

- **Bundle without proxy (CORS-friendly)**  
  Mode where the JS loads the PDF directly from a URL the app declares as allowed (without going through the proxy), documenting CORS requirements.

---

## Quality and development

- **E2E tests**  
  End-to-end tests (Playwright or similar) for the flow: load PDF, add box, submit form.

- **Form integration tests**  
  Tests that render the form type with different options and assert HTML or structure.

- **Compatibility with more Symfony/PHP versions**  
  Keep supporting new versions (Symfony 9, PHP 8.4+) and document minimum versions.

- **CI: JS/TS static analysis**  
  ESLint/TypeScript in the pipeline for the viewer code.

---

## Documentation

- **Quick start video or GIF**  
  Show the full flow in the README in 1–2 minutes.

- **Recipes / examples by use case**  
  Short examples: “fixed URL only”, “multiple signers with unique names”, “same signer multiple positions”, “predefine boxes from DB”.

- **Form type options reference**  
  Table or reference of all options for `SignatureCoordinatesType` and `SignatureBoxType` (in USAGE or a separate doc).

- **Accessibility guide**  
  Recommendations for keyboard, screen readers, and contrast in the viewer and form controls.

---

## Security and performance

- **Proxy origin allowlist**  
  Restrict which URLs the proxy can request (allowed domains or patterns).

- **Proxy PDF size limit**  
  Reject or truncate responses above a configurable size.

- **Viewer lazy load**  
  Load the PDF script only when the coordinates block is visible (intersection observer).

- **Web Worker for PDF.js**  
  Move PDF parsing to a worker so the main thread is not blocked on large documents.

---

*To propose or prioritise an improvement, open an issue or PR in the repository.*
