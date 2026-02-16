<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\Event;

use Nowo\PdfSignableBundle\AcroForm\AcroFormFieldPatch;
use Nowo\PdfSignableBundle\Event\AcroFormApplyRequestEvent;
use PHPUnit\Framework\TestCase;

final class AcroFormApplyRequestEventTest extends TestCase
{
    public function testGetPdfContentsAndPatches(): void
    {
        $pdf = '%PDF-1.4 contents';
        $patch = AcroFormFieldPatch::fromArray(['fieldId' => 'f1', 'defaultValue' => 'x']);
        $event = new AcroFormApplyRequestEvent($pdf, [$patch]);

        self::assertSame($pdf, $event->getPdfContents());
        self::assertCount(1, $event->getPatches());
        self::assertSame('f1', $event->getPatches()[0]->fieldId);
    }

    public function testSetAndGetModifiedPdf(): void
    {
        $event = new AcroFormApplyRequestEvent('%PDF', []);
        self::assertNull($event->getModifiedPdf());

        $event->setModifiedPdf('%PDF modified');
        self::assertSame('%PDF modified', $event->getModifiedPdf());
    }

    public function testSetAndGetError(): void
    {
        $event = new AcroFormApplyRequestEvent('%PDF', []);
        self::assertNull($event->getError());

        $error = new \RuntimeException('PDF has no form');
        $event->setError($error);
        self::assertSame($error, $event->getError());
    }

    public function testHasResponseWhenModifiedPdfSet(): void
    {
        $event = new AcroFormApplyRequestEvent('%PDF', []);
        self::assertFalse($event->hasResponse());

        $event->setModifiedPdf('modified');
        self::assertTrue($event->hasResponse());
    }

    public function testHasResponseWhenErrorSet(): void
    {
        $event = new AcroFormApplyRequestEvent('%PDF', []);
        $event->setError(new \RuntimeException('err'));
        self::assertTrue($event->hasResponse());
    }

    public function testHasResponseWhenValidationResultSet(): void
    {
        $event = new AcroFormApplyRequestEvent('%PDF', [], true);
        self::assertFalse($event->hasResponse());
        $event->setValidationResult(['success' => true, 'patches_count' => 0]);
        self::assertTrue($event->hasResponse());
    }

    public function testSetAndGetErrorDetail(): void
    {
        $event = new AcroFormApplyRequestEvent('%PDF', []);
        self::assertNull($event->getErrorDetail());
        $event->setErrorDetail('stderr: script failed');
        self::assertSame('stderr: script failed', $event->getErrorDetail());
    }

    public function testIsValidateOnly(): void
    {
        $event = new AcroFormApplyRequestEvent('%PDF', [], false);
        self::assertFalse($event->isValidateOnly());

        $eventValidate = new AcroFormApplyRequestEvent('%PDF', [], true);
        self::assertTrue($eventValidate->isValidateOnly());
    }

    public function testSetAndGetValidationResult(): void
    {
        $event = new AcroFormApplyRequestEvent('%PDF', [], true);
        self::assertNull($event->getValidationResult());
        $result = ['success' => true, 'message' => 'OK', 'patches_count' => 2];
        $event->setValidationResult($result);
        self::assertSame($result, $event->getValidationResult());
    }
}
