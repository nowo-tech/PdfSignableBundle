# Security Policy

## Security considerations for integrators

- **PDF proxy**
  - When `proxy_enabled` is true, the bundle fetches external PDFs. Use **proxy_url_allowlist** to restrict which URLs can be requested (substring or regex).
  - Set **`proxy_url_allowlist_required: true`** in production so an empty allowlist fails container compilation. Default remains `false` for BC / local demos only.
  - The Flex recipe (`when@prod`) forces `proxy_url_allowlist_required: true` in production; keep a non-empty `proxy_url_allowlist`.
  - The proxy **blocks private/local URLs** (SSRF mitigation): 127.0.0.0/8, ::1, 10.0.0.0/8, 192.168.0.0/16, 169.254.0.0/16 and hostname `localhost`. Requests to these hosts return 403.
  - Proxy error responses do not expose exception messages to the client (no internal paths or server details).

- **AcroForm JSON routes**
  - When `acroform.enabled` is true, `/pdf-signable/acroform/*` endpoints mutate session (or custom storage) and may run Process scripts. The bundle does **not** attach `IsGranted` by default (form/API surface for the host app).
  - **CSRF (REQ-SEC-005):** All mutating AcroForm actions (`POST` load/save/extract/apply/process, `DELETE` overrides) validate a CSRF token with id `nowo_pdf_signable_acroform` (`AcroFormOverridesController::CSRF_TOKEN_ID`). Clients must send the token as the `X-CSRF-Token` header or as `_token` in the JSON/form body. The bundled editor panel sets `data-csrf-token="{{ csrf_token('nowo_pdf_signable_acroform') }}"` and sends `X-CSRF-Token` on every mutating `fetch`.
  - **Host must** still protect these routes with firewall `access_control` / authentication appropriate for your threat model.
  - Keep `allow_pdf_modify: false` unless you need PDF rewrite; set script timeouts via `process_timeout` / `process_script_timeout`.

- **Form and viewer**
  - Signature box names and coordinates are user input. The bundle form theme and JavaScript use **escaped output** (e.g. `escapeHtml` for overlay labels) when rendering user-controlled data into the DOM. Do not disable escaping in templates that display user data.
  - CSRF is handled by Symfony's form component when the signature form is submitted with the default configuration. AcroForm AJAX mutations use the explicit token above.

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

## Release security checklist (12.4.1)

Before tagging a release, confirm:

| Item | Notes |
|------|--------|
| **SECURITY.md** | This document is current and linked from the README where applicable. |
| **`.gitignore` and `.env`** | `.env` and local env files are ignored; no committed secrets. |
| **No secrets in repo** | No API keys, passwords, or tokens in tracked files. |
| **Recipe / Flex** | Default recipe or installer templates do not ship production secrets. |
| **Input / output** | Inputs validated; outputs escaped in Twig/templates where user-controlled. |
| **Dependencies** | `composer audit` run; issues triaged. |
| **Logging** | Logs do not print secrets, tokens, or session identifiers unnecessarily. |
| **Cryptography** | If used: keys from secure config; never hardcoded. |
| **Permissions / exposure** | Routes and admin features documented; roles configured for production. |
| **Limits / DoS** | Timeouts, size limits, rate limits where applicable. |

Record confirmation in the release PR or tag notes.

## AI security audit

| Field | Value |
| ----- | ----- |
| Date | 2026-07-29 |
| Grade | Pass (conditional) |
| Overall risk | Medium |
| Residual | Empty allowlist allowed when `proxy_url_allowlist_required: false`; AcroForm routes still need host firewall/auth (CSRF is enforced by the bundle) |
| Monorepo | See `BUNDLES_SECURITY_ANALYSIS.md` (PdfSignableBundle) |
