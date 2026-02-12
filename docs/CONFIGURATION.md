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

    # Optional: named configs for SignatureCoordinatesType (use form option config: "name" to apply)
    configs:
        default:
            units: ['mm', 'cm', 'pt']
            unit_default: 'mm'
            origin_default: 'bottom_left'
        fixed_url:
            pdf_url: '%env(EXAMPLE_PDF_URL)%'
            url_field: false
            show_load_pdf_button: false
            # Optional: lock unit and origin (hidden fields, use unit_default/origin_default)
            # unit_field: false
            # origin_field: false
        limited_boxes:
            min_entries: 1
            max_entries: 4
            signature_box_options:
                name_mode: choice
                name_choices: { 'Signer 1': signer_1, 'Signer 2': signer_2, 'Witness': witness }
                # choice_placeholder: false  # no empty option (default)

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
| `example_pdf_url`       | string | `''` (empty) | If set, the coordinates form is preloaded with this URL. Empty string to disable. |
| `debug`                 | bool   | `false` | When `true`, the PDF viewer script emits `console.log` and `console.warn` in the browser dev tools (e.g. DOM resolution, load PDF, add/remove box, overlay updates, missing template elements). Useful for development and for detecting overridden templates that omit required attributes or structure. |
| `configs`               | array  | `[]`    | Named configurations for the form type. Keys are option names; use the form option `config: "name"` to apply a config. Options passed when creating the form override the named config. |
| `audit.fill_from_request` | bool | `true` | When `true`, the bundle controller merges `submitted_at`, `ip`, and `user_agent` into the model’s `audit_metadata` before dispatching `SIGNATURE_COORDINATES_SUBMITTED`. Your listeners can add more (e.g. `user_id`, `tsa_token`). See [SIGNING_ADVANCED](SIGNING_ADVANCED.md). |
| `tsa_url`               | string \| null | `null` | **Placeholder.** The bundle does not call it. Set your RFC 3161 TSA URL and use it in a listener to obtain a timestamp token; store it in `audit_metadata` (e.g. key `AuditMetadata::TSA_TOKEN`). |
| `signing_service_id`    | string \| null | `null` | **Placeholder.** The bundle does not use it. Set your signing service or HSM service ID and resolve it in a listener for `PDF_SIGN_REQUEST` or `SIGNATURE_COORDINATES_SUBMITTED` to perform PKI/PAdES signing. |

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

---

## Complete configuration reference

Below is a full example with **every option** documented. Use it as a reference; you only need to set the options you use. For form-type options (inside `configs.*`), see also [USAGE](USAGE.md).

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

    # example_pdf_url (string, default: '')
    # Default PDF URL used when the form has no pdf_url and no config sets pdf_url.
    # Leave empty to not preload any URL.
    example_pdf_url: ''

    # debug (bool, default: false)
    # When true, the viewer script logs to the browser console (load, add/remove box, errors).
    # debug: false

    # configs (array, default: {})
    # Named presets for SignatureCoordinatesType. Reference with form option config: "name".
    # Keys below are form-type options; see USAGE.md for all of them.
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
