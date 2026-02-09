# Accessibility guide

This document gives recommendations for making the PdfSignable viewer and form usable with keyboard, screen readers, and sufficient contrast.

---

## Keyboard

### Built-in shortcuts

When focus is **not** in an input, select or textarea:

| Shortcut | Action |
|----------|--------|
| **Ctrl+Shift+A** | Add a signature box (centred on page 1) |
| **Ctrl+Z** | Undo last box (remove the most recently added box) |
| **Delete** / **Backspace** | Delete the currently selected box |

To select a box: click its overlay on the PDF. To clear selection: click on the canvas (empty area). The selected box has a visible highlight (outline).

### Focus order

- The default tab order follows the form: URL field → Load button → unit/origin selectors → signature boxes list (name, page, dimensions, Delete) → Add box → Submit.
- After loading a PDF, focus is not moved into the viewer. Users can tab to the next form field or use the shortcuts above without focusing the viewer.

### Recommendations

- **Skip link**: If your page has a lot of content before the form, consider a “Skip to signature form” link that moves focus to the PDF viewer card or the first form field.
- **Focus visible**: Ensure your CSS does not remove the default focus outline on buttons and inputs (e.g. use `:focus-visible` with a clear outline instead of `outline: none` without a replacement).
- **Load PDF button**: The bundle does not set an `aria-label` on the Load PDF button; the label comes from translation (`page.load`). For a more descriptive label (e.g. for screen readers), you can override the button in your form theme and add `aria-label="Load PDF document into viewer"` or similar.

---

## Screen readers

### What the bundle provides

- **Loading overlay**: While the PDF is loading, an overlay is shown with `aria-live="polite"` and `aria-busy="true"`, and the spinner has `role="status"` with a visually hidden “Loading...” text so screen readers announce the state.
- **Form labels**: Units, origin, and signature box fields use proper `<label>` or visible labels so that controls are associated with their purpose.
- **Validation errors**: Form errors are rendered in the DOM and associated with fields where possible (e.g. `form_errors` next to inputs), so they can be announced.
- **Alerts**: Critical messages (e.g. “Please enter a PDF URL”) are shown via `alert()` for immediate feedback; this is audible but not ideal for all users. Consider replacing with an in-page `role="alert"` region in a custom build if you need to avoid modal dialogs.

### PDF viewer region

- The viewer container does not have an explicit `role` or `aria-label`. You can wrap it or add an `aria-label` in your theme, for example:
  - `aria-label="PDF document viewer for placing signature areas"`.
- The **zoom toolbar** has `role="toolbar"` and each button (zoom out, zoom in, fit width) has an `aria-label` from the translation catalogue (`js.zoom_out`, `js.zoom_in`, `js.zoom_fit`).
- The canvas and overlays are not exposed as a structured “document” to assistive tech; the primary way to work with boxes is via the **form list** (name, page, X, Y, width, height). Screen reader users can edit positions in the list and hear updates.

### Recommendations

- **Page title**: Set a descriptive page title (e.g. “Define signature coordinates”) so screen reader users know the purpose of the page.
- **Instructions**: The “help text” under the form (e.g. “Click on the PDF to place a new signature box…”) is visible and can be read; keep it concise and consider an `aria-describedby` link from the viewer to this text if you add an ID.
- **Box list**: Each signature box row has a Delete button; ensure “Delete” is clear in context (e.g. “Delete box for Signer 1” can be achieved with an `aria-label` on the button that includes the box name if you extend the theme).

---

## Contrast and visibility

### Overlays and controls

- **Signature box overlays**: Each overlay uses a border and semi-transparent background derived from a hue (see `getColorForBoxIndex`). The default colors are chosen to be distinguishable and readable on a light PDF background. On dark or colored PDFs, contrast may drop.
- **Overlay label**: The box name (e.g. `signer_1`) is shown as text on the overlay in a small font. Ensure the text color contrasts with the overlay background (the bundle uses the same hue for border and text).
- **Resize and rotate handles**: Small squares/circles on the overlay edges; they use the same hue with a white border. In low light or for users with low vision, consider increasing handle size or contrast in a custom CSS override.

### Form controls

- The bundle relies on your application’s form theme (e.g. Bootstrap). Use form controls and labels that meet **WCAG 2.1 Level AA** contrast (at least 4.5:1 for normal text, 3:1 for large text and UI components).
- **Error state**: Invalid fields use the theme’s “invalid” class (e.g. red border). Ensure error text and borders have sufficient contrast against the background.

### Recommendations

- **High-contrast mode**: If your app supports a high-contrast or “accessible” theme, use it; the viewer and form will inherit your colors.
- **Overlay border**: To increase visibility of boxes, you can override in CSS, for example:
  - `.nowo-pdf-signable-widget .signature-box-overlay { border-width: 3px; }`
- **Focus outline**: Keep a visible focus ring on the Load PDF button, Add box button, and Submit button (and do not rely only on color change).

---

## Touch and pointer

- **Touch**: Pinch to zoom and two-finger pan are supported. There is no separate “touch mode”; the same controls work with mouse and touch.
- **Target size**: Buttons (Load PDF, Add box, Delete, Submit) should be large enough for touch (e.g. at least 44×44 px). The bundle’s default button sizes depend on your theme; increase padding or font size if needed.

---

## See also

- [USAGE.md](USAGE.md) — Viewer interaction and keyboard shortcuts.
- [WCAG 2.1](https://www.w3.org/WAI/WCAG21/quickref/) — Guidelines for accessible content.
