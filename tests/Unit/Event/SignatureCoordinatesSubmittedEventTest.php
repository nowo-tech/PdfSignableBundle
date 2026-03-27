<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\Event;

use Nowo\PdfSignableBundle\Event\SignatureCoordinatesSubmittedEvent;
use Nowo\PdfSignableBundle\Model\SignatureCoordinatesModel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests for SignatureCoordinatesSubmittedEvent.
 */
final class SignatureCoordinatesSubmittedEventTest extends TestCase
{
    public function testGettersReturnInjectedValues(): void
    {
        $model = new SignatureCoordinatesModel();
        $model->setPdfUrl('https://example.com/doc.pdf');
        $request = Request::create('/pdf-signable', 'POST');

        $event = new SignatureCoordinatesSubmittedEvent($model, $request);

        self::assertSame($model, $event->getCoordinates());
        self::assertSame($request, $event->getRequest());
        self::assertSame('https://example.com/doc.pdf', $event->getCoordinates()->getPdfUrl());
    }
}
