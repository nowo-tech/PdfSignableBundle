# PdfSignable TypeScript layout

Three logical areas so each form type (signable vs acroform) can depend only on what it needs:

## `shared/`

Code used by **both** the signable viewer (signature boxes) and the acroform editor:

- **types** – `NowoPdfSignableConfig`, `PDFViewport`, `PDFDocumentProxy`, `IPdfDocForScale`
- **constants** – `SCALE_GUTTER`
- **url-and-scale** – `getLoadUrl`, `getScaleForFitWidth`, `getScaleForFitPage`
- **zoom-toolbar** – `bindZoomToolbar`
- **pdfjs-loader** – `getPdfJsLib`, `getWorkerUrl`
- **index** – re-exports the above

## `signable-editor/`

Code used only by the **signature coordinates** flow (PDF + signature boxes):

- **types** – `BoxBounds` and re-exports of shared PDF types
- **box-drag**, **box-overlays**, **coordinates**, **grid**, **signature-pad**, **utils**, **constants**, **font-auto-size**, **touch**, **thumbnails**
- **index** – re-exports the above

Entry point: `signable-editor.ts` (imports from `shared/` and `signable-editor/`). Build output: `pdf-signable.js`.

## `acroform-editor/`

Code used only by the **AcroForm editor** flow (PDF + AcroForm fields and overrides):

- **config** – `getConfig`, `parseLabelChoices`, `parseFontSizes`, `parseFontFamilies`, `LABEL_VALUE_OTHER`
- **strings** – `DEFAULT_STRINGS`, `escapeAttr`
- **acroform-move-resize** – `createAcroformMoveResize`, `viewportPixelsToPdfRect`
- **index** – re-exports the above

Entry points: `signable-editor.ts` (viewer with AcroForm overlays) imports `createAcroformMoveResize` from `acroform-editor/`; `acroform-editor.ts` (panel) imports config and strings from `acroform-editor/`.

## Build

- `pnpm run build` → `pdf-signable.js` (signable viewer + shared + acroform move/resize in viewer)
- `VITE_ENTRY=acroform-editor pnpm run build` → `acroform-editor.js` (panel only)

The Vite config uses `signable-editor.ts` as the source entry for the `pdf-signable` output so the built filename stays `pdf-signable.js` (referenced by Twig, theme, CI and docs).
