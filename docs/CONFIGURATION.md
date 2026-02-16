# Configuration

Create or edit `config/packages/nowo_pdf_signable.yaml`. For a **full example with every option explained**, see [Complete configuration reference](#complete-configuration-reference) below.

```yaml
nowo_pdf_signable:
    # Enable proxy endpoint to load external PDFs (avoids CORS)
    proxy_enabled: true

    # When non-empty, proxy only fetches URLs matching one entry. Each entry: substring of URL, or regex if prefixed with # (e.g. #^https://example\.com/#)
    # proxy_url_allowlist: []

    # Example PDF URL for form preload (leave empty to not preload)
    example_pdf_url: 'https://www.transportes.gob.es/recursos_mfom/paginabasica/recursos/11_07_2019_modelo_orientativo_de_contrato_de_arrendamiento_de_vivienda.pdf'

    # Enable console logging in the browser dev tools (PDF viewer, load, add/remove box)
    # debug: false

    # Signature: global defaults and named configs by alias (use form option config: "alias" to apply).
    signature:
        configs:
            default:
                units: ['mm', 'cm', 'pt']
                unit_default: 'mm'
                origin_default: 'bottom_left'
            fixed_url:
                pdf_url: '%env(EXAMPLE_PDF_URL)%'
                url_field: false
                show_load_pdf_button: false
                # unit_field: false
                # origin_field: false
            limited_boxes:
                min_entries: 1
                max_entries: 4
                signature_box_options:
                    name_mode: choice
                    name_choices: { 'Signer 1': signer_1, 'Signer 2': signer_2, 'Witness': witness }

    # Audit and signing (see SIGNING_ADVANCED.md)
    audit:
        fill_from_request: true   # merge IP, user_agent, submitted_at into model before dispatch
    # tsa_url: null                # your RFC 3161 TSA URL (bundle does not call it)
    # signing_service_id: null     # your signing/HSM service ID (bundle does not use it)
```

## Options

| Option                  | Type   | Default | Description |
|-------------------------|--------|---------|-------------|
| `proxy_enabled`         | bool   | `true`  | Enables the `/pdf-signable/proxy` route to fetch PDFs by URL and avoid CORS. |
| `proxy_url_allowlist`   | string[] | `[]` | When non-empty, the proxy only fetches URLs that match at least one entry. Each entry: a **substring** of the URL (e.g. `transportes.gob.es`), or a **regex** if prefixed with `#` (e.g. `#^https://example\.com/.*#`). Empty list = no restriction. |
| `example_pdf_url`       | string | (sample URL in code) | Default PDF URL for form preload when no pdf_url is set in form/config. Set `''` to disable. |
| `debug`                 | bool   | `false` | When `true`, the PDF viewer script emits `console.log` and `console.warn` in the browser dev tools (e.g. DOM resolution, load PDF, add/remove box, overlay updates, missing template elements). Useful for development and for detecting overridden templates that omit required attributes or structure. |
| `signature.*`           | —      | see below | Signature: global defaults (box dimensions, lock) and configs by alias (default alias `default`). See [Signature](#signature). |
| `audit.fill_from_request` | bool | `true` | When `true`, the bundle controller merges `submitted_at`, `ip`, and `user_agent` into the model’s `audit_metadata` before dispatching `SIGNATURE_COORDINATES_SUBMITTED`. Your listeners can add more (e.g. `user_id`, `tsa_token`). See [SIGNING_ADVANCED](SIGNING_ADVANCED.md). |
| `tsa_url`               | string \| null | `null` | **Placeholder.** The bundle does not call it. Set your RFC 3161 TSA URL and use it in a listener to obtain a timestamp token; store it in `audit_metadata` (e.g. key `AuditMetadata::TSA_TOKEN`). |
| `signing_service_id`    | string \| null | `null` | **Placeholder.** The bundle does not use it. Set your signing service or HSM service ID and resolve it in a listener for `PDF_SIGN_REQUEST` or `SIGNATURE_COORDINATES_SUBMITTED` to perform PKI/PAdES signing. |
| `acroform.*`             | —              | see below | AcroForm: platform (enabled, scripts, storage) and configs by alias. See [AcroForm](#acroform) and [ACROFORM_BACKEND_EXTENSION](ACROFORM_BACKEND_EXTENSION.md). |

### Signature

Signature (coordinates + boxes) uses a **`signature`** node: global defaults (box dimensions, lock) and **configs by alias** (default alias `default`). Use form option `config: 'alias'` to apply. Define `signature.default_config_alias`, `signature.default_box_width`, `signature.default_box_height`, `signature.lock_box_width`, `signature.lock_box_height`, `signature.min_box_width`, `signature.min_box_height`, and `signature.configs` (map alias → form options). See demo YAML and [USAGE](USAGE.md).

### AcroForm

When you need to **persist AcroForm overrides** (default value, label, control type, position per field) or **apply patches to the PDF** (return a modified PDF with updated fields), enable AcroForm and optionally configure storage and the apply endpoint. Config is under a single **`acroform`** node (platform settings + editor defaults + configs by alias; default alias is `default`):

```yaml
nowo_pdf_signable:
    acroform:
        enabled: true                      # Expose GET/POST/DELETE /pdf-signable/acroform/overrides
        overrides_storage: 'session'       # 'session' or your service id (AcroFormOverridesStorageInterface)
        document_key_mode: 'request'       # 'request' = client sends document_key; 'derive_from_url' = from pdf_url
        allow_pdf_modify: false            # true = expose POST /pdf-signable/acroform/apply (needs editor or event)
        editor_service_id: null            # Optional: service id implementing PdfAcroFormEditorInterface
        max_pdf_size: 20971520             # Max PDF size for apply (bytes)
        max_patches: 500                   # Max patches per apply request
        fields_extractor_script: null      # Optional: path to Python script to extract AcroForm fields (arg = PDF path; stdout = JSON). Enables POST /pdf-signable/acroform/fields/extract
        apply_script: null                 # Optional: path to Python script to apply patches (--pdf, --patches; stdout = modified PDF). See ACROFORM_BACKEND_EXTENSION.md
        apply_script_command: 'python3'   # Executable to run apply_script (use full path if python3 is not in PATH; set apply_script: null if Python is not installed)
        process_script: null               # Optional: path to Python script to process modified PDF (--input, --output, --document-key). Enables POST /pdf-signable/acroform/process; event dispatched with result.
        process_script_command: 'python3'  # Executable to run process_script (use full path if python3 is not in PATH)
        default_config_alias: default      # Alias used when form option config is not set (resolved from configs below)
        min_field_width: 12                # Minimum width for AcroForm fields when moving/resizing (PDF points). Global default; overridable per config alias.
        min_field_height: 12               # Minimum height for AcroForm fields when moving/resizing (PDF points). Global default; overridable per config alias.
        # Edit-field modal options (global defaults; overridable per config alias)
        # field_name_mode: 'input'         # 'input' = free text; 'choice' = select with field_name_choices + field_name_other_text
        # field_name_choices: []           # e.g. ['Name', 'Surname', 'ID', 'Date'] or ['value|Label', ...]
        # field_name_other_text: 'Other'   # Text for "Other" option when field_name_mode is choice
        # show_field_rect: true            # false = hide coordinates (rect) input in the edit-field modal
        # font_sizes: []                   # e.g. [8, 10, 11, 12, 14, 18] for select; empty = number input (1-72)
        # font_families: []                # e.g. ['sans-serif', 'Arial', 'Times New Roman|Times New Roman']; empty = built-in list
        configs:                           # Configs by alias (form option config: 'alias'). Default alias = default_config_alias
            default: {}                    # Used when no config is specified
            # with_fonts: { font_sizes: [8, 10, 12], font_families: ['Arial', 'Times New Roman|Times New Roman'] }
            # field_dropdown: { field_name_mode: choice, field_name_choices: ['Name', 'Surname', 'ID'], field_name_other_text: 'Other' }
```

- **Overrides:** With `enabled: true`, the frontend can GET/POST/DELETE overrides by `document_key` (session storage by default).
- **Apply:** Set `allow_pdf_modify: true` and either implement a listener for `ACROFORM_APPLY_REQUEST`, register a service implementing `PdfAcroFormEditorInterface` (e.g. with SetaPDF), or set `apply_script` to a Python script path. See [ACROFORM_BACKEND_EXTENSION](ACROFORM_BACKEND_EXTENSION.md). If Python is not installed, set `apply_script: null`; the apply endpoint will then return 501 unless you provide a PHP editor. If `python3` is not in PATH, set `apply_script_command` to the full path (e.g. `/usr/bin/python3`).
- **Process:** Set `process_script` to a Python script path to expose POST `/pdf-signable/acroform/process`; the bundle runs the script and dispatches `AcroFormModifiedPdfProcessedEvent` so your app can save or use the result. Use `process_script_command` if the executable is not `python3` or not in PATH.
- **Minimum field size:** When using the AcroForm editor (move/resize overlay on the PDF), `min_field_width` and `min_field_height` (in PDF points, default 12) enforce a minimum size when resizing fields.
- **Edit-field modal options:** `field_name_mode`, `field_name_choices`, and `field_name_other_text` control how the field name is edited (free text or select with predefined options). `show_field_rect` (default `true`) hides the coordinates (rect) input when `false`. `font_sizes` and `font_families` control the font size and font family controls (empty = default behaviour; non-empty = select with those values). These are passed to the template and exposed as `data-field-name-mode`, `data-field-name-choices`, etc. on `#acroform-editor-root`. See [ACROFORM](ACROFORM.md).

### Proxy URL allowlist

To restrict which URLs the proxy can fetch (security / abuse prevention), set `proxy_url_allowlist`:

```yaml
nowo_pdf_signable:
    proxy_url_allowlist:
        - 'https://cdn.example.com/'   # any URL containing this string
        - 'transportes.gob.es'          # any URL containing this string
        - '#^https://internal\.corp/#'  # regex: must match full URL
```

If the list is non-empty and the requested URL does not match any entry, the proxy returns 403 with a “URL not allowed” message.

**SSRF mitigation:** The proxy always blocks requests to private or local hosts (127.0.0.0/8, ::1, 10.0.0.0/8, 192.168.0.0/16, 169.254.0.0/16, and hostname `localhost`), even when the allowlist is empty. This reduces the risk of server-side request forgery.

**502 Bad Gateway:** If the proxy returns 502, the server could not fetch the external PDF (timeout, connection refused, upstream 4xx/5xx, or SSL/DNS from the app server). Check your server logs for the exact reason (the bundle logs a warning with the URL and exception message). Ensure the app (e.g. PHP/Docker) can reach the PDF URL over HTTPS; if the default `example_pdf_url` is unreachable, set `example_pdf_url: ''` and use another PDF URL or a local file served by your app.

---

## Complete configuration reference

Below is a full example with **every option** documented. Use it as a reference; you only need to set the options you use. For form-type options (inside `signature.configs.*`), see also [USAGE](USAGE.md).

```yaml
nowo_pdf_signable:
    # ─── Bundle-level (global) ─────────────────────────────────────────────

    # proxy_enabled (bool, default: true)
    # Enables the /pdf-signable/proxy route so the browser can load external PDFs without CORS.
    proxy_enabled: true

    # proxy_url_allowlist (string[], default: [])
    # When non-empty, the proxy only fetches URLs matching at least one entry.
    # Each entry: substring of URL, or regex if prefixed with # (e.g. #^https://example\.com/#).
    # proxy_url_allowlist: []

    # example_pdf_url (string; bundle default: sample PDF URL in code; set '' to disable)
    # Default PDF URL used when the form has no pdf_url and no config sets pdf_url.
    example_pdf_url: ''

    # debug (bool, default: false)
    # When true, the viewer script logs to the browser console (load, add/remove box, errors).
    # debug: false

    # signature (object): global defaults (box dimensions, lock) and configs by alias (default alias: default).
    signature:
        default_config_alias: default
        # default_box_width: null
        # default_box_height: null
        # lock_box_width: false
        # lock_box_height: false
        # min_box_width: null
        # min_box_height: null
        configs:
            # Example: minimal preset (units and origin only)
            default:
                units: ['mm', 'cm', 'pt']
                unit_default: 'mm'
                origin_default: 'bottom_left'

            # Example: single fixed PDF (no URL field, no Load button)
            fixed_url:
                pdf_url: '%env(EXAMPLE_PDF_URL)%'
                url_field: false
                show_load_pdf_button: false
                unit_field: false
                origin_field: false
                unit_default: 'mm'
                origin_default: 'bottom_left'

            # Example: full commented config (every form option)
            full_reference:
                # ─── URL ───
                pdf_url: null                    # Initial PDF URL; null = use example_pdf_url when set
                url_field: true                  # false = hide URL field, use pdf_url as hidden value
                show_load_pdf_button: true       # false = hide "Load PDF" button (e.g. with url_field false)
                url_mode: 'input'                # 'input' = text; 'choice' = dropdown (use url_choices)
                url_choices: []                  # For url_mode choice: { 'Label': 'https://...' }
    
                # ─── Unit ───
                units: null                      # null = all (mm, cm, pt, px, in); or e.g. ['mm', 'pt']
                unit_default: 'mm'
                unit_field: true                 # false = hidden field, value = unit_default
                unit_mode: 'choice'              # 'choice' = dropdown; 'input' = text
    
                # ─── Origin ───
                origins: null                    # null = all four corners; or e.g. ['top_left', 'bottom_left']
                origin_default: 'bottom_left'
                origin_field: true               # false = hidden field, value = origin_default
                origin_mode: 'choice'
    
                # ─── Signature boxes (collection) ───
                min_entries: 0
                max_entries: null                # null = unlimited
                unique_box_names: false          # true = all unique; or ['signer_1', 'witness'] = only those unique
                allowed_pages: null              # e.g. [1] = dropdown, boxes only on page 1
                sort_boxes: false                # true = sort by page, Y, X on submit
                prevent_box_overlap: true        # false = allow overlapping boxes on same page
                enable_rotation: false           # true = angle field + rotate handle per box
                box_defaults_by_name: {}         # e.g. signer_1: { width: 150, height: 40, x: 0, y: 0, angle: 0 }
                signature_box_options: {}        # Passed to each box (name_mode, name_choices, etc.)
                # collection_constraints: []     # Extra constraints on the collection
                # box_constraints: []             # Extra constraints on each box
    
                # ─── Viewer / layout ───
                snap_to_grid: 0                  # Step in form unit (e.g. 5 for 5 mm); 0 = off
                snap_to_boxes: true              # Snap box edges to other boxes when dragging
                show_grid: false                 # true = grid overlay on each page
                grid_step: 10.0                  # Grid step when show_grid true (form unit)
                viewer_lazy_load: false          # true = load PDF.js when widget enters viewport
                show_acroform: true              # true = show AcroForm field outlines on PDF
                acroform_interactive: true      # true = editable inputs for text fields on PDF
                hide_coordinate_fields: false    # true = hide width/height/x/y (and angle) in UI; values still submitted
                hide_position_fields: false     # true = hide x/y in UI; values still submitted (e.g. from overlay)
                min_box_width: null             # minimum width (form unit); null = no minimum
                min_box_height: null            # minimum height (form unit); null = no minimum
                show_signature_boxes: true       # false = hide signature boxes card (AcroForm-only flows)
    
                # ─── Signing (draw/upload in box) ───
                enable_signature_capture: false   # true = draw pad per box
                enable_signature_upload: false   # true = file upload per box
                signing_legal_disclaimer: null   # Text above viewer (e.g. legal notice)
                signing_legal_disclaimer_url: null
                signing_require_consent: false   # true = required checkbox before submit
                signing_consent_label: 'signing.consent_label'
                signing_only: false              # true = only box name + signature (no coordinate fields)
                batch_sign_enabled: false        # true = "Sign all" button, dispatches BATCH_SIGN_REQUESTED
    
    # ─── Audit and signing (bundle-level) ─────────────────────────────────
    audit:
        fill_from_request: true   # Merge submitted_at, ip, user_agent into model before events
    # tsa_url: null               # Placeholder: your RFC 3161 TSA URL (bundle does not call it)
    # signing_service_id: null    # Placeholder: your signing/HSM service ID (bundle does not use it)
```

**Using a named config in code:**

```php
$builder->add('signatureCoordinates', SignatureCoordinatesType::class, [
    'config' => 'fixed_url',
    // Override anything from the config:
    // 'pdf_url' => 'https://other.com/doc.pdf',
]);
```

See [USAGE](USAGE.md) for the full option tables and [SIGNING_ADVANCED](SIGNING_ADVANCED.md) for audit, TSA and PKI integration. To override the form theme or other bundle templates, see [USAGE — Overriding bundle templates](USAGE.md#overriding-bundle-templates).

---

## Bundle config files (maintainers)

Inside the bundle, `Resources/config/` contains:

- **`services.yaml`** — Controllers (SignatureController, AcroFormOverridesController), AcroForm session storage, form types, Twig extension. The extension sets the storage alias from `acroform.overrides_storage`.
- **`routes.yaml`** — Signature and AcroForm controller routes (attribute routes); apps import this file once and set `prefix`.
