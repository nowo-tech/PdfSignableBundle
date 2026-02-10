# Events

The bundle dispatches events at key moments so you can extend behavior without modifying the bundle.

## Event list

| Event | When | Use case |
|-------|------|----------|
| `SignatureCoordinatesSubmittedEvent` | After the signature form is submitted and valid | Persist coordinates, send to a signing service, add audit metadata, log |
| `BatchSignRequestedEvent` | Form submitted with "Sign all" (`batch_sign=1`) | Perform batch signing (draw/upload or PKI) in one go |
| `PdfSignRequestEvent` | When your code requests a digital (PKI) signature | Call your signing service/HSM, set signed PDF or custom response |
| `PdfProxyRequestEvent` | Before the PDF proxy fetches an external URL | Change URL, serve from cache, short-circuit with custom response |
| `PdfProxyResponseEvent` | After the proxy successfully fetches the PDF | Modify response (headers, content), log |

---

## SignatureCoordinatesSubmittedEvent

**Name:** `nowo_pdf_signable.signature_coordinates_submitted`

Dispatched when the signature coordinates form is submitted and valid (bundle controller or your own controller if you dispatch it).

### Payload

- `getCoordinates(): SignatureCoordinatesModel` — the submitted model (pdfUrl, unit, origin, signatureBoxes).
- `getRequest(): Request` — the current request.

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
        $model = $event->getCoordinates();
        // Persist $model->getPdfUrl(), $model->getSignatureBoxes(), etc.
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
    // redirect or return JSON
}
```

---

## BatchSignRequestedEvent

**Name:** `nowo_pdf_signable.batch_sign_requested`

Dispatched when the form is submitted with the **Sign all** button (`batch_sign=1`). The bundle does not perform the actual signing; your listener can call your signing service or handle the flow.

### Payload

- `getCoordinates(): SignatureCoordinatesModel` — the submitted model (all boxes).
- `getRequest(): Request` — the POST request.
- `getBoxTarget(): ?array` — `null` = all boxes, or list of indices/names to sign.

### Example

See [SIGNING_ADVANCED](SIGNING_ADVANCED.md). Enable the button with form option `batch_sign_enabled: true`.

---

## PdfSignRequestEvent

**Name:** `nowo_pdf_signable.pdf_sign_request`

Dispatched when your application requests a digital (PKI/PAdES) signature. The bundle does not call any signing library; your listener calls your signing service or HSM and can set a custom response.

### Payload

- `getCoordinates(): SignatureCoordinatesModel` — coordinates and boxes.
- `getRequest(): Request` — current request.
- `getOptions(): array` — optional (e.g. signing_profile, box_indices).
- `setResponse(?Response): void` — set a response to return (e.g. redirect to certificate picker).
- `getResponse(): ?Response` — response set by a listener.

### Example

Dispatch from your code when the user clicks "Sign with certificate", then in the listener call your signing service and optionally `$event->setResponse($response)`.

---

## PdfProxyRequestEvent

**Name:** `nowo_pdf_signable.pdf_proxy_request`

Dispatched before the proxy fetches the PDF from the given URL.

### Payload

- `getUrl(): string` / `setUrl(string): void` — URL to fetch (you can change it).
- `getRequest(): Request` — the request to the proxy.
- `setResponse(Response): void` — set a custom response to skip the HTTP fetch (e.g. serve from cache).
- `hasResponse(): bool` — whether a custom response was set.

### Example: serve from cache

```php
use Nowo\PdfSignableBundle\Event\PdfProxyRequestEvent;
use Nowo\PdfSignableBundle\Event\PdfSignableEvents;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;

#[AsEventListener(event: PdfSignableEvents::PDF_PROXY_REQUEST)]
class PdfProxyCacheListener
{
    public function __invoke(PdfProxyRequestEvent $event): void
    {
        $url = $event->getUrl();
        // If you have cached content for $url:
        // $event->setResponse(new Response($cachedContent, 200, ['Content-Type' => 'application/pdf']));
    }
}
```

---

## PdfProxyResponseEvent

**Name:** `nowo_pdf_signable.pdf_proxy_response`

Dispatched after the proxy successfully fetches the PDF. You can replace or wrap the response.

### Payload

- `getUrl(): string` — URL that was fetched.
- `getRequest(): Request` — the request to the proxy.
- `getResponse(): Response` / `setResponse(Response): void` — the response (you can replace it).

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
        $response = $event->getResponse();
        $response->headers->set('X-Pdf-Source', $event->getUrl());
        $event->setResponse($response);
    }
}
```

---

## Event classes reference

- `Nowo\PdfSignableBundle\Event\PdfSignableEvents` — event name constants.
- `Nowo\PdfSignableBundle\Event\SignatureCoordinatesSubmittedEvent`
- `Nowo\PdfSignableBundle\Event\BatchSignRequestedEvent`
- `Nowo\PdfSignableBundle\Event\PdfSignRequestEvent`
- `Nowo\PdfSignableBundle\Event\PdfProxyRequestEvent`
- `Nowo\PdfSignableBundle\Event\PdfProxyResponseEvent`

Listeners can be registered as services with the `kernel.event_listener` tag or using the `#[AsEventListener]` attribute (Symfony 6.3+).
