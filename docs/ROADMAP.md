# Roadmap — Possible improvements

This document lists **possible improvements** and ideas for future versions of the bundle. They are not commitments or a fixed plan; they serve as a reference for contributions and prioritisation.

---

## Form and coordinates functionality

- **Default values per box name** *(implemented)*  
  Form option `box_defaults_by_name`: map of box name to default `width`, `height`, `x`, `y`, `angle`. When the user selects a name (dropdown or input), the frontend fills in those fields. See [USAGE](USAGE.md).

- **Page restriction** *(implemented)*  
  Option to limit which pages boxes can be placed on (e.g. page 1 only, or range 1–3) via `allowed_pages` or `page_choices`. Implemented as `allowed_pages` (form option and `SignatureBoxType`); see [USAGE](USAGE.md).

- **Box order** *(implemented)*  
  Option to sort the collection by page and then by position (Y/X) when serialising or displaying in the overlay. Implemented as `sort_boxes` (form option; sorts on submit by page, then Y, then X); see [USAGE](USAGE.md).

- **Rotate coordinates** *(implemented)*  
  Each signature box has an `angle` (degrees). Model and form store it; the viewer overlay uses CSS `transform: rotate(angle deg)`. Rotation is **optional**: set `enable_rotation: true` on `SignatureCoordinatesType` to show the angle field and a rotate handle in the viewer; when `false` (default), the angle field is omitted and boxes are not rotatable. See [USAGE](USAGE.md).

- **Export/import coordinates** *(implemented)*  
  `SignatureCoordinatesModel::toArray()` and `::fromArray(array)`; `SignatureBoxModel::toArray()` and `::fromArray(array)`. Use for JSON/YAML export or import. See [USAGE](USAGE.md#export--import-coordinates).

- **Customisable constraints** *(implemented)*  
  Form options `collection_constraints` (array of constraints on the boxes collection) and `box_constraints` (array of constraints on each `SignatureBoxModel`). **Non-overlapping boxes** is built-in as `prevent_box_overlap` (default `true`); see [USAGE](USAGE.md).

---

## User experience (PDF viewer)

- **Keyboard shortcuts** *(implemented)*  
  **Ctrl+Shift+A** Add box (centred on page 1), **Ctrl+Z** Undo last box, **Delete** / **Backspace** Delete selected box. Click an overlay to select it; click on the canvas to clear selection. See [USAGE](USAGE.md). Zoom in/out is not yet implemented.

- **Guides and grid**  
  Option to show guides or grid on the canvas (e.g. every 10 mm) to align boxes.

- **Snap to grid / snap between boxes** *(implemented)*  
  Form options `snap_to_grid` (grid step in form unit, 0 = off) and `snap_to_boxes` (default true). When dragging, position/size snap to the grid and box edges snap to other boxes’ edges within ~10 px. See [USAGE](USAGE.md).

- **Print preview**  
  Option to see how boxes would look on a rendered PDF (no interaction).

- **Touch support** *(implemented)*  
  Pinch to zoom and two-finger drag to pan the PDF viewer on touch devices. The viewer is wrapped in a transform layer; mouse and touch work together.

- **PDF loading indicator** *(implemented)*  
  Full-area overlay with spinner and "Loading..." text in the viewer container while the PDF or proxy is loading. Accessible (aria-live, aria-busy).

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

Key idea: **Multiple views (thumbnails)** — thumbnail panel for pages to jump quickly and see which page each box is on.

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

- **Proxy origin allowlist** *(implemented)*  
  Restrict which URLs the proxy can request (allowed domains or patterns). Implemented as `proxy_url_allowlist` (bundle config: substring or regex patterns); see [CONFIGURATION](CONFIGURATION.md).

- **Proxy PDF size limit**  
  Reject or truncate responses above a configurable size.

- **Viewer lazy load**  
  Load the PDF script only when the coordinates block is visible (intersection observer).

- **Web Worker for PDF.js**  
  Move PDF parsing to a worker so the main thread is not blocked on large documents.

---

*To propose or prioritise an improvement, open an issue or PR in the repository.*
