<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\Event;

use Nowo\PdfSignableBundle\Event\BatchSignRequestedEvent;
use Nowo\PdfSignableBundle\Model\SignatureCoordinatesModel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests for BatchSignRequestedEvent.
 */
final class BatchSignRequestedEventTest extends TestCase
{
    public function testGettersReturnInjectedValues(): void
    {
        $model = new SignatureCoordinatesModel();
        $model->setPdfUrl('https://example.com/doc.pdf');
        $request = Request::create('/pdf-signable', 'POST', ['batch_sign' => '1']);

        $event = new BatchSignRequestedEvent($model, $request);

        self::assertSame($model, $event->getCoordinates());
        self::assertSame($request, $event->getRequest());
        self::assertNull($event->getBoxTarget());
        self::assertSame('https://example.com/doc.pdf', $event->getCoordinates()->getPdfUrl());
    }

    public function testBoxTargetWhenProvided(): void
    {
        $model     = new SignatureCoordinatesModel();
        $request   = Request::create('/pdf-signable', 'POST');
        $boxTarget = [0, 2];

        $event = new BatchSignRequestedEvent($model, $request, $boxTarget);

        self::assertSame($boxTarget, $event->getBoxTarget());
    }

    public function testBoxTargetWithNames(): void
    {
        $model     = new SignatureCoordinatesModel();
        $request   = Request::create('/pdf-signable', 'POST');
        $boxTarget = ['signer_1', 'witness'];

        $event = new BatchSignRequestedEvent($model, $request, $boxTarget);

        self::assertSame($boxTarget, $event->getBoxTarget());
    }
}
