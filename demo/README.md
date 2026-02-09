# PdfSignable Bundle demos

Dockerized demos (Symfony 7 and Symfony 8) to try the bundle. Nine demo pages cover no config, default/fixed_url/overridden config, URL as dropdown, limited boxes, same signer multiple locations, unique per name (array), and predefined boxes. Demos include **Web Profiler**, **Xdebug**, and **pnpm** for building the bundle assets.

Screenshots (demo home and signature coordinates form) are in the [main README](../README.md#screenshots) and in [docs/img/](../docs/img/) (`demo-home.png`, `demo-signature-form.png`).

## Start

```bash
make run-symfony7   # http://localhost:8000
make run-symfony8   # http://localhost:8001
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

The demos build the bundle's PDF viewer assets automatically during `make run` / `make install`. The PHP container includes Node.js and pnpm; the build runs from the bundle root before `composer install`.
