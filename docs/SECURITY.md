# Security Policy

## Security considerations for integrators

- **PDF proxy**
  - When `proxy_enabled` is true, the bundle fetches external PDFs. Use **proxy_url_allowlist** to restrict which URLs can be requested (substring or regex). Empty allowlist = no restriction (suitable only for trusted environments).
  - The proxy **blocks private/local URLs** (SSRF mitigation): 127.0.0.0/8, ::1, 10.0.0.0/8, 192.168.0.0/16, 169.254.0.0/16 and hostname `localhost`. Requests to these hosts return 403.
  - Proxy error responses do not expose exception messages to the client (no internal paths or server details).

- **Form and viewer**
  - Signature box names and coordinates are user input. The bundle form theme and JavaScript use **escaped output** (e.g. `escapeHtml` for overlay labels) when rendering user-controlled data into the DOM. Do not disable escaping in templates that display user data.
  - CSRF is handled by Symfony's form component when the signature form is submitted with the default configuration.

- **Flash messages**
  - If you store HTML in flash messages, render it with `|raw` only when that HTML is produced by your code with **all** user-derived parts escaped (e.g. `htmlspecialchars`). Prefer plain-text flash messages and `{{ message }}` (no `|raw`) to avoid XSS.

## Supported Versions

We provide security fixes for the following versions:

| Version | Supported          |
| ------- | ------------------ |
| 2.x     | :white_check_mark: |
| 1.x     | :white_check_mark: |

## Reporting a Vulnerability

If you discover a security vulnerability in this project, please report it responsibly:

1. **Do not** open a public GitHub issue for security-sensitive bugs.
2. Send details to **[hectorfranco@nowo.tech](mailto:hectorfranco@nowo.tech)** (or the maintainers listed in [composer.json](../composer.json)).
3. Include a clear description, steps to reproduce, and impact if possible.
4. We will acknowledge receipt and work on a fix. We may ask for more information.
5. After a fix is released, we can coordinate on disclosure (e.g. a security advisory).

We appreciate your effort to report vulnerabilities privately so users can update before details are public.
