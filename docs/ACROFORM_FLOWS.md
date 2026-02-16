# AcroForm: flow diagrams and behaviour

This document describes **unambiguously** how the AcroForm editor works: architecture, data flows, state and events between the editor panel, the PDF viewer and the backend. It includes Mermaid diagrams for each flow.

---

## 1. General architecture

The AcroForm editor is made of **three parts** that communicate via events and global variables in the browser, and via HTTP with the server.

```mermaid
flowchart TB
  subgraph Browser["Browser"]
    subgraph Editor["AcroForm panel acroform-editor"]
      List[Field list]
      Modal[Edit field modal]
      Buttons[Load / Save / Clear / Apply / Process]
      Draft[draftOverrides]
    end

    subgraph Viewer["PDF viewer signable-editor"]
      Canvas[PDF canvas + AcroForm layer]
      Outlines[Field outlines]
      Overlay[Move/resize overlay]
    end

    Win["window.__pdfSignable*"]
    Editor -->|events + Win| Viewer
    Viewer -->|events| Editor
  end

  subgraph Server["Symfony server"]
    Load[GET/POST load overrides]
    Save[POST save overrides]
    Apply[POST apply PDF]
    Process[POST process PDF]
  end

  Editor -->|fetch| Load
  Editor -->|fetch| Save
  Editor -->|fetch| Apply
  Editor -->|fetch| Process
```

