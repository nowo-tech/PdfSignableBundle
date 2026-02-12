# Workflow

This document describes the main flows of the PdfSignable bundle: how the page is built, how the viewer and form stay in sync, and what happens on submit.

## High-level architecture

```mermaid
flowchart LR
  subgraph Server["Symfony (server)"]
    Config[nowo_pdf_signable.yaml]
    Controller[Controller]
    FormType[SignatureCoordinatesType]
    Event[Events]
    Config --> FormType
    Controller --> FormType
    FormType --> Event
  end

  subgraph Browser["Browser"]
    HTML[HTML + NowoPdfSignableConfig]
    JS[pdf-signable.js]
    PDF[PDF.js]
    Form[Form inputs]
    Overlays[Overlays on canvas]
    HTML --> JS
    JS --> Form
    JS --> Overlays
    JS --> PDF
    Form <-.->|sync| Overlays
  end

  Server -->|render form| HTML
  Browser -->|POST submit| Server
```

- **Server:** configuration and form type produce the widget HTML and inject options (proxy URL, strings, debug). The form type adds `data-pdf-signable` attributes so the script can find elements.
- **Browser:** the script reads the config, finds the widget and list by `data-pdf-signable`, loads the PDF (via URL or proxy), draws overlays from form values, and keeps form and overlays in sync on add/drag/resize.

---

## Page load and script init

```mermaid
sequenceDiagram
  participant User
  participant Server
  participant Twig
  participant HTML
  participant JS

  User->>Server: Request page
  Server->>Twig: Render template with form
  Twig->>Twig: signature_coordinates_widget block
  Note over Twig: theme.html.twig widget root viewer boxes list prototype data-pdf-signable
  Twig->>HTML: HTML and script tag with NowoPdfSignableConfig
  Server->>User: Response HTML
  User->>HTML: Parse and load PDF.js and pdf-signable.js
  JS->>JS: run()
  JS->>HTML: querySelector widget
  JS->>HTML: querySelector boxes-list and form fields by data-pdf-signable
  JS->>JS: Bind loadPdfBtn and canvas click and overlay mousedown and unit or origin change and remove-box and submit
  JS->>JS: updateOverlays if existing boxes or auto-load PDF
```

1. The form theme outputs the widget (viewer + list) and injects `NowoPdfSignableConfig`.
2. When the script runs, it finds the widget and list by `data-pdf-signable`, reads unit/origin from the form, and binds events.
3. If the form already has boxes (e.g. edit mode), it builds overlays from the current input values.

---

## Load PDF

```mermaid
sequenceDiagram
  participant User
  participant JS
  participant Form
  participant Proxy
  participant PDFjs

  User->>JS: Click Load PDF or auto-load
  JS->>Form: Read URL from pdf-url field
  JS->>JS: getLoadUrl for same-origin or proxy
  alt Cross-origin URL
    JS->>Proxy: GET proxy URL
    Proxy->>PDFjs: Fetch external PDF
    PDFjs->>Proxy: Binary PDF
    Proxy->>JS: Binary PDF
  else Same-origin
    JS->>PDFjs: getDocument(url)
  end
  PDFjs->>JS: PDFDocumentProxy
  JS->>JS: renderPdfAtScale and store pageViewports
  JS->>JS: updateOverlays read form boxes and draw overlays
  JS->>User: PDF visible and overlays on correct pages
```

- The script gets the URL from the form. If it is cross-origin, it uses the bundle proxy to avoid CORS.
- After the PDF is loaded, it renders pages and then rebuilds overlays from the form (page, x, y, width, height in the selected unit/origin).

---

## Add signature box (click on canvas)

```mermaid
sequenceDiagram
  participant User
  participant Canvas
  participant JS
  participant Form

  User->>Canvas: Click on PDF page
  JS->>JS: Ignore if click on overlay
  JS->>Canvas: Get pdf-page-wrapper and canvas
  JS->>JS: Get pageNum and viewport from canvas
  JS->>JS: convertToPdfPoint to get xPdf yPdf bottom-left
  JS->>JS: addSignatureBox with pageNum and PDF coords
  JS->>Form: Get prototype and replace __name__ with index
  JS->>Form: Parse HTML and get div firstElementChild box-item
  JS->>Form: Set page and x y width height from viewportToForm and ptToUnit
  JS->>Form: Append div to boxesList
  JS->>JS: updateOverlays redraw all overlays from form
  User->>User: New box row and overlay visible
```

