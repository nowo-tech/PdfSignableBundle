<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\Event;

use Nowo\PdfSignableBundle\Event\PdfSignRequestEvent;
use Nowo\PdfSignableBundle\Model\SignatureCoordinatesModel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests for PdfSignRequestEvent.
 */
final class PdfSignRequestEventTest extends TestCase
{
    public function testGettersReturnInjectedValues(): void
    {
        $model = new SignatureCoordinatesModel();
        $model->setPdfUrl('https://example.com/doc.pdf');
        $request = Request::create('/pdf-signable/sign', 'POST');
        $options = ['signing_profile' => 'PAdES-BES'];

        $event = new PdfSignRequestEvent($model, $request, $options);

        self::assertSame($model, $event->getCoordinates());
        self::assertSame($request, $event->getRequest());
        self::assertSame($options, $event->getOptions());
        self::assertNull($event->getResponse());
    }

    public function testDefaultOptionsEmptyArray(): void
    {
        $model = new SignatureCoordinatesModel();
        $request = Request::create('/pdf-signable', 'POST');

        $event = new PdfSignRequestEvent($model, $request);

        self::assertSame([], $event->getOptions());
    }

    public function testSetAndGetResponse(): void
    {
        $model = new SignatureCoordinatesModel();
        $request = Request::create('/pdf-signable', 'POST');
        $event = new PdfSignRequestEvent($model, $request);

        self::assertNull($event->getResponse());

        $response = new Response('OK', 200);
        $event->setResponse($response);
        self::assertSame($response, $event->getResponse());

        $event->setResponse(null);
        self::assertNull($event->getResponse());
    }
}
