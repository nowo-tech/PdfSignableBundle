# AcroForm: unified guide

The AcroForm editor lets you **list, edit and reposition** PDF form fields (AcroForm/Widget) without changing the file until you use **Apply to PDF**. Everything is persisted as **overrides** (Layer 1); PDF rewriting is optional (Layer 2). For detailed flow and sequence diagrams, see **[ACROFORM_FLOWS](ACROFORM_FLOWS.md)**. For backend (endpoints, events, DTOs), see **[ACROFORM_BACKEND_EXTENSION](ACROFORM_BACKEND_EXTENSION.md)**.

---

## 1. Overview

- **Frontend:** The viewer (PDF.js) renders the fields; the editor panel lets you load/save overrides, edit each field (type, label, default value, position/size) and add new fields. Overrides are applied in memory (`window.__pdfSignableAcroFormOverrides`); the PDF binary is unchanged.
- **Backend:** Override persistence (session, DB, etc.) and optionally **Apply** (accepts PDF + patches and returns a modified PDF) and **Process** (script on the applied PDF). See [ACROFORM_BACKEND_EXTENSION](ACROFORM_BACKEND_EXTENSION.md).

---

## 2. Current behaviour (summary)

- **Move / resize:** only from the **cross** button on the field row (not by clicking the field outline on the PDF). The overlay stays until you pick another field (another cross) or click **Edit**; the active field row is shown in red.
- **Edit:** opens the **modal** for configuration (rect, type, label, value, font, etc.). Only **one** of the two contexts can be active: move/resize overlay or modal; opening one closes the other.
- **Add field:** click on **empty area** of the PDF → override with id `new-<timestamp>` and name by pattern (e.g. "New field 1") → modal opens to configure it.
- **List order:** by page and, within each page, top to bottom (higher Y first).

**Full flows, sequences and frontend events:** [ACROFORM_FLOWS](ACROFORM_FLOWS.md).

---

## 3. Data model (overrides)

Each field can have in overrides:

| Key | Use |
|-----|-----|
| `hidden` | `true` = do not show the field (hide in view). "Remove" = set hidden. |
| `rect` | `[llx, lly, urx, ury]` in PDF points. If present, the viewer uses this position/size. |
| `defaultValue` | Default value. |
| `label` | Label for accessibility/UI. |
| `controlType` | `text`, `textarea`, `checkbox`, `select`. |
| `options` | For select: `[{ value, label? }, ...]`. |
| `fieldType` | PDF type: Tx, Btn, Ch (informational or for apply). |
| `page` | 1-based page. |
| `fontSize` | Text/textarea only: size in px. |
| `fontFamily` | Text/textarea only: CSS family. |
| `fontAutoSize` | Text/textarea only: `true` = shrink font until content fits. |
| `checkboxValueOn` | Checkbox: value when checked (default `"1"`). |
| `checkboxValueOff` | Checkbox: value when unchecked (default `"0"`). |
| `checkboxIcon` | Checkbox: `"check"`, `"cross"` or `"dot"`. |

In the backend, `AcroFormFieldPatch` includes these fields; when applying to the PDF, the Python script writes rect, defaultValue, hidden, label, fieldType, options, maxLen and appearance (/DA) from fontSize and fontFamily.

---

## 4. Editor UI

- **Field list:** each row shows name/id, type, page, value and buttons: **Hide/Restore**, **Cross** (move/resize on PDF), **Edit** (modal). Clicking the row highlights the field on the PDF.
- **Edit modal:** position and size (rect), control type, options (if select), label, default value; for text/textarea: font; for checkbox: on/off values and icon. Move/resize on the PDF is only from the row cross, not from the modal.
- **Add field:** user clicks an empty area of the PDF; a `new-*` override is created and the modal opens.
- **Load / Save / Clear / Apply / Process:** panel buttons; Apply downloads the modified PDF; Process sends the last applied PDF to the configured endpoint.

Sequence and event details: [ACROFORM_FLOWS](ACROFORM_FLOWS.md).

---

## 5. Modal and editor configuration

### 5.1 Label: free text or list + "Other"

In the modal, the **Label** field can be:

1. **Free text (default):** a single `<input type="text">`.
2. **List + "Other":** a `<select>` with configurable options and an "Other" option that shows a free-text input.

**Bundle config** (`config/packages/nowo_pdf_signable.yaml`):

```yaml
nowo_pdf_signable:
    acroform:
        label_mode: 'choice'
        label_choices: ['Name', 'Surname', 'ID', 'Date', 'Signature']
        label_other_text: 'Other'
```

In the controller, pass to the template: `acroform_label_mode`, `acroform_label_choices`, `acroform_label_other_text` (from bundle parameters or per-route values). The widget uses `data-label-mode`, `data-label-choices`, `data-label-other-text` on `#acroform-editor-root`.

