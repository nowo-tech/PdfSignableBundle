# AcroForm backend extension

This document describes how to **extend the backend** of the bundle to support AcroForm editing: persisting overrides (without modifying the PDF) and, optionally, **rewriting the PDF** with real field changes (position, type, default value).

It is split into **two layers**: one that requires no PDF library, and an optional one that does (e.g. SetaPDF).

---

## 1. Goals

- **Layer 1 — Overrides:** Save and load a JSON blob of overrides (default value, label, UI control type, rect in our view) per document, so the frontend can apply the same experience on later loads. **Does not modify the PDF.**
- **Layer 2 — Modify the PDF:** Accept “patches” (move field, change default value in the PDF, etc.) and return a **new PDF** with those changes applied in the file. Requires a PHP library that can write AcroForm (e.g. SetaPDF) or a custom service.

Both layers are **complementary**: you can have only overrides, only “apply PDF”, or both.

---

## 2. Data model (DTOs)

### 2.1 Single-field patch (frontend → backend)

Structure sent by the frontend for **one** field (for overrides or for PDF modification):

```php
// Nowo\PdfSignableBundle\AcroForm\AcroFormFieldPatch
final class AcroFormFieldPatch
{
    public function __construct(
        /** Field identifier: PDF.js annotation id or PDF field name */
        public readonly string $fieldId,
        /** Rect in PDF points [llx, lly, urx, ury] — optional; for move/resize */
        public readonly ?array $rect = null,
        /** Default value (PDF /DV or our override) */
        public readonly ?string $defaultValue = null,
        /** PDF field type: Tx, Btn, Ch (optional; type change is advanced) */
        public readonly ?string $fieldType = null,
        /** Display label (overrides only) */
        public readonly ?string $label = null,
        /** UI control type: text, textarea, checkbox, select (overrides only) */
        public readonly ?string $controlType = null,
        /** Options for select (overrides only); array of { value, label? } */
        public readonly ?array $options = null,
        /** Page 1-based (for rect or field identification) */
        public readonly ?int $page = null,
        /** When true, hide field in our view (overrides only; apply may remove widget) */
        public readonly ?bool $hidden = null,
        /** PDF field name (/T) — optional; for apply script */
        public readonly ?string $fieldName = null,
        /** Max length for text fields (/MaxLen) — optional; for apply script */
        public readonly ?int $maxLen = null,
        /** Font size in points — optional; for apply script /DA (default appearance) */
        public readonly ?float $fontSize = null,
        /** Font family name (e.g. Helvetica, Arial) — optional; for apply script /DA */
        public readonly ?string $fontFamily = null,
    ) {}
}
```

- **Overrides (Layer 1):** use `fieldId`, `defaultValue`, `label`, `controlType`, `options`, `rect`, `page`, `hidden`, `fontSize`, `fontFamily`, `fontAutoSize` (overrides), `checkboxValueOn`, `checkboxValueOff`, `checkboxIcon`. The backend stores this as-is in JSON.
- **Modify PDF (Layer 2):** use `fieldId`, `rect`, `defaultValue`, `hidden`, `fontSize`, `fontFamily`, and optionally `fieldType`/flags if the library supports it. The service that writes the PDF (or the Python apply script) applies rect, defaultValue, hidden, label (/TU), fieldType (/FT), options (/Opt), maxLen (/MaxLen), and default appearance (/DA) when fontSize or fontFamily is present.

### 2.2 Full overrides (storage)

```php
// Nowo\PdfSignableBundle\AcroForm\AcroFormOverrides
final class AcroFormOverrides
{
    /** @param array<string, array> Map fieldId → override data (defaultValue, label, controlType, rect, options, page) */
    public function __construct(
        public readonly array $overrides,
        public readonly ?string $documentKey = null,
    ) {}

    public static function fromArray(array $data): self { ... }
    public function toArray(): array { ... }
}
```

`documentKey` identifies the document (e.g. hash of the PDF URL, or document ID in your app) for retrieving and updating overrides per document.

---

## 3. Layer 1: Overrides persistence (no PDF library)

### 3.1 Storage contract

Interface that the bundle (session) or the application (DB/Redis) can implement:

