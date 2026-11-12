# Events

The bundle dispatches events at key moments so you can extend behavior without modifying the bundle.

## Table of contents

- [Event list](#event-list)
- [SignatureCoordinatesSubmittedEvent](#signaturecoordinatessubmittedevent)
  - [Payload](#payload)
  - [Example: persist coordinates](#example-persist-coordinates)
  - [Using the form in your own controller](#using-the-form-in-your-own-controller)
- [BatchSignRequestedEvent](#batchsignrequestedevent)
  - [Payload](#payload-1)
  - [Example](#example)
- [PdfSignRequestEvent](#pdfsignrequestevent)
  - [Payload](#payload-2)
  - [Example](#example-1)
- [PdfProxyRequestEvent](#pdfproxyrequestevent)
  - [Payload](#payload-3)
  - [Example: serve from cache](#example-serve-from-cache)
- [PdfProxyResponseEvent](#pdfproxyresponseevent)
  - [Payload](#payload-4)
  - [Example: add a header](#example-add-a-header)
- [AcroFormModifiedPdfProcessedEvent](#acroformmodifiedpdfprocessedevent)
  - [Payload](#payload-5)
  - [Example](#example-2)
- [Frontend (browser) custom events — AcroForm editor ↔ viewer](#frontend-browser-custom-events--acroform-editor--viewer)
- [Event classes reference](#event-classes-reference)

## Event list

| Event | When | Use case |
|-------|------|----------|
| `SignatureCoordinatesSubmittedEvent` | After the signature form is submitted and valid | Persist coordinates, send to a signing service, add audit metadata, log |
| `BatchSignRequestedEvent` | Form submitted with "Sign all" (`batch_sign=1`) | Perform batch signing (draw/upload or PKI) in one go |
| `PdfSignRequestEvent` | When your code requests a digital (PKI) signature | Call your signing service/HSM, set signed PDF or custom response |
| `PdfProxyRequestEvent` | Before the PDF proxy fetches an external URL | Change URL, serve from cache, short-circuit with custom response |
| `PdfProxyResponseEvent` | After the proxy successfully fetches the PDF | Modify response (headers, content), log |
| `AcroFormApplyRequestEvent` | When POST `/pdf-signable/acroform/apply` is called with PDF + patches | Set modified PDF or error; implement PDF rewrite (e.g. with SetaPDF) without the bundle depending on it. See [ACROFORM_BACKEND_EXTENSION](ACROFORM_BACKEND_EXTENSION.md). |
| `AcroFormModifiedPdfProcessedEvent` | After the process script (e.g. fill/sign) runs when POST `/pdf-signable/acroform/process` is used | Save the processed PDF, send it elsewhere, or update your app state. See [ACROFORM_BACKEND_EXTENSION](ACROFORM_BACKEND_EXTENSION.md). |

---

## SignatureCoordinatesSubmittedEvent

**Name:** `nowo_pdf_signable.signature_coordinates_submitted`

Dispatched when the signature coordinates form is submitted and valid (bundle controller or your own controller if you dispatch it).

### Payload

- `getSubmission(): SignatureSubmission` (3.10) — immutable snapshot: `pdfUrl`, `unit`, `origin`, `boxes`
  (`SignatureBox`: `name`, `page`, `x`, `y`, `width`, `height`, `angle`, `signedAt`, `getSignatureData()`),
  `signingConsent`, `getAuditMetadata()` / `getAuditValue()`, `toArray()` (same array as the model's).
- `getRequestContext(): PdfRequestContext` (3.10) — `method`, `path`, `locale`, `getClientIp()`, `getUserAgent()`.
- `getCoordinates(): SignatureCoordinatesModel` — the submitted model (pdfUrl, unit, origin, signatureBoxes).
  **Deprecated since 3.10** (use `getSubmission()`), removed in 4.0.
- `getRequest(): Request` — the current request. **Deprecated since 3.10** (use `getRequestContext()`), removed in 4.0.

### Example: persist coordinates

```php
use Nowo\PdfSignableBundle\Event\PdfSignableEvents;
use Nowo\PdfSignableBundle\Event\SignatureCoordinatesSubmittedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: PdfSignableEvents::SIGNATURE_COORDINATES_SUBMITTED)]
class PersistSignatureCoordinatesListener
{
    public function __invoke(SignatureCoordinatesSubmittedEvent $event): void
    {
        $submission = $event->getSubmission();
        // Persist $submission->pdfUrl, $submission->boxes (name, page, x, y, …), $submission->getAuditMetadata(), etc.
    }
}
```

### Using the form in your own controller

If you use `SignatureCoordinatesType` in your app controller, dispatch the event yourself after a valid submit:

```php
use Nowo\PdfSignableBundle\Event\PdfSignableEvents;
use Nowo\PdfSignableBundle\Event\SignatureCoordinatesSubmittedEvent;

if ($form->isSubmitted() && $form->isValid()) {
    $model = $form->getData();
    $this->eventDispatcher->dispatch(
        new SignatureCoordinatesSubmittedEvent($model, $request),
        PdfSignableEvents::SIGNATURE_COORDINATES_SUBMITTED
    );
    // Or, in a DDD host, map it and call your own use case:
    // $this->signDocument->handle(SignatureSubmission::fromModel($model));
    // redirect or return JSON
}
```

---

## BatchSignRequestedEvent

**Name:** `nowo_pdf_signable.batch_sign_requested`

Dispatched when the form is submitted with the **Sign all** button (`batch_sign=1`). The bundle does not perform the actual signing; your listener can call your signing service or handle the flow.

### Payload

- `getSubmission(): SignatureSubmission`, `getRequestContext(): PdfRequestContext` (3.10) — as above.
- `getCoordinates(): SignatureCoordinatesModel` — the submitted model (all boxes). **Deprecated since 3.10.**
- `getRequest(): Request` — the POST request. **Deprecated since 3.10.**
- `getBoxTarget(): ?array` — `null` = all boxes, or list of indices/names to sign.

### Example

See [SIGNING_ADVANCED](SIGNING_ADVANCED.md). Enable the button with form option `batch_sign_enabled: true`.

---

## PdfSignRequestEvent

**Name:** `nowo_pdf_signable.pdf_sign_request`

Dispatched when your application requests a digital (PKI/PAdES) signature. The bundle does not call any signing library; your listener calls your signing service or HSM and can set a custom response.

### Payload

- Constructor (3.10): `new PdfSignRequestEvent(SignatureCoordinatesModel|SignatureSubmission $coordinates, ?Request $request = null, array $options = [])` — create it from a `SignatureSubmission` without a request (command handler, Messenger message).
- `getSubmission(): SignatureSubmission`, `getRequestContext(): ?PdfRequestContext` (null without a request), `hasRequest(): bool` (3.10).
- `setResult(?string)` / `getResult(): ?string` (3.10) — the signing outcome (signed PDF bytes or a storage reference).
- `getCoordinates(): SignatureCoordinatesModel` — coordinates and boxes. **Deprecated since 3.10.**
- `getRequest(): Request` — current request (throws without one). **Deprecated since 3.10.**
- `getOptions(): array` — optional (e.g. signing_profile, box_indices).
- `setResponse(?Response): void` / `getResponse(): ?Response` — a response to return. **Deprecated since 3.10** (use `setResult()` and build the response in your controller).

### Example

Dispatch from your code when the user clicks "Sign with certificate", then in the listener call your signing service and `$event->setResult($signedPdfPath)`.

---

## PdfProxyRequestEvent

**Name:** `nowo_pdf_signable.pdf_proxy_request`

Dispatched before the proxy fetches the PDF from the given URL.

### Payload

- `getUrl(): string` / `setUrl(string): void` — URL to fetch (you can change it).
- `getRequestContext(): PdfRequestContext` (3.10) — method, path, locale, client IP, user agent.
- `respondWith(int $status, string $body = '', array $headers = []): void` (3.10) — skip the HTTP fetch and answer with this (e.g. serve from cache).
- `getRequest(): Request` — the request to the proxy. **Deprecated since 3.10.**
- `setResponse(Response): void` — set a custom response to skip the HTTP fetch. **Deprecated since 3.10** (use `respondWith()`).
- `getResponse(): ?Response` — the custom response. **Deprecated since 3.10.1** (use the readers below).
- `hasResponse(): bool` — whether a custom response was set.
- `getResponseStatusCode(): ?int`, `getResponseContent(): ?string`, `getResponseHeader(string): ?string` (3.10.1) —
  what an earlier listener set, without HttpFoundation types (`getResponseContent()` is `null` for a streamed response).

### Example: serve from cache

```php
use Nowo\PdfSignableBundle\Event\PdfProxyRequestEvent;
use Nowo\PdfSignableBundle\Event\PdfSignableEvents;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: PdfSignableEvents::PDF_PROXY_REQUEST)]
class PdfProxyCacheListener
{
    public function __invoke(PdfProxyRequestEvent $event): void
    {
        $url = $event->getUrl();
        // If you have cached content for $url:
        // $event->respondWith(200, $cachedContent, ['Content-Type' => 'application/pdf']);
    }
}
```

---

## PdfProxyResponseEvent

**Name:** `nowo_pdf_signable.pdf_proxy_response`

Dispatched after the proxy successfully fetches the PDF. You can replace or wrap the response.

### Payload

- `getUrl(): string` — URL that was fetched.
- `getRequestContext(): PdfRequestContext` (3.10).
- `getContent(): string` / `setContent(string): void`, `getStatusCode(): int`, `setHeader(string, string): void` (3.10) — read or change the PDF that is sent.
- `getRequest(): Request` — the request to the proxy. **Deprecated since 3.10.**
- `getResponse(): Response` / `setResponse(Response): void` — the response (you can replace it). **Deprecated since 3.10.**

### Example: add a header

```php
use Nowo\PdfSignableBundle\Event\PdfProxyResponseEvent;
use Nowo\PdfSignableBundle\Event\PdfSignableEvents;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: PdfSignableEvents::PDF_PROXY_RESPONSE)]
class PdfProxyHeaderListener
{
    public function __invoke(PdfProxyResponseEvent $event): void
    {
        $event->setHeader('X-Pdf-Source', $event->getUrl());
    }
}
```

---

## AcroFormModifiedPdfProcessedEvent

**Name:** `nowo_pdf_signable.acroform_modified_pdf_processed`

Dispatched after the AcroForm process script (config `acroform.process_script`) runs successfully. Use it to save the processed PDF or send it to another service.

### Payload

- `getProcessedPdfContents(): string` — the processed PDF bytes (script output).
- `getDocumentKey(): ?string` — document key from the request, if provided.
- `getRequestContext(): PdfRequestContext` (3.10).
- `getRequest(): Request` — the current request. **Deprecated since 3.10.**

### Example

See [ACROFORM_BACKEND_EXTENSION](ACROFORM_BACKEND_EXTENSION.md). Register a listener to persist the PDF or trigger a download.

---

## Frontend (browser) custom events — AcroForm editor ↔ viewer

The AcroForm editor (panel) and the PDF viewer coordinate via **CustomEvent** on `window`. These are browser events, not Symfony. For full flows and diagrams see [ACROFORM_FLOWS](ACROFORM_FLOWS.md).

| Event | Source | Target | Effect |
|-------|--------|--------|--------|
| `pdf-signable-acroform-move-resize` | Editor | Viewer | Shows move/resize overlay for the given field. |
| `pdf-signable-acroform-move-resize-close` | Editor | Viewer | Removes the overlay and clears move/resize state. |
| `pdf-signable-acroform-move-resize-opened` | Viewer | Editor | Notifies that the overlay is visible; the editor closes the modal (single active context). |
| `pdf-signable-acroform-overrides-updated` | Editor | Viewer | Re-renders the PDF with current overrides; restores overlay if one was active. |
| `pdf-signable-acroform-rect-changed` | Viewer | Editor | Updates `draftOverrides[fieldId].rect` after drag/release on the overlay. |
| `pdf-signable-acroform-add-field-place` | Viewer | Editor | Click on empty PDF area: creates override for new field and opens modal. |
| `pdf-signable-acroform-field-focused` | Viewer | Editor | Highlights outline and row when focusing an input on the PDF. |
| `pdf-signable-acroform-fields-updated` | Viewer | Editor | PDF field list updated. |
| `pdf-signable-acroform-edit-mode` | Editor | Viewer | Toggles edit mode class on the widget. |

**Global variables** (`window`): `__pdfSignableAcroFormOverrides`, `__pdfSignableAcroFormFields`, `__pdfSignableAcroFormEditMode`, `__pdfSignableAcroFormMoveResizeFieldId`, `__pdfSignableAcroFormMoveResizePage`. Details in [ACROFORM_FLOWS](ACROFORM_FLOWS.md#9-frontend-events-editor--viewer).

---

## Event classes reference

- `Nowo\PdfSignableBundle\Event\PdfSignableEvents` — event name constants.
- `Nowo\PdfSignableBundle\Event\SignatureCoordinatesSubmittedEvent`
- `Nowo\PdfSignableBundle\Event\BatchSignRequestedEvent`
- `Nowo\PdfSignableBundle\Event\PdfSignRequestEvent`
- `Nowo\PdfSignableBundle\Event\PdfProxyRequestEvent`
- `Nowo\PdfSignableBundle\Event\PdfProxyResponseEvent`
- `Nowo\PdfSignableBundle\Event\AcroFormApplyRequestEvent` — `ACROFORM_APPLY_REQUEST`; set `setModifiedPdf()` or `setError()` to respond.
- `Nowo\PdfSignableBundle\Event\AcroFormModifiedPdfProcessedEvent` — `ACROFORM_MODIFIED_PDF_PROCESSED`; payload: `getProcessedPdfContents()`, `getDocumentKey()`, `getRequestContext()` (`getRequest()` deprecated).

Listeners can be registered as services with the `kernel.event_listener` tag or using the `#[AsEventListener]` attribute (Symfony 6.3+).
