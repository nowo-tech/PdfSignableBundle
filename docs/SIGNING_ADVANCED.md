# Advanced signing (PKI, timestamp, audit, batch)

The bundle provides **structures and extension points** for digital signatures (PKI/PAdES), trusted timestamps, audit trails, and batch signing. It does **not** include third-party libraries or services; your application supplies keys, TSA URL, and signing service integration.

---

## What the bundle provides (no extra dependencies)

### Audit metadata

- **`SignatureCoordinatesModel::getAuditMetadata()`** — Array for evidence (who, when, IP, etc.). Exported in `toArray()` for storage or export.
- **`AuditMetadata`** (class with constants) — Recommended keys: `submitted_at`, `ip`, `user_agent`, `user_id`, `session_id`, `tsa_token`, `signing_method`. See `Nowo\PdfSignableBundle\Model\AuditMetadata`.
- **Config `audit.fill_from_request`** (default `true`) — When enabled, the bundle controller merges `submitted_at`, `ip`, and `user_agent` into the model before dispatching `SIGNATURE_COORDINATES_SUBMITTED`. Your listener can add `user_id`, `session_id`, `tsa_token`, etc.

### Events

| Event | When | Use in listener |
|-------|------|------------------|
| **`SIGNATURE_COORDINATES_SUBMITTED`** | Form submitted and valid. | Persist, call your signing service, add audit fields, return custom response. |
| **`BATCH_SIGN_REQUESTED`** | Form submitted with **Sign all** (`batch_sign=1`). | Perform batch signing (draw/upload or PKI), redirect or set response. |
| **`PDF_SIGN_REQUEST`** | Dispatched when your code requests a digital signature. | Call your PKI/HSM service, set signed PDF or `setResponse()` on the event. |

### Batch signing (one-click)

- **Form option `batch_sign_enabled`** — When `true`, the widget shows a **Sign all** button. Submitting with that button sends `batch_sign=1` and the bundle dispatches **`BATCH_SIGN_REQUESTED`** with the coordinates model and request. Your listener handles the actual signing (e.g. iterate boxes for draw/upload, or one PKI sign for all).

### Config placeholders (for your app)

The bundle defines optional config under `nowo_pdf_signable` that it **does not use**; you can set them and read them in your listeners:

- **`tsa_url`** — Your RFC 3161 TSA endpoint. In a `SIGNATURE_COORDINATES_SUBMITTED` listener, call this URL to get a timestamp token, then set `AuditMetadata::TSA_TOKEN` in the model’s audit metadata.
- **`signing_service_id`** — Your signing service or HSM service ID. In a listener for `PDF_SIGN_REQUEST` or `SIGNATURE_COORDINATES_SUBMITTED`, get this service from the container and use it to produce a PAdES signature.

---

## What you must add (third-party / app)

### Trusted timestamp (RFC 3161)

1. **Configure** `nowo_pdf_signable.tsa_url` (or your own config) with your TSA endpoint.
2. In a **listener for `SIGNATURE_COORDINATES_SUBMITTED`**:
   - Request a timestamp from the TSA (e.g. over HTTP with the hash of the signed data).
   - Put the returned token (base64) in `$model->getAuditMetadata()` under key `AuditMetadata::TSA_TOKEN`.
   - Persist or export the model (including `audit_metadata`) for evidence.

The bundle does not call the TSA or any HTTP client for it.

### Digital signature (PKI / PAdES)

1. **Backend**: Use a PHP library (e.g. SetaPDF-Signer, TCPDF with signing, or your HSM/signing service SDK) to:
   - Load the PDF (from URL or storage).
   - Apply a PAdES-BES or PAdES-EPES signature in the defined boxes (coordinates from `SignatureCoordinatesModel`).
   - Optionally add LTV and/or timestamp (TSA).
2. **Integration**: Either:
   - In a **`SIGNATURE_COORDINATES_SUBMITTED`** listener: read coordinates, call your signing service, save the signed PDF and update the model/DB; or
   - Dispatch **`PDF_SIGN_REQUEST`** from your code (e.g. after a “Sign with certificate” button), and in the listener call the signing service and set `$event->setResponse($response)` if you want to return a redirect or JSON.
3. **Config**: Set `signing_service_id` to your service name if you want to resolve it from the container in the listener; the bundle does not resolve or call it.

### Qualified / eIDAS-style signatures

Same as PKI above, plus:

- Use a **qualified** trust service provider (TSP) for certificates and optionally for TSA.
- Add **LTV** (long-term validation) and/or **timestamp** as required by your jurisdiction.
- The bundle only holds coordinates and audit metadata; all TSP integration is in your listeners and services.

### Batch signing behaviour

- The bundle dispatches **`BATCH_SIGN_REQUESTED`** with the submitted model and request. Your listener must:
  - Decide whether to sign all boxes (draw/upload per box, or one PKI sign).
  - Perform the signing (your services).
  - Redirect or return a response (e.g. flash + redirect, or JSON). If you set a Response on an event, your controller or listener must return it; the default bundle controller does not check `BatchSignRequestedEvent` for a response (it always dispatches then continues with the normal redirect/JSON). So for a custom flow (e.g. “Sign all” redirects to a different page), either handle the submit in your own controller and dispatch the event, or document that the default controller still runs the normal submit flow after the event.

Clarification: the default bundle controller dispatches `BATCH_SIGN_REQUESTED` and then still dispatches `SIGNATURE_COORDINATES_SUBMITTED` and returns the usual redirect/JSON. So your listener can do the batch sign (e.g. persist, call external API) and the user still gets the standard “success” response. If you need “Sign all” to do a different response (e.g. redirect to a signing gateway), implement your own action that dispatches `BATCH_SIGN_REQUESTED` and returns the listener’s response instead of using the bundle’s default controller for that route.

---

## Summary table

| Feature | In bundle | You add |
|--------|-----------|---------|
| **Audit trail** | Model + `audit_metadata`, `AuditMetadata` keys, optional fill from request (IP, user_agent, submitted_at) | user_id, session_id, TSA token, persistence |
| **Timestamp (RFC 3161)** | Config placeholder `tsa_url`, audit key `tsa_token` | TSA client, call TSA in listener, set token in audit |
| **PKI / PAdES** | Events `PDF_SIGN_REQUEST`, `SIGNATURE_COORDINATES_SUBMITTED`; config placeholder `signing_service_id` | Signing library or HSM/signing service, listener that signs and stores |
| **Qualified / eIDAS** | Same as PKI; audit + timestamp support | Qualified TSP, LTV, timestamp in listener |
| **Batch / one-click** | Option `batch_sign_enabled`, “Sign all” button, event `BATCH_SIGN_REQUESTED` | Listener that performs batch sign and optional custom response |

See [ROADMAP](ROADMAP.md) for the high-level list and [CONFIGURATION](CONFIGURATION.md) for `audit`, `tsa_url`, and `signing_service_id`.
