# Proposal: AcroForm Editor Extensibility

## Problem

The AcroForm edit-field form, translatable strings and templates are currently:

1. **Modal form** — Built entirely in TypeScript (`acroform-editor.ts`) with hardcoded HTML via `innerHTML`. Users cannot:
   - Add/remove fields via config
   - Use Symfony Form Type for validation
   - Override the template via `templates/bundles/NowoPdfSignable/`

2. **Strings** — Defined in `assets/acroform-editor/strings.ts` and overridden via `window.NowoPdfSignableAcroFormEditorStrings`. Users must:
   - Inject every string manually in their template (as in the demo)
   - Cannot rely on Symfony translations and `translations/` for overrides
   - No single place to extend/override strings

3. **Templates** — The modal structure is in TS, not Twig. Users cannot:
   - Override the modal via `templates/bundles/NowoPdfSignable/acroform/...`
   - Customise layout or add blocks
   - Follow the same pattern as `form/theme.html.twig` and `SignatureCoordinatesType`

## Goal

Make the AcroForm editor extensible in the same way as the main widget:

- **Form Type** for the edit-field form, configurable options, Twig-rendered
- **Twig templates** that users can override in `templates/bundles/NowoPdfSignable/`
- **Translations** from Symfony (`nowo_pdf_signable` domain) injected by the bundle, overridable via app translations

---

## Proposed Changes

### 1. AcroForm Field Edit Form Type

**New:** `Nowo\PdfSignableBundle\Form\AcroFormFieldEditType`

A Symfony Form Type that represents the edit-field modal structure. Options:

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `label_mode` | `'input' \| 'choice'` | `'input'` | Label: free text or select |
| `label_choices` | `array` | `[]` | Options when `label_mode` = `choice` |
| `label_other_text` | `string` | `'Other'` | "Other" option text |
| `show_field_rect` | `bool` | `true` | Show coordinates input |
| `font_sizes` | `int[]` | `[]` | Select options; empty = number input |
| `font_families` | `array` | `[]` | Select options; empty = built-in list |
| `allowed_control_types` | `string[]` | `['text', 'textarea', 'checkbox', 'select', 'choice']` | Control types shown |

Fields:

- `label` (TextType or ChoiceType depending on `label_mode`)
- `controlType` (ChoiceType)
- `rect` (TextType, optional)
- `options` (TextareaType, conditional)
- `defaultValue` (TextType, conditional)
- `defaultChecked` (CheckboxType, conditional)
- `checkboxValueOn`, `checkboxValueOff`, `checkboxIcon` (conditional)
- `fontSize`, `fontFamily`, `fontAutoSize` (conditional)

The form is **rendered once** in the page (hidden) as a Twig template. JS binds to the existing DOM elements by ID, fills values when opening the modal and reads values on save. No server-side submit; the form provides structure and validation rules that can be reused if we add server-side validation later.

### 2. Twig Templates (overrideable)

**New structure:**

```
src/Resources/views/acroform/
├── editor_root.html.twig     # Full panel (document key, load/save, fields list, JSON)
├── editor_modal.html.twig    # Edit-field modal (backdrop + modal body)
├── _edit_modal_body.html.twig # Modal body content (label, control type, rect, etc.)
└── _edit_modal_label_input.html.twig   # Label: input or choice (block)
```

**Rendering flow:**

1. App includes the AcroForm editor panel via a Twig include or block:

   ```twig
   {{ include('@NowoPdfSignable/acroform/editor_root.html.twig', {
       load_url: acroform_overrides_load_url,
       post_url: acroform_overrides_save_url,
       document_key: acroform_document_key,
       config: acroform_config,  # from bundle config
   }) }}
   ```

2. `editor_root.html.twig` renders:
   - The panel (document key, buttons, fields list, JSON textarea)
   - A hidden `#acroform-edit-modal` placeholder that contains the modal HTML
   - A `<script type="application/json" id="acroform-editor-strings">` with all translations
   - A `<script type="application/json" id="acroform-editor-config">` with config (label_mode, show_field_rect, font_sizes, etc.)

3. `editor_modal.html.twig` uses `AcroFormFieldEditType` (or a simple Twig partial if we skip the Form Type for now) and renders the modal structure. Users override via:

   ```twig
   {# templates/bundles/NowoPdfSignable/acroform/_edit_modal_body.html.twig #}
   {% block acroform_edit_modal_body %}
     {# Custom layout, extra fields, etc. #}
   {% endblock %}
   ```

### 3. Translations Injection

**Current:** Demo manually injects `window.NowoPdfSignableAcroFormEditorStrings` with 50+ keys.

**Proposed:** Bundle template injects all strings automatically:

```twig
{# In editor_root.html.twig #}
<script type="application/json" id="acroform-editor-strings" data-translation-domain="nowo_pdf_signable">
  {{ {
    msg_draft_updated: 'acroform_editor.msg_draft_updated'|trans({}, 'nowo_pdf_signable'),
    msg_enter_document_key: 'acroform_editor.msg_enter_document_key'|trans({}, 'nowo_pdf_signable'),
    modal_edit_title: 'acroform_editor.modal_edit_title'|trans({}, 'nowo_pdf_signable'),
    # ... all keys
  }|json_encode|raw }}
</script>
```