```php
// Nowo\PdfSignableBundle\AcroForm\Storage\AcroFormOverridesStorageInterface
namespace Nowo\PdfSignableBundle\AcroForm\Storage;

interface AcroFormOverridesStorageInterface
{
    /** Get overrides for a document key; null if none stored */
    public function get(string $documentKey): ?AcroFormOverrides;

    /** Save overrides for a document */
    public function set(string $documentKey, AcroFormOverrides $overrides): void;

    /** Remove overrides for a document */
    public function remove(string $documentKey): void;
}
```

### 3.2 Default implementation: session

- Service `SessionAcroFormOverridesStorage` uses `RequestStack` and a key like `nowo_pdf_signable.acroform_overrides.{documentKey}`.
- Useful for demos and “same session”; the app can replace it with a DB-backed storage.

### 3.3 How `documentKey` is obtained

- **Option A:** The frontend sends a `document_key` (e.g. hash of the PDF URL, or an ID the app already has). The backend does not validate PDF content; it only uses the key for storage.
- **Option B:** The backend derives the key: e.g. `sha256($pdfUrl)` when `pdf_url` is sent in the body. For external URLs you can reuse the same allowlist as the proxy.

Recommendation: accept `document_key` in the request body (the app can generate it however it likes) and, optionally, allow `pdf_url` to derive the key when that option is enabled (and the URL is allowlisted).

### 3.4 Endpoints (Layer 1)

| Method | Route | Description |
|--------|-------|-------------|
| GET | `/pdf-signable/acroform/overrides` | Query: `document_key=...`. Response: `{ "overrides": { ... } }` or 404. |
| POST | `/pdf-signable/acroform/overrides` | Body: `{ "document_key": "...", "overrides": { "fieldId1": { ... }, ... } }`. Saves and returns 200 or 201. |
| DELETE | `/pdf-signable/acroform/overrides` | Query: `document_key=...`. Removes overrides; 204. |

Protection: same as the rest of the bundle (routes under the app prefix); optionally enforce a role such as `ROLE_PDF_ACROFORM_EDIT` if the app configures it.

---

## 4. Layer 2: Modifying the PDF (AcroForm rewrite)

### 4.1 Editor contract

The bundle defines an interface; the implementation can live in the bundle (if an optional dependency is used) or in the app:

```php
// Nowo\PdfSignableBundle\AcroForm\PdfAcroFormEditorInterface
namespace Nowo\PdfSignableBundle\AcroForm;

interface PdfAcroFormEditorInterface
{
    /**
     * Apply patches to the PDF's AcroForm fields and return the modified PDF as a string.
     *
     * @param string $pdfContents Raw PDF bytes
     * @param list<AcroFormFieldPatch> $patches Patches to apply (rect, defaultValue, etc.)
     * @return string Modified PDF (binary)
     * @throws AcroFormEditorException If the PDF has no form or a patch cannot be applied
     */
    public function applyPatches(string $pdfContents, array $patches): string;
}
```

Domain exception:

```php
namespace Nowo\PdfSignableBundle\AcroForm\Exception;
class AcroFormEditorException extends \RuntimeException {}
```

### 4.2 Apply endpoint

| Method | Route | Description |
|--------|-------|-------------|
| POST | `/pdf-signable/acroform/apply` | Body: `{ "pdf_url": "..." }` or `"pdf_content": "<base64>"`, and `"patches": [ { "fieldId": "...", "rect": [...], "defaultValue": "..." }, ... ] }`. Response: PDF binary (`Content-Type: application/pdf`) or 400/502 with a controlled message. |

Behaviour:

1. Obtain PDF: if `pdf_url` is provided, validate against the **same allowlist and SSRF rules** as the proxy; then fetch. If `pdf_content` is provided, decode base64 (with a size limit, e.g. 20 MB).
2. Convert `patches` to `AcroFormFieldPatch[]` (basic validation).
3. Call `PdfAcroFormEditorInterface::applyPatches($pdfContents, $patches)` (or dispatch an event first).
4. Return the modified PDF (or let an event listener provide the response).

