<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\Event;

use Nowo\PdfSignableBundle\Event\PdfSignableEvents;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Tests for PdfSignableEvents constants.
 */
final class PdfSignableEventsTest extends TestCase
{
    public function testSignatureCoordinatesSubmittedConstant(): void
    {
        self::assertStringContainsString('signature_coordinates_submitted', PdfSignableEvents::SIGNATURE_COORDINATES_SUBMITTED);
    }

    public function testPdfProxyRequestConstant(): void
    {
        self::assertStringContainsString('pdf_proxy_request', PdfSignableEvents::PDF_PROXY_REQUEST);
    }

    public function testPdfProxyResponseConstant(): void
    {
        self::assertStringContainsString('pdf_proxy_response', PdfSignableEvents::PDF_PROXY_RESPONSE);
    }

    public function testBatchSignRequestedConstant(): void
    {
        self::assertStringContainsString('batch_sign_requested', PdfSignableEvents::BATCH_SIGN_REQUESTED);
    }

    public function testPdfSignRequestConstant(): void
    {
        self::assertStringContainsString('pdf_sign_request', PdfSignableEvents::PDF_SIGN_REQUEST);
    }

    public function testAcroFormApplyRequestConstant(): void
    {
        self::assertStringContainsString('acroform_apply_request', PdfSignableEvents::ACROFORM_APPLY_REQUEST);
    }

    public function testAcroFormModifiedPdfProcessedConstant(): void
    {
        self::assertStringContainsString('acroform_modified_pdf_processed', PdfSignableEvents::ACROFORM_MODIFIED_PDF_PROCESSED);
    }

    public function testClassIsNotInstantiable(): void
    {
        $ref = new ReflectionClass(PdfSignableEvents::class);
        $constructor = $ref->getConstructor();
        self::assertNotNull($constructor);
        self::assertTrue($constructor->isPrivate());
    }
}
