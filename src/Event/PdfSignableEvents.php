<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Event;

/**
 * Event names for the PdfSignable bundle.
 */
final class PdfSignableEvents
{
    /**
     * Dispatched when the signature coordinates form is submitted and valid.
     * Listeners can persist the model, send to a signing service, or add data to the response.
     *
     * @see SignatureCoordinatesSubmittedEvent
     */
    public const SIGNATURE_COORDINATES_SUBMITTED = 'nowo_pdf_signable.signature_coordinates_submitted';

    /**
     * Dispatched before the PDF proxy fetches an external URL.
     * Listeners can modify the URL or set a custom Response to skip the fetch.
     *
     * @see PdfProxyRequestEvent
     */
    public const PDF_PROXY_REQUEST = 'nowo_pdf_signable.pdf_proxy_request';

    /**
     * Dispatched after the PDF proxy successfully fetches the document.
     * Listeners can modify the Response (e.g. add headers, transform content).
     *
     * @see PdfProxyResponseEvent
     */
    public const PDF_PROXY_RESPONSE = 'nowo_pdf_signable.pdf_proxy_response';

    private function __construct()
    {
    }
}
