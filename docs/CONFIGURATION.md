# Configuration

Create or edit `config/packages/nowo_pdf_signable.yaml`:

```yaml
nowo_pdf_signable:
    # Enable proxy endpoint to load external PDFs (avoids CORS)
    proxy_enabled: true

    # Example PDF URL for form preload (leave empty to not preload)
    example_pdf_url: 'https://www.transportes.gob.es/recursos_mfom/paginabasica/recursos/11_07_2019_modelo_orientativo_de_contrato_de_arrendamiento_de_vivienda.pdf'

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
```

## Options

| Option            | Type   | Default | Description |
|-------------------|--------|---------|-------------|
| `proxy_enabled`   | bool   | `true`  | Enables the `/pdf-signable/proxy` route to fetch PDFs by URL and avoid CORS. |
| `example_pdf_url` | string | `''` (empty) | If set, the coordinates form is preloaded with this URL. Empty string to disable. |
| `configs`         | array  | `[]`    | Named configurations for the form type. Keys are option names; use the form option `config: "name"` to apply a config. Options passed when creating the form override the named config. |
