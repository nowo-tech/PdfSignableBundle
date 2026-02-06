# PdfSignable Bundle

Symfony bundle to define **signature coordinates on PDFs**: a form field that receives the PDF URL, renders it on screen and lets you place signature boxes by click (and drag to move/resize).

## Features

- Form with PDF URL, units (mm, cm, pt, px, in), origin (corners) and a collection of signature boxes.
- PDF viewer in the browser (PDF.js) with overlays for each box.
- Click on the PDF to add a new box; drag to move; drag corners to resize.
- Optional proxy to load external PDFs without CORS issues.
- Compatible with Symfony 6.1+, 7 and 8.

## Installation

```bash
composer require nowo-tech/pdf-signable-bundle
```

Register the bundle (automatic with Flex), import the routes and optionally configure `nowo_pdf_signable` (proxy and example URL). See [docs/INSTALLATION.md](docs/INSTALLATION.md) and [docs/CONFIGURATION.md](docs/CONFIGURATION.md).

## Quick usage

The **Type** `SignatureCoordinatesType` is a form field that renders the full view (PDF viewer + boxes) and submits coordinates in the model:

- **Model**: `SignatureCoordinatesModel` (pdfUrl, unit, origin, signatureBoxes) and `SignatureBoxModel` (name, page, x, y, width, height).
- **View**: the bundle form theme renders the full widget (PDF + click to define boxes).

1. Go to the bundle route (default `/pdf-signable`) or include the Type in your own form.
2. Enter a PDF URL and click "Load PDF".
3. Click on the PDF to place signature boxes; adjust position and size by dragging.
4. Submit the form to get the model with all coordinates.

See [docs/USAGE.md](docs/USAGE.md) for using the form type in your app and proxy details.

## Demos

Dockerized demos for Symfony 7 and 8 (Bootstrap, Vite, TypeScript):

```bash
cd demo
make run-symfony7   # → http://localhost:8000
# or
make run-symfony8   # → http://localhost:8001
```

See [demo/Makefile](demo/Makefile) for more targets.

### Debugger (Xdebug)

Demos include **Xdebug** in the PHP container. Your host IDE must listen on port **9003**.

- **VS Code / Cursor**: "PHP Debug" extension, use "Listen for Xdebug" in `launch.json` (port 9003).
- **PhpStorm**: Settings → PHP → Debug, port 9003; then Run → Start Listening.

On each request to the demo, Xdebug will try to connect to the IDE. If nothing is listening, the response may take a few seconds; to start only on demand, in the demo's `docker-compose.yml` you can set `XDEBUG_START_WITH_REQUEST=trigger` on the `php` service and use your IDE trigger (cookie/parameter).

## Frontend (Vite + TypeScript)

The PDF viewer script is built with **Vite** and **TypeScript**. The built file is in `src/Resources/public/js/pdf-signable.js` and is included by the form theme. After installing the bundle in your app, run:

```bash
php bin/console assets:install
```

To rebuild the script (e.g. after changing `assets/pdf-signable.ts`), from the bundle root:

```bash
pnpm install
pnpm run build
```

## Tests and QA

From the bundle root:

```bash
make install
make test       # PHPUnit
make cs-check   # PHP-CS-Fixer
make qa         # cs-check + test
```

## Documentation (English)

- [Installation](docs/INSTALLATION.md)
- [Configuration](docs/CONFIGURATION.md)
- [Usage](docs/USAGE.md)
- [Changelog](docs/CHANGELOG.md)
- [Upgrading](docs/UPGRADING.md)

## License

MIT.
