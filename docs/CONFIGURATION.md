# Configuration

Create or edit `config/packages/nowo_pdf_signable.yaml`:

```yaml
nowo_pdf_signable:
    # Enable proxy endpoint to load external PDFs (avoids CORS)
    proxy_enabled: true

    # Example PDF URL for form preload (leave empty to not preload)
    example_pdf_url: 'https://www.transportes.gob.es/recursos_mfom/paginabasica/recursos/11_07_2019_modelo_orientativo_de_contrato_de_arrendamiento_de_vivienda.pdf'
```

## Options

| Option            | Type   | Default | Description |
|-------------------|--------|---------|-------------|
| `proxy_enabled`   | bool   | `true`  | Enables the `/pdf-signable/proxy` route to fetch PDFs by URL and avoid CORS. |
| `example_pdf_url` | string | Sample PDF URL | If set, the coordinates form is preloaded with this URL. Empty string to disable. |
