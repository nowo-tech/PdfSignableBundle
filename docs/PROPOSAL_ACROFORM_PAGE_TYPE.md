# Proposal: AcroFormPageType (viewer + AcroForm editor) as a standalone feature

**Status:** Implemented (configs in YAML, AcroFormEditorType, AcroFormPageModel, viewer partial, `acroform_editor_widget` block, demo with AcroFormPageType in Symfony 7 and 8).

## Goal

Provide an **AcroForm page type** symmetric to the signature page:

- **SignaturePageType** → contains **PDF viewer** + **coordinates editor** (signature boxes). Configured via YAML (`signature.configs`) and injected per page with isolated options.
- **AcroFormPageType** → should contain **PDF viewer** + **AcroForm panel/editor** (overrides, field list, apply to PDF). YAML configuration and per-page injection, **without depending on or extending** the signature flow.

The bundle is used by defining config in YAML and then injecting that config into each PageType in a simple, isolated way. The two features (signature and AcroForm) must be independent and not extend each other.

---

## Current state

### Signature (already done)

- **Bundle:** `SignatureCoordinatesType` = form type that renders viewer + box list. Options: `pdf_url`, `url_field`, `units`, `config`, etc. Configs by alias live in `nowo_pdf_signable.signature.configs` (default alias: `default`).
- **App (demo):** `SignaturePageType` adds a child `signatureCoordinates` (SignatureCoordinatesType) with `signature_options` per route. Model: `SignaturePageModel` (contains `SignatureCoordinatesModel`).
- **Usage:** `$this->createForm(SignaturePageType::class, $model, ['signature_options' => ['config' => 'fixed_url', ...]])`. Single option injection per page.

### AcroForm (problem)

- **Bundle:** There is no “AcroForm page” form type. There are:
  - `AcroFormFieldEditType` (edit-field modal only).
  - Partial view `editor_root.html.twig` (panel: document key, load/save, list, JSON, modal).
  - Config parameters: `nowo_pdf_signable.acroform.*` (label_mode, font_sizes, etc.) and `acroform.configs` by alias.
- **App (demo):** For the “AcroForm page”, **SignaturePageType** is reused with options that hide the boxes (`show_signature_boxes` false, `max_entries` 0). So only the viewer is used. Then the template:
  - Renders the form (viewer).
  - Manually includes `@NowoPdfSignable/acroform/editor_root.html.twig` passing ~15 variables (`acroform_overrides_load_url`, `acroform_document_key`, `acroform_label_mode`, `acroform_font_sizes`, etc.).
- **Issues:** AcroForm depends on the signature type (reuses the same form), config is injected manually in the controller and template, and there is no “named config” per page for AcroForm.

---

## Design principles

1. **Two independent flows:** Signature (viewer + coordinates) and AcroForm (viewer + field editor) are two distinct “page types”. Neither extends the other.
2. **Config in YAML:** Both Signature and AcroForm have named configs in YAML; each page chooses one (and optionally overrides) with something like `config: 'name'`.
3. **Simple per-page injection:** The controller creates a single form (SignaturePageType or AcroFormPageType) and passes options (or a config name). No manual assembly of URLs, font lists, etc. in each route.
4. **Same viewer, different “right column”:** The PDF viewer (left) is the same concept (same UI, PDF.js, zoom, etc.). On the right: Signature has the box list; AcroForm has the overrides panel (document key, load/save, field list, apply). To avoid duplicating viewer HTML/CSS/JS, a reusable “viewer only” partial should be extracted.

---

## Proposed solution

### 1. Named configs for AcroForm (YAML)

In `Configuration.php`, under `nowo_pdf_signable`, a single **`acroform`** node (instead of `acroform_editor` + `acroform_configs`):

```yaml
nowo_pdf_signable:
  signature:
    configs: { ... }   # existing: for SignatureCoordinatesType
  acroform:            # Platform (enabled, scripts, storage) + configs by alias
    enabled: true
    # ... platform options (fields_extractor_script, apply_script, etc.)
    default_config_alias: default   # default alias when config is not passed
    label_mode: input
    font_sizes: []
    font_families: []
    # ... global editor defaults
    configs:           # Config by alias (default alias 'default')
      default: {}
      with_fonts:
        font_sizes: [8, 10, 11, 12, 14, 18]
        font_families: ['sans-serif', 'Arial', 'Times New Roman|Times New Roman']
      label_dropdown:
        label_mode: choice
        label_choices: ['Name', 'Surname', 'ID', 'Date', 'Signature']
        label_other_text: 'Other'
```

