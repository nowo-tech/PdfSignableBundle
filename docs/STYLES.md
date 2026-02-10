# PDF viewer styles

Styles for the PDF viewer live in **`assets/pdf-signable.scss`**, scoped under `.nowo-pdf-signable-widget`. The bundle build (Vite) compiles this to `src/Resources/public/js/pdf-signable.css`. The form theme (`src/Resources/views/form/theme.html.twig`) links that CSS and loads the script; no inline `<style>` block. The signature view (`signature/index.html.twig`) may use a smaller inline block for the same viewer concepts.

**Single inclusion per request:** The form theme uses the Twig function `nowo_pdf_signable_include_assets()` (from `NowoPdfSignableTwigExtension`) so that the CSS link and the PDF.js / pdf-signable.js scripts are output only once per request. If you render multiple `SignatureCoordinatesType` widgets on the same page, the assets are not duplicated.

## Aligned with PdfTemplateBundle

The following use the **same values** as PdfTemplateBundle so both viewers look consistent. When changing these, update the other bundle’s theme as well.

| Selector / concept | Values (keep in sync) |
|--------------------|------------------------|
| Viewer container (max/min height, border, background) | `max-height: 75vh; min-height: 400px; border: 1px solid #dee2e6; border-radius: 0.375rem; background: #f8f9fa` |
| Thumb strip width, padding, background | `72px`, `0.25rem`, `#e9ecef` |
| `.pdf-thumb` | `display: block; width: 100%; margin-bottom: 0.25rem; cursor: pointer; border: 2px solid transparent; border-radius: 0.25rem` |
| `.pdf-thumb:hover` | `border-color: #0d6efd` |
| `.pdf-thumb canvas` | `width: 100%; height: auto; display: block` |
| `.pdf-zoom-toolbar` | `flex-shrink: 0; padding: 0.25rem 0.5rem; display: flex; align-items: center; gap: 0.25rem; background: #fff; border-bottom: 1px solid #dee2e6` |
| `.pdf-zoom-toolbar .pdf-zoom-value` | `min-width: 2.5rem; text-align: center` |
| `#pdf-canvas-wrapper` | `position: relative; display: block; width: 100%; padding: 0.5rem` |
| `#pdf-canvas-wrapper canvas` | `display: block; cursor: crosshair` |
| `.pdf-page-wrapper` | `position: relative; display: inline-block; margin-bottom: 10px` |
| Loading overlay | `position: absolute; inset: 0; background: rgba(248, 249, 250, 0.9); display: flex; align-items: center; justify-content: center; z-index: 100; border-radius: 0.375rem` |

## Signable-specific (not in PdfTemplate)

- `.pdf-viewer-outer`, `#pdf-viewer-container` (flex container), `#pdf-thumbnails-strip`, `.pdf-viewer-scroll` — layout with strip + scroll area; PdfTemplate uses `.pdf-viewer-layout`, `#pdf-thumbnails`, `.pdf-main`, and `#pdf-viewer-container` as the scroll area.
- `.pdf-thumb.current` — current page highlight in strip.
- Scrollbar styling (`.pdf-viewer-scroll::-webkit-scrollbar`, `#pdf-thumbnails-strip::-webkit-scrollbar`).
- `.signature-overlays`, `.signature-box-overlay` (with `--box-color`, `--box-bg`), `.resize-handle`, `.rotate-handle`, `.signature-box-overlay.selected`. Resize handles: 12×12 px (corners). Rotate handle: 16×16 px, centered above the box.
- `.pdf-grid-overlay` — when `show_grid` is true, a canvas overlay per page with grid lines (step from `grid_step`, in form unit); positioned above the PDF, below signature overlays; `pointer-events: none`.
- `.signature-box-item`, `#signature-boxes-list`, `.signature-pad-wrapper`, `.signature-pad-canvas`.

## Other views

- `signature/index.html.twig` (and demo copies): minimal viewer styles for the signing page; same `#pdf-viewer-container`, `#pdf-canvas-wrapper`, `.pdf-page-wrapper` intent.