### 5.2 Rect (position and size) visibility

To hide the position/size inputs in the modal (user only move/resizes from the cross):

```yaml
nowo_pdf_signable:
    acroform:
        show_field_rect: false
```

Pass `acroform_show_field_rect` to the template; the template writes `data-show-field-rect` on `#acroform-editor-root`.

### 5.3 Font in text and textarea

In the modal, for text/textarea fields:

- **Font size (px):** optional number (1–72) or a `<select>` if `acroform_font_sizes` is configured (e.g. `[8, 10, 11, 12, 14, 18]`).
- **Font family:** default select (Arial, Helvetica, etc.) or custom list with `acroform_font_families` (e.g. `['sans-serif', 'Arial', 'Times New Roman|Times New Roman']`).
- **Auto-adjust size:** checkbox; the viewer shrinks font size until content fits.

Stored in overrides as `fontSize`, `fontFamily`, `fontAutoSize`. When applying to the PDF, the script uses fontSize and fontFamily for /DA.

**Optional config:**

```yaml
nowo_pdf_signable:
    acroform:
        font_sizes: [8, 10, 11, 12, 14, 18]
        font_families: ['sans-serif', 'Arial', 'Times New Roman|Times New Roman']
```

Pass `acroform_font_sizes` and `acroform_font_families` to the template (`data-font-sizes`, `data-font-families`).

### 5.4 Checkbox: on/off value and icon

For checkboxes in the modal:

- **Value when checked / unchecked:** texts stored (default `1` / `0`).
- **Icon:** `check` (✓), `cross` (✗) or `dot` (•).

The viewer applies `checkboxValueOn`, `checkboxValueOff` and `checkboxIcon` (CSS classes `acroform-checkbox-icon-*`).

---

## 6. Form types and recommended usage

- **`AcroFormEditorType`:** bundle form type that renders viewer + AcroForm panel. Options: `config`, `pdf_url`, `document_key`, `load_url`, `post_url`, `apply_url`, `process_url`, etc. Model: `AcroFormPageModel` (pdfUrl, documentKey).
- **`AcroFormPageType`** (in the app): page form with one child `acroFormEditor` of type `AcroFormEditorType`; pass a config by alias (e.g. `config => 'default'`). See [PROPOSAL_ACROFORM_PAGE_TYPE](PROPOSAL_ACROFORM_PAGE_TYPE.md).
- **`AcroFormFieldEditType`:** form for the field edit modal. Options: `label_mode`, `label_choices`, `label_other_text`, `show_field_rect`, `font_sizes`, `font_families`. The bundle uses `AcroFormFieldEdit` as data class; you can pass a view of this form in `acroform_edit_form` when including `editor_root.html.twig` to customise the modal from Twig.

General bundle configuration (scripts, routes, enable): [CONFIGURATION](CONFIGURATION.md).

---

## 7. Backend and scripts

- **Overrides (Layer 1):** save/load JSON by `document_key`; no PDF modification. Contract: `AcroFormOverridesStorageInterface`; see [ACROFORM_BACKEND_EXTENSION](ACROFORM_BACKEND_EXTENSION.md).
- **Apply (Layer 2):** POST with PDF + patches; server (or Python script) returns the modified PDF. Event: `AcroFormApplyRequestEvent`.
- **Process:** POST with the last applied PDF; optional script (e.g. fill/sign). Event: `AcroFormModifiedPdfProcessedEvent`.
- **Field extractor:** Python script (`extract_acroform_fields.py`) to get the field list from the PDF on the server; configurable via `fields_extractor_script`. See [CONFIGURATION](CONFIGURATION.md) and [ACROFORM_BACKEND_EXTENSION](ACROFORM_BACKEND_EXTENSION.md).

---

## 8. Summary

| Action | How | Where persisted |
|--------|-----|-----------------|
| Hide field | Override `hidden: true` | Overrides (Layer 1) |
| Move/resize | Cross on row → overlay on PDF | Overrides `rect` |
| Edit type, label, value, font, checkbox | Edit modal | Overrides |
| Add field | Click on empty PDF → modal | Overrides `new-*` |
| Apply to PDF | Apply button → server returns modified PDF | PDF file (Layer 2) |
| Process PDF | Process button → endpoint with last applied PDF | Per your listener |

Everything in **overrides** until Apply is used; diagrams and events are in [ACROFORM_FLOWS](ACROFORM_FLOWS.md). Extensibility proposals: [PROPOSAL_ACROFORM_EDITOR_EXTENSIBILITY](PROPOSAL_ACROFORM_EDITOR_EXTENSIBILITY.md).
