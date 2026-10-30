# Configuration

## Table of contents

- [Options](#options)
  - [Signature](#signature)
  - [AcroForm](#acroform)
  - [Proxy URL allowlist](#proxy-url-allowlist)
- [FormKit profile](#formkit-profile)
- [Complete configuration reference](#complete-configuration-reference)
- [Bundle config files (maintainers)](#bundle-config-files-maintainers)

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

    # Signature: global defaults and named profiles by name (use form option config: "alias" to apply).
    signature:
        profiles:
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
| `proxy_url_allowlist`   | string[] | `[]` | When non-empty, the proxy only fetches URLs that match at least one entry. Each entry: a **host** (e.g. `transportes.gob.es` matches that host and its subdomains; `.example.com` subdomains only; optional port/path prefix `example.com:8443/pdfs/`), a **URL prefix** with scheme (e.g. `https://cdn.example.com/pdfs/`: same scheme, exact host and port, path prefix), or a **regex** against the full URL if prefixed with `#` (e.g. `#^https://example\.com/.*#`). Checked again on every redirect hop. Empty list = no restriction unless `proxy_url_allowlist_required` is true. |
| `proxy_max_pdf_size` | int | `20971520` | Max size in bytes of a PDF fetched by the proxy. The download is streamed and aborted (502) when exceeded. |
| `proxy_url_allowlist_required` | bool | `true` | When `true` and `proxy_enabled` is true, an empty allowlist fails container compilation. Set `false` for local demos only. |
| `example_pdf_url`       | string | (sample URL in code) | Default PDF URL for form preload when no pdf_url is set in form/config. Set `''` to disable. |
| `debug`                 | bool   | `false` | When `true`, the frontend uses the bundle logger with debug enabled: all `debug`/`info`/`warn` messages are shown in the browser console (e.g. DOM resolution, load PDF, add/remove box, overlay updates, AcroForm apply). When `false`, only the “script loaded” line is shown. The AcroForm editor panel also supports `data-debug="1"` or `data-debug="true"` on its root element to enable the same logger output for that script. |
| `http_timeout`          | float  | `30`    | HTTP timeout (seconds) for proxy / external PDF fetches. Keep below PHP `max_execution_time` and FrankenPHP/Caddy write timeout (REQ-RUNTIME-001). |
| `process_timeout`       | float  | `60`    | Symfony Process timeout + idle timeout (seconds) for AcroForm extract/apply scripts. |
| `process_script_timeout`| float  | `120`   | Symfony Process timeout + idle timeout (seconds) for `acroform.process_script`. |
| `dependency_check_timeout` | float | `5`  | Process timeout (seconds) for the optional `pypdf` probe in dependency checks. |
| `signature.*`           | —      | see below | Signature: global defaults (box dimensions, lock) and profiles by name (default profile `default`). See [Signature](#signature). |
| `audit.fill_from_request` | bool | `true` | When `true`, the bundle controller merges `submitted_at`, `ip`, and `user_agent` into the model’s `audit_metadata` before dispatching `SIGNATURE_COORDINATES_SUBMITTED`. Your listeners can add more (e.g. `user_id`, `tsa_token`). See [SIGNING_ADVANCED](SIGNING_ADVANCED.md). |
| `tsa_url`               | string \| null | `null` | **Placeholder.** The bundle does not call it. Set your RFC 3161 TSA URL and use it in a listener to obtain a timestamp token; store it in `audit_metadata` (e.g. key `AuditMetadata::TSA_TOKEN`). |
| `signing_service_id`    | string \| null | `null` | **Placeholder.** The bundle does not use it. Set your signing service or HSM service ID and resolve it in a listener for `PDF_SIGN_REQUEST` or `SIGNATURE_COORDINATES_SUBMITTED` to perform PKI/PAdES signing. |
| `acroform.*`             | —              | see below | AcroForm: platform (enabled, scripts, storage) and profiles by name. See [AcroForm](#acroform) and [ACROFORM_BACKEND_EXTENSION](ACROFORM_BACKEND_EXTENSION.md). |

### Signature

Signature (coordinates + boxes) uses a **`signature`** node: global defaults (box dimensions, lock) and **profiles by name** (default profile `default`). Use form option `config: 'alias'` to apply. Define `signature.default_profile`, `signature.default_box_width`, `signature.default_box_height`, `signature.lock_box_width`, `signature.lock_box_height`, `signature.min_box_width`, `signature.min_box_height`, and `signature.profiles` (map alias → form options). See demo YAML and [USAGE](USAGE.md).

### AcroForm

When you need to **persist AcroForm overrides** (default value, label, control type, position per field) or **apply patches to the PDF** (return a modified PDF with updated fields), enable AcroForm and optionally configure storage and the apply endpoint. Config is under a single **`acroform`** node (platform settings + editor defaults + profiles by name; default profile is `default`):

```yaml
nowo_pdf_signable:
    acroform:
        enabled: true                      # Expose GET/POST/DELETE /pdf-signable/acroform/overrides
        overrides_storage: 'session'       # 'session' or your service id (AcroFormOverridesStorageInterface)
        # document_key_mode: 'request'     # Deprecated since 3.4, no effect: document_key is always read from the request (query, form or JSON body)
        allow_pdf_modify: false            # true = expose POST /pdf-signable/acroform/apply (needs editor or event)
        editor_service_id: null            # Optional: service id implementing PdfAcroFormEditorInterface
        max_pdf_size: 20971520             # Max PDF size for apply (bytes)
        max_patches: 500                   # Max patches per apply request
        fields_extractor_script: null      # Optional: path to Python script to extract AcroForm fields (arg = PDF path; stdout = JSON). Enables POST /pdf-signable/acroform/fields/extract
        fields_extractor_command: 'python3' # Executable to run fields_extractor_script (use full path if python3 is not in PATH)
        apply_script: null                 # Optional: path to Python script to apply patches (--pdf, --patches; stdout = modified PDF). See ACROFORM_BACKEND_EXTENSION.md
        apply_script_command: 'python3'   # Executable to run apply_script (use full path if python3 is not in PATH; set apply_script: null if Python is not installed)
        process_script: null               # Optional: path to Python script to process modified PDF (--input, --output, --document-key). Enables POST /pdf-signable/acroform/process; event dispatched with result.
        process_script_command: 'python3'  # Executable to run process_script (use full path if python3 is not in PATH)
        default_profile: default      # Profile used when form option config is not set (resolved from profiles below)
        min_field_width: 12                # Minimum width for AcroForm fields when moving/resizing (PDF points). Global default; overridable per config alias.
        min_field_height: 12               # Minimum height for AcroForm fields when moving/resizing (PDF points). Global default; overridable per config alias.
        # Edit-field modal options (global defaults; overridable per config alias)
        # field_name_mode: 'input'         # 'input' = free text; 'choice' = select with field_name_choices + field_name_other_text
        # field_name_choices: []           # List: ['Name', 'Surname'], ['value|Label'], or [{ value: 'x', label: 'X' }]. Or associative (label => value): { Nombre: nombre, Apellidos: apellidos }
        # field_name_other_text: 'Other'   # Text for "Other" option when field_name_mode is choice
        # show_field_rect: true            # false = hide coordinates (rect) input in the edit-field modal
        # font_sizes: []                   # e.g. [8, 10, 11, 12, 14, 18] for select; empty = number input (1-72)
        # font_families: []                # e.g. ['sans-serif', 'Arial', 'Times New Roman|Times New Roman']; empty = built-in list
        profiles:                           # Profiles by name (form option config: 'alias'). Default profile = default_profile
            default: {}                    # Used when no config is specified
            # with_fonts: { font_sizes: [8, 10, 12], font_families: ['Arial', 'Times New Roman|Times New Roman'] }
            # field_dropdown: { field_name_mode: choice, field_name_choices: ['Name', 'Surname', 'ID'], field_name_other_text: 'Other' }
```

- **Overrides:** With `enabled: true`, the frontend can GET/POST/DELETE overrides by `document_key` (session storage by default).
- **Apply:** Set `allow_pdf_modify: true` and either implement a listener for `ACROFORM_APPLY_REQUEST`, register a service implementing `PdfAcroFormEditorInterface` (e.g. with SetaPDF), or set `apply_script` to a Python script path. See [ACROFORM_BACKEND_EXTENSION](ACROFORM_BACKEND_EXTENSION.md). If Python is not installed, set `apply_script: null`; the apply endpoint will then return 501 unless you provide a PHP editor. If `python3` is not in PATH, set `apply_script_command` to the full path (e.g. `/usr/bin/python3`).
- **Script errors:** Raw script output (stderr/stdout) and filesystem paths are only included in error responses (`detail`, `fields_extractor_error`) when `kernel.debug` is true; in production clients get a generic message and the details go to the logs.
- **Process:** Set `process_script` to a Python script path to expose POST `/pdf-signable/acroform/process`; the bundle runs the script and dispatches `AcroFormModifiedPdfProcessedEvent` so your app can save or use the result. Use `process_script_command` if the executable is not `python3` or not in PATH.
- **Minimum field size:** When using the AcroForm editor (move/resize overlay on the PDF), `min_field_width` and `min_field_height` (in PDF points, default 12) enforce a minimum size when resizing fields.
- **Edit-field modal options:** `field_name_mode`, `field_name_choices`, and `field_name_other_text` control how the field name is edited (free text or select with predefined options). **`field_name_choices`** can be: a **list** of strings (`['Name', 'Date']` or `['nombre|Nombre']` for value|label), a list of objects `[{ value: 'nombre', label: 'Nombre' }]`, or an **associative array** (map) `{ Nombre: nombre, Apellidos: apellidos }` where the key is the label shown in the dropdown and the value is the PDF field name (/T) submitted. `document_key_field` (default `true`): when `false`, the document key input row is hidden in the AcroForm editor panel (the key is still used from config/request). `show_field_rect` (default `true`) hides the coordinates (rect) input when `false`. `font_sizes` and `font_families` control the font size and font family controls (empty = default behaviour; non-empty = select with those values). The legacy keys `label_mode`, `label_choices` and `label_other_text` are still accepted: when set and the matching `field_name_*` key is not, their value is used for `field_name_*`. These are passed to the template and exposed as `data-field-name-mode`, `data-field-name-choices`, etc. on `#acroform-editor-root`. See [ACROFORM](ACROFORM.md).

### Proxy URL allowlist

To restrict which URLs the proxy can fetch (security / abuse prevention), set `proxy_url_allowlist`:

```yaml
nowo_pdf_signable:
    proxy_url_allowlist:
        - 'https://cdn.example.com/'   # URL prefix: https + exact host cdn.example.com (any path)
        - 'transportes.gob.es'          # host: transportes.gob.es and *.transportes.gob.es
        - '#^https://internal\.corp/#'  # regex: must match full URL (anchor it!)
```

If the list is non-empty and the requested URL does not match any entry, the proxy returns 403 with a “URL not allowed” message.

Non-regex entries are matched against the **parsed host** (never as a substring of the whole URL), so `https://evil/?x=example.com` or `https://example.com.evil.net/` do not match `example.com`.

**SSRF mitigation:** The proxy and the AcroForm `pdf_url` fetches always block private, local and reserved addresses (loopback, RFC 1918 incl. 172.16.0.0/12, 0.0.0.0/8, CGNAT 100.64.0.0/10, link-local/metadata 169.254.0.0/16, multicast/reserved, `::`, `::1`, fc00::/7, fe80::/10, IPv4-mapped IPv6 such as `::ffff:127.0.0.1`, hostname `localhost` / `*.localhost`), even when the allowlist is empty. **All** A/AAAA records of the host must be public, and hosts that do not resolve are rejected (fail closed). The connection is pinned to the validated IP and made through Symfony's `NoPrivateNetworkHttpClient` (DNS rebinding protection). Redirects (max 5) are followed manually and every hop is re-checked against the allowlist and SSRF rules.

**Response hardening:** The proxy always answers `Content-Type: application/pdf` (the upstream type is ignored), with `X-Content-Type-Options: nosniff` and `Content-Security-Policy: sandbox`; bodies that do not start with a `%PDF-` header are rejected (502). Bodies larger than `proxy_max_pdf_size` (proxy) or `acroform.max_pdf_size` (AcroForm `pdf_url`) are aborted while streaming.

**502 Bad Gateway:** If the proxy returns 502, the server could not fetch the external PDF (timeout, connection refused, upstream 4xx/5xx, or SSL/DNS from the app server). Check your server logs for the exact reason (the bundle logs a warning with the URL and exception message). Ensure the app (e.g. PHP/Docker) can reach the PDF URL over HTTPS; if the default `example_pdf_url` is unreachable, set `example_pdf_url: ''` and use another PDF URL or a local file served by your app.

---

## FormKit profile

The bundle form types (`SignatureCoordinatesType`, `SignatureBoxType`, `AcroFormEditorType`, `AcroFormEditorPanelType`, `AcroFormFieldEditType`) are FormKit Types (`#[FormKitConfig('pdf_signable')]`, REQ-TWIG-005 rule 11). Their field defaults come from the FormKit profile **`nowo_form_kit.profiles.pdf_signable`**, which the bundle prepends when you do not define it:

```yaml
# config/packages/nowo_form_kit.yaml — define the profile to override the bundle default (the bundle then prepends nothing)
nowo_form_kit:
    profiles:
        pdf_signable:
            alias: pdf_signable
            translation_domain: NowoPdfSignableBundle
            auto_help: false
            auto_placeholder: false
            defaults:
                attr: {}
                row_attr: {}
            field_types:
                text: { attr: { class: form-control } }
                url: { attr: { class: form-control } }
                number: { attr: { class: form-control } }
                integer: { attr: { class: form-control } }
                textarea: { attr: { class: form-control } }
                choice: { attr: { class: form-select } }
                checkbox: { attr: { class: form-check-input }, row_attr: { class: form-check } }
```

- Options a type sets itself (`form-control-sm` classes, the `acroform-edit-*` ids the editor JS reads, explicit labels) win over the profile; the profile fills what the types leave unset.
- Keep `translation_domain: NowoPdfSignableBundle` unless you ship your own translations for the `signature_*` / `acroform_editor.*` label keys.
- The profile does not change your `nowo_form_kit.default_profile`. The bundle widget theme `@NowoPdfSignableBundle/form/theme.html.twig` is still registered in `twig.form_themes` (it implements the `signature_coordinates` / `signature_box` / `acroform_editor` widgets).

## Complete configuration reference

Below is a full example with **every option** documented. Use it as a reference; you only need to set the options you use. For form-type options (inside `signature.profiles.*`), see also [USAGE](USAGE.md).

```yaml
nowo_pdf_signable:
    # ─── Bundle-level (global) ─────────────────────────────────────────────

    # proxy_enabled (bool, default: true)
    # Enables the /pdf-signable/proxy route so the browser can load external PDFs without CORS.
    proxy_enabled: true

    # proxy_url_allowlist (string[], default: [])
    # When non-empty, the proxy only fetches URLs matching at least one entry.
    # Each entry: host (example.com = host + subdomains), URL prefix with scheme (https://cdn.example.com/pdfs/),
    # or regex against the full URL if prefixed with # (e.g. #^https://example\.com/#). Re-checked on redirects.
    # proxy_url_allowlist: []

    # proxy_max_pdf_size (int, default: 20971520)
    # Max size in bytes of a PDF fetched by the proxy; the download is aborted when exceeded.
    # proxy_max_pdf_size: 20971520

    # example_pdf_url (string; bundle default: sample PDF URL in code; set '' to disable)
    # Default PDF URL used when the form has no pdf_url and no config sets pdf_url.
    example_pdf_url: ''

    # debug (bool, default: false)
    # When true, the viewer script logs to the browser console (load, add/remove box, errors).
    # debug: false

    # http_timeout (float seconds, default: 30) — proxy / external PDF HttpClient timeout
    # process_timeout (float seconds, default: 60) — AcroForm extract/apply Process timeout + idle
    # process_script_timeout (float seconds, default: 120) — process_script Process timeout + idle
    # dependency_check_timeout (float seconds, default: 5) — pypdf probe Process timeout
    # http_timeout: 30
    # process_timeout: 60
    # process_script_timeout: 120
    # dependency_check_timeout: 5

    # signature (object): global defaults (box dimensions, lock) and profiles by name (default profile: default).
    signature:
        default_profile: default
        # default_box_width: null
        # default_box_height: null
        # lock_box_width: false
        # lock_box_height: false
        # min_box_width: null
        # min_box_height: null
        profiles:
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
