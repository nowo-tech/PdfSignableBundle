# Roadmap — Possible improvements

This document lists **possible improvements** and ideas for future versions of the bundle. They are not commitments or a fixed plan; they serve as a reference for contributions and prioritisation.

---

## PDF signing and legal validity

The bundle today focuses on **defining where** signature boxes go (coordinates). The ideas below extend toward **actually signing** inside those boxes, with different levels of legal validity.

- **Simple in-box signature (draw / finger)** *(implemented)*  
  Draw pad per box; image stored in `SignatureBoxModel::signatureData`. Implemented via `enable_signature_capture: true`. See [USAGE](USAGE.md#signing-in-boxes-draw-or-image).

- **Pre-made signature image** *(implemented)*  
  Upload image per box via `enable_signature_upload: true`. See [USAGE](USAGE.md#signing-in-boxes-draw-or-image).

- **Digital signature (PKI / PAdES)** *(structure in bundle; you add keys & service)*  
  The bundle provides **`PdfSignRequestEvent`** and config placeholder **`signing_service_id`**. Your app adds a signing library or HSM/signing service and subscribes to the event (or `SIGNATURE_COORDINATES_SUBMITTED`) to produce PAdES-BES/EPES in the defined boxes. See [SIGNING_ADVANCED](SIGNING_ADVANCED.md).

- **Qualified / eIDAS-style signatures** *(same as PKI + TSA/LTV in your app)*  
  Use the same events and audit metadata; your app integrates a qualified TSP (certificates, TSA, LTV). No TSP code in the bundle. See [SIGNING_ADVANCED](SIGNING_ADVANCED.md).

- **Timestamp and audit trail** *(implemented)*  
  **Audit:** `SignatureCoordinatesModel::audit_metadata`, **`AuditMetadata`** constants (e.g. `tsa_token`, `ip`, `user_id`). **Config `audit.fill_from_request`** (default true) fills `submitted_at`, `ip`, `user_agent` before dispatch. **Config `tsa_url`** is a placeholder: your listener calls your TSA and sets the token in audit. See [SIGNING_ADVANCED](SIGNING_ADVANCED.md) and [CONFIGURATION](CONFIGURATION.md).

- **One-click / batch signing** *(implemented)*  
  **Form option `batch_sign_enabled`** shows a **Sign all** button; submit with `batch_sign=1` dispatches **`BATCH_SIGN_REQUESTED`**. Your listener performs the actual batch sign (draw/upload or PKI). See [SIGNING_ADVANCED](SIGNING_ADVANCED.md) and [EVENTS](EVENTS.md).

- **Legal disclaimer in UI** *(implemented)*  
  Via `signing_legal_disclaimer` and `signing_legal_disclaimer_url`. See [USAGE](USAGE.md#legal-disclaimer).

*The bundle provides events and structures for PKI, timestamp and batch; the actual TSA/PKI calls and PDF signing require your backend integration (signing service, HSM or library). See [SIGNING_ADVANCED](SIGNING_ADVANCED.md).*

---

## AcroForm (PDF form fields editor)

- **Field list and overrides** *(implemented)*  
  Panel with list of AcroForm fields (from PDF or extractor), Load/Save/Clear overrides, and draft in memory. Overrides (defaultValue, label, controlType, rect, hidden, font, checkbox) are persisted via backend (e.g. session or DB). See [ACROFORM](ACROFORM.md) and [ACROFORM_BACKEND_EXTENSION](ACROFORM_BACKEND_EXTENSION.md).

- **Edit field modal** *(implemented)*  
  Modal to edit type, label, default value, position/size (rect), options (select), font (size, family, auto-size), checkbox (on/off value, icon). Config: label_mode (free text or choice list), show_field_rect, font_sizes, font_families. See [ACROFORM](ACROFORM.md#5-modal-and-editor-configuration).

- **Move/resize on PDF** *(implemented)*  
  Per-field cross button opens an overlay on the PDF to drag or resize; rect is written to overrides. Single active context (overlay or modal). See [ACROFORM_FLOWS](ACROFORM_FLOWS.md).

- **Add new field** *(implemented)*  
  Click on empty area of the PDF creates override with id `new-*` and opens edit modal. Fields exist in overrides until Apply writes them to the PDF.

- **Apply to PDF (Layer 2)** *(implemented)*  
  Endpoint accepts PDF + patches; bundle can run Python script or dispatch `AcroFormApplyRequestEvent` for custom implementation (e.g. SetaPDF). Returns modified PDF. See [ACROFORM_BACKEND_EXTENSION](ACROFORM_BACKEND_EXTENSION.md).

- **Process modified PDF** *(implemented)*  
  Endpoint to send last applied PDF to a script or listener (`AcroFormModifiedPdfProcessedEvent`) for fill/sign or storage.

- **Extensibility of the editor** *(proposal)*  
  Form type for edit-field modal, Twig templates overridable, Symfony translations. See [PROPOSAL_ACROFORM_EDITOR_EXTENSIBILITY](PROPOSAL_ACROFORM_EDITOR_EXTENSIBILITY.md).

- **AcroForm E2E or integration tests**  
  Automated tests for load overrides → edit field → save → apply flow (browser or API).

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
  **Ctrl+Shift+A** Add box (centred on page 1), **Ctrl+Z** Undo last box, **Delete** / **Backspace** Delete selected box. Click an overlay to select it; click on the canvas to clear selection. See [USAGE](USAGE.md).

- **Zoom toolbar** *(implemented)*  
  Zoom out (−), zoom in (+), fit width (translated). PDF loads at fit-to-width; range 0.5×–3×. See [USAGE](USAGE.md).

- **Guides and grid** *(implemented)*  
  Form options `show_grid` (bool) and `grid_step` (in form unit, e.g. 10 mm). A grid overlay is drawn on the PDF so boxes can be aligned. Demo: `/demo-signature/guides-and-grid`.

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

- **Multiple views (thumbnails)** *(implemented)*  
  Thumbnail strip on the left of the viewer: one thumbnail per page, click to scroll to that page. The current page is highlighted. See [USAGE](USAGE.md).

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

- **Unified AcroForm docs** *(implemented)*  
  [ACROFORM](ACROFORM.md) (unified guide), [ACROFORM_FLOWS](ACROFORM_FLOWS.md) (diagrams and sequences), [ACROFORM_BACKEND_EXTENSION](ACROFORM_BACKEND_EXTENSION.md) (backend). Index in [docs/README](README.md).

- **Quick start video or GIF**  
  Show the full flow in the README in 1–2 minutes.

- **Recipes / examples by use case**  
  Short examples: “fixed URL only”, “multiple signers with unique names”, “same signer multiple positions”, “predefine boxes from DB”, “AcroForm editor with custom storage”.

- **Form type options reference**  
  Table or reference of all options for `SignatureCoordinatesType`, `SignatureBoxType`, and AcroForm types (in USAGE or a separate doc).

- **Accessibility guide** *(implemented)*  
  [ACCESSIBILITY](ACCESSIBILITY.md): keyboard shortcuts, focus order, screen readers (ARIA, loading overlay), contrast and visibility recommendations for the viewer and form controls.

---

## Security and performance

- **Proxy origin allowlist** *(implemented)*  
  Restrict which URLs the proxy can request (allowed domains or patterns). Implemented as `proxy_url_allowlist` (bundle config: substring or regex patterns); see [CONFIGURATION](CONFIGURATION.md).

- **Proxy PDF size limit**  
  Reject or truncate responses above a configurable size.

- **Viewer lazy load** *(implemented)*  
  Form option `viewer_lazy_load: true` defers loading of PDF.js and pdf-signable.js until the widget enters the viewport (IntersectionObserver with rootMargin). Demo: `/demo-signature/lazy-load`.

- **Web Worker for PDF.js**  
  Move PDF parsing to a worker so the main thread is not blocked on large documents.

---

*To propose or prioritise an improvement, open an issue or PR in the repository.*