- **Editor panel:** field list, edit modal, Load/Save/Clear/Apply/Process buttons, and the override draft in memory (`draftOverrides`).
- **Viewer:** renders the PDF and the AcroForm layer (outlines and inputs); shows the move/resize overlay when applicable.
- **In-browser communication:** custom events (`pdf-signable-acroform-*`) and variables on `window` (`__pdfSignableAcroFormOverrides`, `__pdfSignableAcroFormMoveResizeFieldId`, etc.). See [Frontend events](#9-frontend-events-editor--viewer).

---

## 2. Load overrides

The user enters **document_key** and has the PDF loaded. When they click **Load fields + config**, the server is asked for stored overrides for that document and the draft and list are updated.

```mermaid
sequenceDiagram
  participant U as User
  participant E as Editor acroform-editor
  participant S as Server load_url
  participant V as Viewer

  U->>E: Click Load fields + config
  E->>E: Validate document_key and pdf_url
  E->>S: POST load_url
  S->>E: fields and overrides
  E->>E: draftOverrides merge overrides
  E->>E: syncDraftToTextarea and renderFieldsList
  E->>E: notifyViewerOverrides
  E->>V: dispatch overrides-updated
  V->>V: renderPdfAtScale with overrides
  Note over V: If overlay visible it is restored
  E->>U: Message Draft loaded or No data on server
```

- **load_url** is typically the endpoint that returns overrides by `document_key` (and optionally the extractor field list).
- The field list is ordered **by page** and, within each page, top to bottom.

---

## 3. Save overrides

The current draft is sent to the server to persist it (session, DB, etc.). The viewer is already using that draft; save only persists.

```mermaid
sequenceDiagram
  participant U as User
  participant E as Editor
  participant S as Server post_url

  U->>E: Click Save overrides
  E->>E: Validate document_key + syncTextareaToDraft + mergeInputsIntoDraft
  E->>E: buildFullOverrides
  E->>S: POST post_url with document_key overrides fields
  S->>E: 200 OK
  E->>E: notifyViewerOverrides
  E->>U: Message Draft saved
```

---

## 4. Move / resize a field

**Rule:** you can only enter move/resize mode from the **cross** button for that field in the list. The overlay does not open when clicking the field outline on the PDF.

- The field stays **highlighted** (red outline on the PDF and **red row in the list**) until you choose another field (another cross) or click **Edit**.
- After releasing the drag, the overlay **does not close**: the PDF is re-rendered with the new `rect` and the **overlay is shown again** on the same field (so it stays linked).

```mermaid
flowchart LR
  subgraph Enter["How to enter"]
    A[Click cross on field row]
    A --> B[Editor closeEditModal set flag dispatch move-resize]
    B --> C[Viewer showOverlay]
    C --> D[Red outline and red list row]
  end

  subgraph During["During"]
    D --> E[Drag to move or resize]
    E --> F[Release]
    F --> G[onRectChanged editor updates overrides]
    G --> H[notifyViewerOverrides viewer re-renders]
    H --> I[Viewer restores overlay same field]
    I --> D
  end

  subgraph Exit["How to exit"]
    D --> J[Click cross of OTHER field]
    D --> K[Click Edit]
    J --> L[showOverlay other field and red row on other]
    K --> M[clearMoveResizeState hideOverlay open modal]
  end
```

```mermaid
sequenceDiagram
  participant U as User
  participant E as Editor
  participant V as Viewer

  U->>E: Click field cross
  E->>E: closeEditModal and activate move-resize
  E->>E: highlightFieldOnPdf and highlightListRow
  E->>V: dispatch move-resize
  V->>V: showOverlay
  V->>E: dispatch move-resize-opened
  E->>E: closeEditModal

  Note over U,V: User drags and releases
  V->>V: onRectChanged
  V->>E: dispatch rect-changed
  E->>E: update rect and notifyViewerOverrides
  E->>V: dispatch overrides-updated
  V->>V: renderPdfAtScale and showOverlay
```

---

## 5. Edit field configuration (modal)

Only **one** of the two contexts can be active: **edit modal** or **move/resize overlay**. Opening one closes the other.

```mermaid
flowchart LR
  subgraph Open_modal["Open modal"]
    M1[Click Edit on row]
    M2[Click row with overlay inactive]
    M1 --> MC[Editor clearMoveResizeState and highlightListRow]
    M2 --> MC
    MC --> MD[dispatch move-resize-close and openEditModal]
    MD --> Modal[Modal visible]
  end

  subgraph Open_overlay["Open overlay"]
    O1[Click cross on row]
    O1 --> OC[Editor closeEditModal set flag dispatch move-resize]
    OC --> OD[Viewer showOverlay]
    OD --> Overlay[Overlay visible]
  end

  Modal -.->|on cross click| Open_overlay
  Overlay -.->|on Edit click| Open_modal
```

- **Click on row:** opens the modal **only if** there is no active move/resize overlay (`__pdfSignableAcroFormMoveResizeFieldId` undefined).
- **Click Edit:** closes the overlay (if any), clears move/resize state and opens the modal.

---

## 6. Add a new field

The user clicks on an **empty area** of the PDF (not on a field). An override is created with id `new-<timestamp>`, default name by **pattern** (e.g. "New field 1", "New field 2") and the edit modal opens for that field.

```mermaid
sequenceDiagram
  participant U as User
  participant V as Viewer
  participant E as Editor

  U->>V: Click empty area of PDF
  V->>V: Get page llx lly width height
  V->>E: dispatch add-field-place page llx lly width height
  E->>E: newId new-timestamp
  E->>E: defaultFieldName with pattern
  E->>E: draftOverrides newId with page rect controlType
  E->>E: notifyViewerOverrides and renderFieldsList
  E->>E: openEditModal if no overlay
  E->>U: Modal open
```

- The default name is **translatable** (`new_field_name_pattern` with `%n`).
- In the modal the user can change the name and other properties.

---

## 7. Apply changes to PDF (Apply)

The frontend fetches the PDF (by URL), builds the **patches** list from `draftOverrides` and sends it to the server. The server (or Python script) returns the modified PDF. Fields with id `new-*` are sent with `createIfMissing: true` so the script creates them in the PDF.

```mermaid
sequenceDiagram
  participant U as User
  participant E as Editor
  participant Net as Network
  participant S as Server apply_url
  participant Py as Python script

  U->>E: Click Apply to PDF
  E->>E: mergeInputsIntoDraft and buildPatchesFromOverrides
  E->>Net: GET pdf_url
  Net->>E: ArrayBuffer PDF
  E->>E: arrayBufferToBase64 and body with pdf_content patches
  E->>S: POST apply_url
  S->>Py: Run script PDF and patches
  Py->>S: Modified PDF
  S->>E: 200 PDF binary
  E->>E: lastAppliedPdf buffer and offer download
  E->>U: Message PDF modified received and download
```

---

## 8. Send / process PDF (Process)

The **last applied PDF** (`lastAppliedPdf`) is sent to the process endpoint (e.g. a script that fills or signs). The backend consumes the result (event `AcroFormModifiedPdfProcessedEvent`).

```mermaid
sequenceDiagram
  participant U as User
  participant E as Editor
  participant S as Server process_url

  U->>E: Click Send Process
  E->>E: Check lastAppliedPdf not empty
  E->>S: POST process_url with pdf_content document_key
  S->>S: Run process_script
  S->>S: Dispatch AcroFormModifiedPdfProcessedEvent
  S->>E: 200 success
  E->>U: Success or error message
```

---

## 9. Frontend events (editor ↔ viewer)

All are **CustomEvent** on `window`. The editor and viewer coordinate only via these events and the global variables below.

| Event | Source | Target | Effect |
|-------|--------|--------|--------|
| `pdf-signable-acroform-move-resize` | Editor | Viewer | Shows move/resize overlay for the given field. |
| `pdf-signable-acroform-move-resize-close` | Editor | Viewer | Removes the overlay and clears `__pdfSignableAcroFormMoveResizeFieldId` and `__pdfSignableAcroFormMoveResizePage`. |
| `pdf-signable-acroform-move-resize-opened` | Viewer (move-resize) | Editor | Closes the edit modal (single active context). |
| `pdf-signable-acroform-overrides-updated` | Editor | Viewer | Re-renders the PDF with current overrides; restores overlay if one was active. |
| `pdf-signable-acroform-rect-changed` | Viewer | Editor | Updates `draftOverrides[fieldId].rect` and re-renders list; does not clear move/resize flag. |
| `pdf-signable-acroform-add-field-place` | Viewer | Editor | Creates override for new field and opens modal. |
| `pdf-signable-acroform-field-focused` | Viewer | Editor | Highlights outline and row when focusing an input on the PDF. |
| `pdf-signable-acroform-fields-updated` | Viewer | Editor | PDF field list updated; editor can Load or refresh list. |
| `pdf-signable-acroform-edit-mode` | Editor | Viewer | Toggles `acroform-edit-mode` class on the widget (currently edit mode is always on when the editor is present). |

```mermaid
flowchart LR
  subgraph Editor["Editor"]
    E1[dispatch move-resize]
    E2[dispatch move-resize-close]
    E3[dispatch overrides-updated]
    E4[listen rect-changed]
    E5[listen move-resize-opened]
    E6[listen add-field-place]
  end

  subgraph Viewer["Viewer"]
    V1[showOverlay / hideOverlay]
    V2[renderPdfAtScale + restore overlay]
    V3[onRectChanged dispatch rect-changed]
    V4[dispatch move-resize-opened]
    V5[dispatch add-field-place]
  end

  E1 --> V1
  E2 --> V1
  E3 --> V2
  V3 --> E4
  V4 --> E5
  V5 --> E6
```

### Global variables (window)

| Variable | Use |
|----------|-----|
| `__pdfSignableAcroFormOverrides` | Override map applied by the viewer when rendering. Written by the editor. |
| `__pdfSignableAcroFormFields` | Field descriptor list exposed by the viewer for the editor. |
| `__pdfSignableAcroFormEditMode` | Edit mode on (currently always `true` when the editor is present). |
| `__pdfSignableAcroFormMoveResizeFieldId` | Id of the field that has the move/resize overlay visible. |
| `__pdfSignableAcroFormMoveResizePage` | Page of that field; used to restore the overlay after re-render. |

---

## 10. Field list order

The list is **ordered by page** (ascending) and, within each page, **top to bottom** in the PDF (higher Y first). Ordering is applied in `renderFieldsList` before rendering the rows.

---

## 11. Quick summary

| Action | Where | Effect |
|--------|--------|--------|
| Load | Editor | Loads overrides from server and updates list and viewer. |
| Save | Editor | Saves `draftOverrides` to the server. |
| Clear | Editor | Clears the draft and notifies the viewer. |
| Edit (pencil) | Editor | Closes overlay (if any), opens modal for that field. |
| Cross (move/resize) | Editor | Closes modal (if any), shows overlay; field and row in red until changing field or Edit. |
| Click row | Editor | Highlights field on PDF; opens modal only if no overlay active. |
| Click empty PDF | Viewer | Adds new field with pattern name and opens modal. |
| Apply to PDF | Editor | Sends PDF + patches to server; downloads modified PDF. |
| Send / Process | Editor | Sends last applied PDF to process_url. |

For backend PHP events (apply, process, etc.) see [EVENTS](EVENTS.md). For AcroForm configuration and backend see [ACROFORM_BACKEND_EXTENSION](ACROFORM_BACKEND_EXTENSION.md) and [CONFIGURATION](CONFIGURATION.md).