If no service implementing `PdfAcroFormEditorInterface` is registered, the endpoint can return 501 (Not Implemented) or not be registered.

### 4.3 Event for app-provided implementation

To avoid forcing SetaPDF (or any PDF library) into the bundle, an **event** is dispatched with the PDF and patches; an app listener can use its own library and set the response:

```php
// Nowo\PdfSignableBundle\Event\AcroFormApplyRequestEvent
final class AcroFormApplyRequestEvent extends Event
{
    public function __construct(
        private string $pdfContents,
        private array $patches,  // AcroFormFieldPatch[]
        private ?string $modifiedPdf = null,
        private ?\Throwable $error = null,
    ) {}

    public function getPdfContents(): string { ... }
    public function getPatches(): array { ... }
    public function setModifiedPdf(string $pdf): void { ... }
    public function setError(\Throwable $e): void { ... }
    public function hasResponse(): bool { ... }
}
```

The controller, after obtaining the PDF and patches, dispatches the event; if a listener calls `setModifiedPdf()`, that PDF is returned; if it calls `setError()`, a 400/502 is returned. If no listener responds, the controller may call the `PdfAcroFormEditorInterface` service if one is configured (or return 501).

### 4.4 Optional implementation with SetaPDF

- In `composer.json`: **suggest** `setasign/setapdf-formfiller` (and `setasign/setapdf-core` if applicable).
- Optional service: `SetaPdfAcroFormEditor` implementing `PdfAcroFormEditorInterface`:
  - Load the PDF with SetaPDF.
  - For each patch: find the field by name/id (as SetaPDF exposes fields), and if it exists: set `/Rect` when `rect` is provided, default value when `defaultValue` is provided, etc.
  - Save to a string and return it.
- Conditional registration: only when SetaPDF classes are present (compiler pass or `optional` in `services.yaml`).

This way, anyone who wants a real “apply” without implementing the event can install SetaPDF and use the default implementation; anyone using another library can do so via the event.

---

## 5. Configuration (DependencyInjection)

The bundle uses a single **`acroform`** node under `nowo_pdf_signable`. Summary of the main options (platform and apply):

```yaml
nowo_pdf_signable:
  acroform:
    enabled: true
    # Overrides persistence: 'session' or service id implementing AcroFormOverridesStorageInterface
    overrides_storage: 'session'   # or service id: 'app.acroform_overrides.storage'
    # Document key: 'request' (only body/query document_key) or 'derive_from_url' (allow pdf_url and use allowlist)
    document_key_mode: 'request'
    # Enable POST /pdf-signable/acroform/apply (modify PDF; path depends on your route prefix)
    allow_pdf_modify: false
    # Service implementing PdfAcroFormEditorInterface (optional; if unset and allow_pdf_modify true, event is used)
    editor_service_id: null   # e.g. 'Nowo\PdfSignableBundle\AcroForm\SetaPdfAcroFormEditor'
    # Max PDF size for apply (bytes)
    max_pdf_size: 20971520   # 20 MiB
    # Max patches per apply request
    max_patches: 500
```

- If `acroform.enabled` is `false`, overrides/apply routes and services are not registered.
- `overrides_storage: session` registers `SessionAcroFormOverridesStorage` and aliases it to the interface.
- `allow_pdf_modify: true` exposes the apply endpoint; the implementation can be `editor_service_id` or event-only.