Or a Twig function to avoid repetition:

```twig
{{ nowo_pdf_signable_acroform_strings() }}
{# Outputs: <script type="application/json" id="acroform-editor-strings">{"msg_draft_updated":"...", ...}</script> #}
```

**TwigExtension:** Add `nowo_pdf_signable_acroform_strings()` that returns a JSON object of all `acroform_editor.*` translations for the current locale. Keys are the suffix (e.g. `msg_draft_updated`). Users override translations in `translations/nowo_pdf_signable.es.yaml` as usual.

### 4. Config from Bundle

**Current:** Demo controller passes `acroform_label_mode`, `acroform_label_choices`, etc. to the template.

**Proposed:** A Twig function or variable that reads from bundle config:

```twig
{% set acroform_config = nowo_pdf_signable_acroform_editor_config() %}
{# Returns: { label_mode, label_choices, label_other_text, show_field_rect, font_sizes, font_families, min_field_width, min_field_height, load_url, post_url, ... } #}
```

Controller passes only **request-specific** data (load_url, post_url, document_key). Static config (label_mode, font_sizes, etc.) comes from `nowo_pdf_signable.acroform` and is injected by the Twig function.

### 5. JS Changes

**Current:** `initAcroFormEditor()` creates the modal with `document.createElement` and `innerHTML`.

**Proposed:**

1. **Modal from DOM:** If `#acroform-edit-modal` already exists in the DOM (rendered by Twig), JS does **not** create it. It only binds events and manages visibility.
2. **Strings from JSON:** JS reads `#acroform-editor-strings` and parses JSON. Fallback to `DEFAULT_STRINGS` only when the script tag is missing (e.g. legacy pages).
3. **Config from JSON:** JS reads `#acroform-editor-config` for label_mode, font_sizes, etc. Fallback to `data-*` on root for backwards compatibility.

```typescript
// In initAcroFormEditor:
const stringsEl = root.querySelector('#acroform-editor-strings');
const strings: Record<string, string> = stringsEl
  ? (JSON.parse(stringsEl.textContent || '{}') as Record<string, string>)
  : { ...DEFAULT_STRINGS, ...(win.NowoPdfSignableAcroFormEditorStrings ?? {}) };
// Window override still works for runtime overrides
```

### 6. Bundle Template for AcroForm Editor

**New:** `@NowoPdfSignable/acroform/editor_root.html.twig`

A reusable partial that apps include when they want the AcroForm editor panel. Variables:

- `load_url`, `post_url`, `document_key` (required)
- `apply_url`, `process_url` (optional)
- `config` (optional) — overrides from bundle config; if not passed, uses `nowo_pdf_signable_acroform_editor_config()`

The template:

- Renders the panel HTML
- Includes `_edit_modal.html.twig` (modal structure)
- Outputs `acroform-editor-strings` and `acroform-editor-config` script tags
- Registers the acroform-editor.js script (or relies on a global include)

### 7. Form Type vs. Twig Partial

**Option A: Full Form Type**

- Create `AcroFormFieldEditType` with all fields
- Render via `form_widget(editForm)` in the modal body
- Pros: validation, form theme override
- Cons: form is populated by JS, not by Symfony; adds complexity

**Option B: Twig partial only**

- Modal body is a Twig template with raw HTML inputs (same structure as now)
- Use `{% block acroform_edit_modal_label %}` etc. for override points
- Pros: simpler, matches current TS structure
- Cons: no Form Type, no built-in validation

**Recommendation:** Start with **Option B** (Twig partial with blocks). Add Form Type later if server-side validation is needed. The critical win is: **template override** + **translations from Symfony**.

---

## Implementation Order

1. **Twig templates** — Create `editor_root.html.twig`, `_edit_modal.html.twig`, `_edit_modal_body.html.twig` with blocks.
2. **TwigExtension** — Add `nowo_pdf_signable_acroform_strings()` and `nowo_pdf_signable_acroform_editor_config()`.
3. **JS** — Prefer DOM modal if present; read strings from `#acroform-editor-strings`; read config from `#acroform-editor-config`.
4. **Demo** — Refactor demo to use `include('@NowoPdfSignable/acroform/editor_root.html.twig')` instead of inline HTML + manual string injection.
5. **(Later)** Form Type `AcroFormFieldEditType` if validation is required.

---

## Migration

- **Existing apps** that inject `window.NowoPdfSignableAcroFormEditorStrings` continue to work (JS merges with JSON, window takes precedence).
- **Existing apps** that rely on TS creating the modal: if they include the new `editor_root.html.twig`, the modal will be in the DOM and JS will use it. No breaking change.
- **New apps** use the include and get translations + config automatically.

---

## Summary

| Current | Proposed |
|---------|----------|
| Modal HTML in TS | Modal HTML in Twig (`_edit_modal_body.html.twig`), overrideable |
| Strings in `strings.ts` + manual `window` injection | Strings from Symfony translations, injected by bundle template |
| Config passed manually from controller | Config from `nowo_pdf_signable_acroform_editor_config()` |
| No form type | (Optional later) `AcroFormFieldEditType` |
| Demo builds panel inline | Demo uses `include('@NowoPdfSignable/acroform/editor_root.html.twig')` |
