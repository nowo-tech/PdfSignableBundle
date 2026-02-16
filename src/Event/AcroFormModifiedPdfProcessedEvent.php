<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Event;

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
     */
    public function getRequest(): Request
    {
        return $this->request;
    }
}