For the **full list** of options (including `process_script`, `process_script_command`, `fields_extractor_script`, `default_config_alias`, `configs`, and editor defaults such as `label_mode`, `font_sizes`), see [CONFIGURATION](CONFIGURATION.md#acroform).

---

## 6. Security and limits

- **Overrides:** Validate that `document_key` has an acceptable format (length, character set) to avoid storage abuse. If the key is derived from `pdf_url`, apply the same allowlist and SSRF checks as the proxy.
- **Apply:**
  - Validate `pdf_url` with the allowlist and SSRF check, same as the proxy.
  - Enforce a size limit on `pdf_content` (base64 decoded) with `max_pdf_size`.
  - Limit the number of patches (e.g. 500) to avoid DoS.
- Optional: attribute/annotation to require a role (e.g. `ROLE_PDF_ACROFORM_EDIT`) on acroform routes; by default it may be omitted so existing apps are not broken.

---

## 7. Component summary

| Component | Layer | Description |
|-----------|-------|-------------|
| `AcroFormFieldPatch` | Both | DTO for a single field patch |
| `AcroFormOverrides` | 1 | DTO for full overrides |
| `AcroFormOverridesStorageInterface` | 1 | Storage contract |
| `SessionAcroFormOverridesStorage` | 1 | Session implementation |
| `PdfAcroFormEditorInterface` | 2 | PDF rewrite contract |
| `AcroFormEditorException` | 2 | Domain exception |
| `AcroFormApplyRequestEvent` | 2 | Event for custom implementation |
| `AcroFormOverridesController` | 1 & 2 | GET/POST/DELETE overrides; POST apply |
| `SetaPdfAcroFormEditor` (optional) | 2 | SetaPDF-based implementation |
| Config `acroform.*` | Both | enabled, overrides_storage, document_key_mode, allow_pdf_modify, editor_service_id, max_pdf_size, max_patches |

---

## 8. Suggested implementation order

1. **Config and parameters** in `Configuration.php` and `PdfSignableExtension.php` for `acroform`.
2. **DTOs:** `AcroFormFieldPatch`, `AcroFormOverrides` (with `fromArray`/`toArray`).
3. **Layer 1:** `AcroFormOverridesStorageInterface`, `SessionAcroFormOverridesStorage`, controller (or routes on an existing controller) for GET/POST/DELETE overrides; conditional registration when `enabled`.
4. **Layer 2:** `PdfAcroFormEditorInterface`, `AcroFormEditorException`, `AcroFormApplyRequestEvent`; POST apply endpoint (obtain PDF, validate, dispatch event and/or call editor); register only when `allow_pdf_modify`.
5. **Optional:** `SetaPdfAcroFormEditor` in a separate package or in the bundle with a `suggest` and conditional registration.

This completes the backend extension for **AcroForm editing**: persistent overrides (view and default values in our layer) and, if the app wants it, real PDF modification via the editor or the event.

---

## 9. Apply script (Python) and process endpoint

### 9.1 Apply script

When you prefer **Python** to apply patches (e.g. with pypdf) instead of a PHP editor service or event:

- **Dependencies:** **Python 3.9+** and **pypdf** (`pip install pypdf`). The bundle does not depend on Python; these are only required if you configure `apply_script` to use the bundled script or your own Python script that uses pypdf.
- **Config:** `acroform.apply_script`: path to a Python script. `acroform.apply_script_command`: executable to run it (default `python3`; use full path if not in PATH).
- **Contract:** The script is invoked with `--pdf <path>` and `--patches <path>` (JSON file). It must write the **modified PDF to stdout** (binary).
- **Bundle script:** `scripts/apply_acroform_patches.py` applies `rect`, `defaultValue`, `hidden` (removes widget), `label` (/TU), `fieldType` (/FT), `options` (/Opt), `maxLen` (/MaxLen), and `fontSize`/`fontFamily` (default appearance /DA) per patch. FieldId can be `p{N}-{idx}` (page and annotation index) or a field name.
- A listener runs this script when no other listener or editor has set the modified PDF on `AcroFormApplyRequestEvent`.

**How Symfony passes the PDF to the script:** Symfony does **not** stream the PDF via stdin. It writes the PDF bytes to a **temporary file** and the patches to a second **temporary JSON file**, then runs e.g. `python3 /path/to/apply_acroform_patches.py --pdf /tmp/pdf_apply_xxxx --patches /tmp/patches_xxxx`. The script **reads** those two files and must output the modified PDF to **stdout** (binary). Symfony captures that stdout and sends it as the HTTP response (so the browser receives the PDF for download). The script does **not** write the result to a file; there is no “output path” for apply. The temp input files are deleted after the process finishes.

### 9.2 Process script and “Submit / Process”

After the user has applied changes and obtained the modified PDF, you can run a **process script** (e.g. fill, sign, flatten) and then let PHP save or use the result:

- **Config:** `acroform.process_script`: path to a Python script. `acroform.process_script_command`: executable to run it (default `python3`; use full path if not in PATH).
- **Endpoint:** POST `/pdf-signable/acroform/process`. Body: `pdf_content` (base64, required), `document_key` (optional). The bundle writes the PDF to a temp file, runs the script with `--input <path>` and `--output <path>` (and `--document-key` if provided). The script must write the result to the output path. The bundle then dispatches **`AcroFormModifiedPdfProcessedEvent`** with the processed PDF bytes and the request; a listener in your app can save the file or send it elsewhere.
- **Response:** 200 JSON `{ success: true, document_key?: string }`, or 200 `application/pdf` if the client sends `Accept: application/pdf`.
- **Bundle script:** `scripts/process_modified_pdf.py` is a stub that copies input to output; replace it with your own logic.

### 9.3 Frontend flow

1. User edits overrides (hide field, edit rect/type, etc.) and optionally **Save overrides**.
2. **Apply to PDF:** frontend POSTs `pdf_url` + `patches` to `/pdf-signable/acroform/apply` (or your prefix); backend returns the modified PDF (binary). The panel stores it and can trigger a download.
3. **Submit / Process:** frontend POSTs the modified PDF as `pdf_content` (base64) to `/pdf-signable/acroform/process`; backend runs the process script and dispatches the event; your listener saves or processes the result.

---

## 10. Getting the modified PDF in your project and uploading to storage (e.g. Amazon S3)

Besides letting the user **download** the modified PDF from the browser, you often need to **transform the PDF on the server** and **store it** (e.g. Amazon S3, your own storage). You can do that in two main ways: (A) call the apply endpoint from your backend and then upload the result, or (B) use the process endpoint and a listener to receive the (processed) PDF and upload it.

### 10.1 Option A: Your backend calls the apply endpoint, then uploads the PDF

Use Symfony’s `HttpClient` to POST to the bundle’s apply endpoint with the same body the frontend would send (`pdf_url` or `pdf_content` + `patches`). You get back the modified PDF as binary; then upload that to S3 (or anywhere).

**When to use:** You have the PDF bytes or URL and the patches in PHP (e.g. from a queue job, an API, or after the user clicked “Save” and you stored patches in DB). You want to produce the modified PDF and store it in one flow.

**Example: service that transforms the PDF and uploads to S3**

```php
// src/Service/AcroFormPdfToS3Service.php
namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class AcroFormPdfToS3Service
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $applyUrl,  // e.g. 'https://your-app.com/pdf-signable/acroform/apply'
        private readonly YourS3Uploader $s3Uploader,
    ) {}

    /**
     * Calls the bundle apply endpoint with pdf_content + patches, then uploads the modified PDF to S3.
     *
     * @param string $pdfContents Raw PDF bytes
     * @param array<int, array> $patches Array of patch arrays (fieldId, rect, defaultValue, ...)
     * @return string S3 key or URL of the uploaded file
     */
    public function applyAndUploadToS3(string $pdfContents, array $patches): string
    {
        $response = $this->httpClient->request('POST', $this->applyUrl, [
            'json' => [
                'pdf_content' => base64_encode($pdfContents),
                'patches' => $patches,
            ],
        ]);

        $modifiedPdfBytes = $response->getContent();
        $key = 'documents/' . uniqid('acroform_', true) . '.pdf';

        $this->s3Uploader->upload($key, $modifiedPdfBytes, 'application/pdf');

        return $key;
    }
}
```

Configure `applyUrl` in your services (e.g. from a env var or route). Ensure the request is authenticated if your apply route is protected (e.g. same user/session or internal token). The bundle’s apply endpoint returns `Content-Type: application/pdf` and the PDF binary.

If you have a **PDF URL** instead of bytes, send `pdf_url` instead of `pdf_content` (and ensure the URL is allowlisted for the proxy/apply):

```php
$response = $this->httpClient->request('POST', $this->applyUrl, [
    'json' => [
        'pdf_url' => $pdfUrl,
        'patches' => $patches,
    ],
]);
$modifiedPdfBytes = $response->getContent();
```

### 10.2 Option B: Process endpoint + listener: receive the (processed) PDF and upload to S3

The frontend (or your app) POSTs the **modified** PDF to `/pdf-signable/acroform/process` as `pdf_content` (base64). The bundle runs your `process_script` (e.g. to flatten, fill, sign) and then dispatches **`AcroFormModifiedPdfProcessedEvent`** with the **processed** PDF bytes. A listener in your project can upload that PDF to Amazon S3 (or another store).

**When to use:** The user has already applied patches in the browser and either downloaded the PDF or your frontend sends it to process. You want to run a server-side “process” step (e.g. flatten, add watermark) and then persist the result to S3.

**Example: event listener that uploads the processed PDF to S3**

```php
// src/EventListener/AcroFormProcessedPdfUploadListener.php
namespace App\EventListener;

use Nowo\PdfSignableBundle\Event\AcroFormModifiedPdfProcessedEvent;
use App\Service\YourS3Uploader;

final class AcroFormProcessedPdfUploadListener
{
    public function __construct(
        private readonly YourS3Uploader $s3Uploader,
    ) {}

    public function __invoke(AcroFormModifiedPdfProcessedEvent $event): void
    {
        $pdfBytes = $event->getProcessedPdfContents();
        $documentKey = $event->getDocumentKey() ?? uniqid('doc_', true);
        $s3Key = 'acroform-processed/' . $documentKey . '.pdf';

        $this->s3Uploader->upload($s3Key, $pdfBytes, 'application/pdf');

        // Optional: store $s3Key in DB, send notification, etc.
    }
}
```

Register the listener for `AcroFormModifiedPdfProcessedEvent` (or `nowo_pdf_signable.acroform_modified_pdf_processed`) in `services.yaml`:

```yaml
services:
    App\EventListener\AcroFormProcessedPdfUploadListener:
        tags:
            - { name: kernel.event_listener, event: nowo_pdf_signable.acroform_modified_pdf_processed, method: __invoke }
```

**Summary**

| Goal | Approach |
|------|----------|
| Transform PDF (apply patches) and get bytes in PHP | POST to `/pdf-signable/acroform/apply` with `pdf_content` (or `pdf_url`) + `patches`; use `HttpClient`; response body = modified PDF. |
| Then upload to Amazon S3 | Use your S3 client (e.g. AWS SDK, `league/flysystem-aws-s3-v3`) with the PDF bytes from the apply response or from the process event. |
| Run a “process” step (e.g. flatten) and then store the result | Use POST `/pdf-signable/acroform/process`; implement a listener for `AcroFormModifiedPdfProcessedEvent`; in the listener, call `$event->getProcessedPdfContents()` and upload to S3. |

See [CONFIGURATION](CONFIGURATION.md) for `acroform.apply_script`, `acroform.process_script`, and `allow_pdf_modify`.

---

## 11. Implementation status

Implemented in the bundle (steps 1–4 above):

- **Config:** `acroform` node in `Configuration.php` with platform options (`enabled`, `overrides_storage`, `document_key_mode`, `allow_pdf_modify`, `editor_service_id`, `max_pdf_size`, `max_patches`, `apply_script`, `apply_script_command`, `process_script`, `process_script_command`), editor defaults (`min_field_width`, `min_field_height`, `label_mode`, etc.), `default_config_alias`, and `configs` (by alias). Parameters and conditional loading in `PdfSignableExtension.php`.
- **DTOs:** `AcroFormFieldPatch`, `AcroFormOverrides` in `src/AcroForm/`.
- **Layer 1:** `AcroFormOverridesStorageInterface`, `SessionAcroFormOverridesStorage`; `AcroFormOverridesController` with GET/POST/DELETE `/pdf-signable/acroform/overrides`. Session storage by default; alias is configurable.
- **Layer 2:** `PdfAcroFormEditorInterface`, `AcroFormEditorException`, `AcroFormApplyRequestEvent`; POST `/pdf-signable/acroform/apply` (pdf_url or pdf_content + patches); `ACROFORM_APPLY_REQUEST` event; optional service implementing the interface.

Pending (optional): `SetaPdfAcroFormEditor` implementation and `suggest` in `composer.json`.