- The click is interpreted as the **bottom-left** of the new box in PDF space. The script converts that to form coordinates (using the current unit and origin), fills the prototype row, appends it, and refreshes overlays.
- The link between overlay and form row is the **index**: overlay `data-box-index="i"` corresponds to the i-th element with `data-pdf-signable="box-item"` in the list.

---

## Drag or resize overlay

```mermaid
sequenceDiagram
  participant User
  participant Overlay
  participant JS
  participant Form

  User->>Overlay: Mousedown on overlay or resize handle
  JS->>JS: Get boxIndex from overlay and item from boxesList
  JS->>JS: Set dragState overlay item boxIndex viewport start position
  loop Mousemove
    User->>JS: Mousemove
    JS->>JS: newLeft newTop newW newH with optional snap
    JS->>JS: viewportToForm to get xPt yPt
    JS->>JS: ptToUnit for x and y
    JS->>Form: Set x y width height inputs from dragState.item
    JS->>Overlay: Set overlay style left top width height
  end
  User->>JS: Mouseup
  JS->>JS: If overlap revert inputs from dragState and alert
  JS->>JS: updateOverlays()
```

- The script identifies the form row from the overlay’s `data-box-index` and keeps a reference to that DOM node (`dragState.item`).
- On each mousemove it converts the new viewport position/size to form coordinates (viewportToForm + ptToUnit) and writes to the inputs found by `data-pdf-signable="x"`, `"y"`, `"width"`, `"height"`.
- On mouseup it can revert if overlap is not allowed; then it rebuilds overlays so they match the (possibly reverted) form.

---

## Coordinate sync (form ↔ overlay)

The form stores **x, y, width, height** in the **selected unit** (mm, cm, pt, etc.) and with the **selected origin** (e.g. bottom_left = bottom-left corner of the box). The canvas and PDF.js use **viewport pixels** and **PDF points** (bottom-left origin). The script converts both ways:

```mermaid
flowchart LR
  subgraph Form["Form (user-facing)"]
    Unit[Unit selector]
    Origin[Origin selector]
    XYWH[x, y, width, height inputs]
  end

  subgraph JS["Script"]
    unitToPt[unitToPt]
    ptToUnit[ptToUnit]
    formToViewport[formToViewport]
    viewportToForm[viewportToForm]
  end

  subgraph Canvas["Canvas"]
    Overlay[Overlay position/size in px]
  end

  XYWH -->|read| unitToPt
  Unit --> unitToPt
  unitToPt -->|points + origin| formToViewport
  Origin --> formToViewport
  formToViewport --> Overlay

  Overlay -->|on drag/resize| viewportToForm
  Origin --> viewportToForm
  viewportToForm -->|points| ptToUnit
  Unit --> ptToUnit
  ptToUnit -->|write| XYWH
```

- **Form → overlay:** read x, y, width, height (in unit); convert to points (`unitToPt`); convert to viewport position with `formToViewport(viewport, xPt, yPt, wPt, hPt, origin)`; set overlay `left`, `top`, `width`, `height` in pixels.
- **Overlay → form:** on drag/resize, new `left`, `top`, `width`, `height` in pixels; convert to PDF points and then to form coordinates with `viewportToForm`; convert points to unit with `ptToUnit`; write back to the inputs.

---

## Form submit

```mermaid
sequenceDiagram
  participant User
  participant JS
  participant Form
  participant Server
  participant Event

  User->>Form: Click "Save coordinates" / Submit
  Form->>JS: submit event
  JS->>JS: Reindex collection sort boxes and rename indices consecutive
  JS->>Form: Submit form POST
  Form->>Server: POST with signature_coordinates signatureBoxes
  Server->>Server: SignatureCoordinatesType: bind request to SignatureCoordinatesModel
  Server->>Server: Validate NotBlank allowed_pages
  alt Valid
    Server->>Event: Dispatch SIGNATURE_COORDINATES_SUBMITTED
    Event->>Event: Listeners persist sign audit
    Server->>User: Redirect / response
  else Invalid
    Server->>User: Re-render form with errors
  end
```

- Before submit, the script **reindexes** the collection (e.g. by page, then y, then x) so the server receives `[0]`, `[1]`, … without gaps.
- The server validates and, if valid, dispatches `SignatureCoordinatesSubmittedEvent` so you can persist, call a signing service, or add audit data. See [EVENTS](EVENTS.md).

---

## References

- [USAGE](USAGE.md) — Form type, options, overriding templates, data attributes.
- [CONFIGURATION](CONFIGURATION.md) — `nowo_pdf_signable` options.
- [EVENTS](EVENTS.md) — Event list and listener examples.
