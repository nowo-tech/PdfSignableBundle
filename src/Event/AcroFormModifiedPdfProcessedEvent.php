<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Event;

use Nowo\PdfSignableBundle\Submission\PdfRequestContext;
use Nowo\PdfSignableBundle\Submission\PdfRequestContextFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched after the process script has run on the modified PDF.
 *
 * Listeners can save the processed PDF to storage, send it elsewhere, etc.
 */
final class AcroFormModifiedPdfProcessedEvent extends Event
{
    public function __construct(
        /** Processed PDF bytes (output of the script) */
        private readonly string $processedPdfContents,
        /** Document key from the request (optional) */
        private readonly ?string $documentKey,
        private readonly Request $request,
    ) {
    }

    /**
     * Returns the processed PDF bytes (output of the process script).
     */
    public function getProcessedPdfContents(): string
    {
        return $this->processedPdfContents;
    }

    /**
     * Returns the document key from the request, or null if not provided.
     */
    public function getDocumentKey(): ?string
    {
        return $this->documentKey;
    }

    /**
     * Returns the HTTP request that triggered the process endpoint.
     *
     * @return Request The POST request with pdf_content and optional document_key
     *
     * @deprecated since 3.10, use {@see getRequestContext()}; removed in 4.0
     */
    public function getRequest(): Request
    {
        return $this->request;
    }

    /**
     * Method, path, locale, client IP and user agent of the process request (3.10, REQ-ARCH-001 rule 3).
     */
    public function getRequestContext(): PdfRequestContext
    {
        return PdfRequestContextFactory::fromRequest($this->request);
    }
}