- **Platform** parameters (enabled, scripts, storage) stay under `acroform`; they are not per-alias.
- **Config by alias:** each key in `configs` is an alias (e.g. `default`, `with_fonts`). The value is merged with global defaults. If `config` is not passed on the form, the alias `default_config_alias` is used (e.g. `default`).

### 2. New form type in the bundle: `AcroFormEditorType`

A single form type representing an **“AcroForm page”**: PDF viewer + AcroForm panel.

- **Form fields:** Only those needed for the model (e.g. `pdfUrl` — and optionally `documentKey` if it should be submitted). The rest (load/save/apply URLs, font lists, etc.) goes in options and view vars, not as form children.
- **Options (summary):**
  - `config`: string|null — config alias in `acroform.configs`; if not passed, the default alias is used (`default_config_alias`, e.g. `default`).
  - `pdf_url`: string|null — default PDF URL (as in Signature).
  - `url_field`: bool — show URL field or not.
  - `show_load_pdf_button`: bool — show “Load PDF” button.
  - `document_key`: string — default value for the panel’s document key (e.g. from route).
  - `load_url`, `post_url`, `apply_url`, `process_url`: string — endpoint URLs (overrides load/save, apply, process). Can be built in the controller from bundle routes.
  - Remaining editor options: `label_mode`, `label_choices`, `show_field_rect`, `font_sizes`, `font_families`, `min_field_width`, `min_field_height`. If not passed, they are taken from the config alias or global defaults in `acroform`.

- **Constructor:** Receives (via Autowire) bundle parameters: `example_pdf_url`, `acroform.configs`, `acroform.default_config_alias`, and global defaults from `acroform` (label_mode, font_sizes, etc.). The form type can merge alias (or default) + defaults without the controller injecting each key.

- **buildView:** Passes to the view a single array (e.g. `acroform_editor_options`) with everything the widget needs: urls, document_key, label_mode, font_sizes, etc. (and the form child `pdfUrl` for the viewer).

- **Data model:** The bundle can define a minimal **`AcroFormPageModel`** (e.g. `pdfUrl`, optional `documentKey`) for submit. The overrides panel is “stateless” with respect to the form (load/save via API), so the form only needs what is sent when the page is submitted (if anything).

### 3. Reusable viewer (Twig partial)

To avoid duplicating viewer HTML/structure between Signature and AcroForm:

- **Extract** in the theme a partial, e.g. **`_pdf_viewer_partial.html.twig`**, that receives:
  - `form` (or only the `pdfUrl` child),
  - `opts`: url_field, show_load_pdf_button, default pdf_url, show_acroform, acroform_interactive, viewer_lazy_load, pdfjs_source, pdfjs_worker_url, etc.

- **Partial content:** The viewer card (title “PDF viewer”), URL row (if applicable), Load button (if applicable), placeholder, zoom toolbar, `#pdf-viewer-container`, `#pdf-placeholder`, `#pdf-canvas-wrapper`. No signature boxes and no right column.

- **SignatureCoordinatesType:** In `signature_coordinates_widget` this partial is used for the left part and the box column stays on the right (as now, but viewer HTML comes from the partial).

- **AcroFormEditorType:** In the new `acroform_editor_widget` block the same partial is used for the left part and on the right the AcroForm panel content is included (equivalent to `editor_root.html.twig`), with variables from `form.vars.acroform_editor_options`. So there is a single viewer and the two “right columns” (boxes vs AcroForm panel) differ.

### 4. Form theme (block for AcroForm)

- **New block:** `acroform_editor_widget` in `form/theme.html.twig`.
  - Structure: `row` with two columns (e.g. col-8 + col-4, or col-7 + col-5 as now).
  - Left column: `include _pdf_viewer_partial.html.twig` with `form.pdfUrl` and viewer options from `form.vars.acroform_editor_options`.
  - Right column: include the AcroForm panel (current content of `editor_root.html.twig` or a variant that reads from `form.vars.acroform_editor_options`). Also include the field-edit modal and the `acroform-editor.js` script.
  - Assets: reuse `nowo_pdf_signable_include_assets()` for CSS/PDF.js/pdf-signable.js if the viewer uses them; load acroform-editor.js once (e.g. from this block).

- **IDs / data-*:** The viewer partial must use the same IDs / data-* expected by `pdf-signable.js` (e.g. `#pdf-viewer-container`, `#loadPdfBtn`, etc.) so behaviour matches Signature.

### 5. Usage in the app (controller + page form)

- **Signature routes (unchanged):** Continue using `SignaturePageType` with `signature_options` and/or `config: 'fixed_url'`.

