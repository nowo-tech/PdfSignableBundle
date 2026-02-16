<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\Event;

use Nowo\PdfSignableBundle\Event\AcroFormModifiedPdfProcessedEvent;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class AcroFormModifiedPdfProcessedEventTest extends TestCase
{
    public function testGetProcessedPdfContentsAndDocumentKey(): void
    {
        $processedPdf = '%PDF-1.4 processed';
        $documentKey = 'doc-123';
        $request = Request::create('/process', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['pdf_content' => 'e0=', 'document_key' => $documentKey], JSON_THROW_ON_ERROR));

        $event = new AcroFormModifiedPdfProcessedEvent($processedPdf, $documentKey, $request);

        self::assertSame($processedPdf, $event->getProcessedPdfContents());
        self::assertSame($documentKey, $event->getDocumentKey());
        self::assertSame($request, $event->getRequest());
    }

    public function testGetDocumentKeyNull(): void
    {
        $request = Request::create('/process', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['pdf_content' => 'e0='], JSON_THROW_ON_ERROR));

        $event = new AcroFormModifiedPdfProcessedEvent('%PDF', null, $request);

        self::assertNull($event->getDocumentKey());
    }
}
