# POC: AcroForm PDF (pypdf)

Pruebas de concepto para localizar en qué punto falla el flujo de PDF con formularios.

## Flujo

1. **Paso 1**: Crear un PDF de una hoja en blanco → `output/poc_blank.pdf`
2. **Paso 2**: Añadir un campo AcroForm de texto (`campo_prueba`) → `output/poc_with_fields.pdf`
3. **Paso 3**: Cargar ese PDF, aplicar parches (modificar valor del campo) con el script de apply → `output/poc_modified.pdf`
4. **Paso 4**: Verificar los 3 PDFs: blanco sin campos, con un campo inicial, modificado con valor "Texto aplicado por POC".

Si algún paso falla, el mensaje indica en cuál.

## Cómo ejecutar

Desde la raíz del bundle:

```bash
make test-poc
```

O con Python local (requiere `pip install pypdf`):

```bash
python3 scripts/PoC/run_poc.py
```

Los PDFs generados se guardan en `scripts/PoC/output/`.
