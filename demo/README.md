# PdfSignable Bundle demos

Dockerized demos (Symfony 7 and Symfony 8) to try the bundle. They run with **FrankenPHP** (Caddy + PHP) and are served over **HTTPS** on localhost. Twenty-plus demo pages cover: no config, default/fixed_url/overridden config, URL as dropdown, limited boxes, same signer multiple locations, unique per name (array), page restriction, sorted boxes, no-overlap, allow-overlap, **min-size-boxes** (min_box_width, min_box_height), rotation (enable_rotation), defaults-by-name (box_defaults_by_name), **snap-to-grid** (snap_to_grid + snap_to_boxes), **guides-and-grid** (show_grid + grid_step), **viewer lazy-load** (IntersectionObserver), **AcroForm editor** (save/load overrides), **AcroForm editor min-size** (min_field_width, min_field_height), latest features (combined, including snap), predefined boxes; plus signing (draw, upload, legal disclaimer, predefined boxes — sign only, signing options). The sidebar highlights the current demo. Touch: pinch zoom and two-finger pan work in the viewer on all demos. Demos include **Web Profiler**, **Xdebug**, and **pnpm** for building the bundle assets.

Screenshots (demo home and signature coordinates form) are in the [main README](../README.md#screenshots) and in [docs/img/](../docs/img/) (`demo-home.png`, `demo-signature-form.png`).

## Start

The demos use **HTTPS** on localhost (self-signed certificate). The browser will show a security warning; accept the certificate to access the app.

```bash
make run-symfony7   # https://localhost:8001
make run-symfony8   # https://localhost:8002
```

From inside a demo:

```bash
cd symfony7
make run
```

## Xdebug

The PHP images include **Xdebug**. Your IDE must listen on port **9003**.

- **Cursor / VS Code**: install the "PHP Debug" extension and use "Listen for Xdebug" (port 9003). Press F5 or "Start Debugging" and reload the page in the browser.
- **PhpStorm**: Run → Start Listening for PHP Connections (port 9003).

The container uses `host.docker.internal` to connect to the host (works with Docker Desktop and Linux with `extra_hosts`).

To avoid starting the connection on every request, in the demo's `docker-compose.yml` you can add:

```yaml
environment:
  - XDEBUG_START_WITH_REQUEST=trigger
```

and use your IDE bookmark/trigger or the `XDEBUG_TRIGGER=1` URL parameter.

## Web Profiler

With `APP_ENV=dev` (default in demos), Symfony's **debug toolbar** appears at the bottom of each response. From there you can open the profiler (`/_profiler`) to inspect timings, requests, forms, etc.

## Assets (pnpm)

The demos build the bundle's PDF viewer assets automatically during `make run` / `make install`. The PHP container includes Node.js and pnpm; the build runs from the bundle root before `composer install`. They do not set `pdfjs_source` so they work with any bundle version; if your bundle supports the option (e.g. from path or v1.6+), you can add `pdfjs_source: 'npm'` in the form options or in `configs` to use PDF.js from the package.

After changing code in the bundle, refresh the demo so it uses the updated bundle: from the demo directory run `make update-bundle` (or from `demo/` run `make update-bundle-symfony7`, `make update-bundle-symfony8`, or `make update-bundle-all`). This rebuilds the bundle assets, runs `assets:install`, and clears the cache.
