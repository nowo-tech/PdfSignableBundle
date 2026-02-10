# Configuration

Create or edit `config/packages/nowo_pdf_signable.yaml`:

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
| `debug`                 | bool   | `false` | When `true`, the PDF viewer script emits `console.log` and `console.warn` in the browser dev tools (e.g. load PDF, add/remove box, errors). Useful for development. |
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