- **AcroForm routes:** Stop using `SignaturePageType` with hidden boxes. Instead:
  - Option A (recommended): The app defines **`AcroFormPageType`** (as with SignaturePageType). That form type has a child **`acroFormEditor`** of type `Nowo\PdfSignableBundle\Form\AcroFormEditorType::class` and passes options, e.g.:
    - `'config' => 'with_fonts'` (alias from `acroform.configs`),
    - `'document_key' => $documentKey` (from route),
    - and the URLs (load, post, apply, process) generated with `generateUrl(...)`.
  - Option B: The controller creates `AcroFormEditorType` directly (without wrapper) and passes the same options.

- **AcroForm page model:** The app can have an **`AcroFormPageModel`** with `pdfUrl` (and optionally `documentKey`) bound to `AcroFormEditorType`. The bundle can provide a minimal model (pdfUrl only) for those who do not want to define their own.

- **Template:** A single template for “AcroForm page”: title, optional explanation, and `form_widget(form)` (or `form_widget(form.acroFormEditor)` if using a wrapper). No need to pass the ~15 panel variables by hand; the form type and theme block handle it.

### 6. Summary of bundle changes

| Component | Change |
|-----------|--------|
| **Configuration.php** | Single `acroform` node: platform + global defaults + `configs` by alias (default alias `default`). |
| **PdfSignableExtension** | Register parameters `acroform.*` and `acroform.configs`; pass them to form type and Twig. |
| **New AcroFormEditorType** | Form type with pdfUrl (and optional documentKey), options (config = alias, pdf_url, urls, document_key, label_mode, font_sizes, …), merge with acroform.configs[alias] and defaults. buildView fills acroform_editor_options. |
| **New AcroFormPageModel** (optional) | Minimal model (pdfUrl, documentKey?) for submit. |
| **Theme Twig** | Extract `_pdf_viewer_partial.html.twig`; use it in `signature_coordinates_widget` and in the new block; add block `acroform_editor_widget` that uses partial + AcroForm panel. |
| **editor_root.html.twig** | Remains for manual inclusion; the new block can include its content or a variant that reads from view vars. |
| **Demo** | Add `AcroFormPageType` (app) with AcroFormEditorType child; in AcroFormController use that form and a template that only renders the form; remove manual inclusion of editor_root and passing of the 15 variables. |

### 7. Per-page config (example)

```yaml
# config/packages/nowo_pdf_signable.yaml
nowo_pdf_signable:
  acroform:
    enabled: true
    label_mode: input
    show_field_rect: true
    font_sizes: []
    font_families: []
    default_config_alias: default
    configs:
      default: {}
      fnmt_custom:
        pdf_url: 'https://www.sede.fnmt.gob.es/.../document.pdf'
        label_mode: choice
        label_choices: ['Name', 'Surname', 'ID', 'Date', 'Signature']
        label_other_text: 'Other'
        font_sizes: [8, 10, 11, 12, 14, 18]
        font_families: ['sans-serif', 'Arial', 'Times New Roman|Times New Roman']
```

In the controller:

```php
// AcroForm page "default"
$form = $this->createForm(AcroFormPageType::class, $model, [
    'acroform_options' => [
        'config' => 'default',
        'document_key' => 'doc-123',
        'load_url' => $this->generateUrl('nowo_pdf_signable_acroform_overrides_load'),
        'post_url' => $this->generateUrl('nowo_pdf_signable_acroform_overrides_save'),
        'apply_url' => $this->generateUrl('nowo_pdf_signable_acroform_apply'),
        'process_url' => $this->generateUrl('nowo_pdf_signable_acroform_process'),
    ],
]);

// Another page with named config
$form = $this->createForm(AcroFormPageType::class, $model, [
    'acroform_options' => [
        'config' => 'fnmt_custom',
        'document_key' => 'fnmt-demo',
        'load_url' => ...,
        'post_url' => ...,
    ],
]);
```

URLs can be centralized in a helper or in the app’s own AcroFormPageType (injecting Router and generating default URLs when not passed).

---

## Benefits

- **Independence:** Signature and AcroForm are two clear flows; SignaturePageType is not reused for AcroForm.
- **Centralized config:** YAML + named configs; each page chooses `config: 'name'` and optionally overrides.
- **Simple injection:** A single option structure (`acroform_options` or direct AcroFormEditorType options) per page.
- **Single viewer:** One partial for the viewer avoids duplicating HTML/CSS and keeps the same behaviour (PDF.js, zoom, etc.) in both flows.
- **Scalable:** More configs can be added in YAML without touching controllers; new pages only choose another `config` and/or document_key and URLs.

If you agree with this direction, the next step would be to implement in this order: (1) `acroform` node (platform + configs by alias, default `default`), (2) viewer partial, (3) `AcroFormEditorType` + theme block, (4) optional model, (5) demo with `AcroFormPageType` and route/template adjustments.
