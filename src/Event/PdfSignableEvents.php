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

    /**
     * Dispatched when the form is submitted with batch_sign=1 (e.g. "Sign all" button).
     * Listeners can perform batch signing (draw/upload or PKI) and redirect or return a response.
     *
     * @see BatchSignRequestedEvent
     */
    public const BATCH_SIGN_REQUESTED = 'nowo_pdf_signable.batch_sign_requested';

    /**
     * Dispatched when the app requests a digital (PKI/PAdES) signature.
     * Listeners call the signing service/HSM and set the result on the event.
     *
     * @see PdfSignRequestEvent
     */
    public const PDF_SIGN_REQUEST = 'nowo_pdf_signable.pdf_sign_request';

    /**
     * Non-instantiable: only event name constants are used.
     */
    private function __construct()
    {
    }
}
