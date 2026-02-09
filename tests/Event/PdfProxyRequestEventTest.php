<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\Event;

use Nowo\PdfSignableBundle\Event\PdfProxyRequestEvent;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests for PdfProxyRequestEvent.
 */
final class PdfProxyRequestEventTest extends TestCase
{
    public function testGetUrlAndSetUrl(): void
    {
        $request = Request::create('/proxy', 'GET', ['url' => 'https://example.com/a.pdf']);
        $event = new PdfProxyRequestEvent('https://example.com/a.pdf', $request);

        self::assertSame('https://example.com/a.pdf', $event->getUrl());
        $event->setUrl('https://other.com/b.pdf');
        self::assertSame('https://other.com/b.pdf', $event->getUrl());
    }

    public function testGetRequest(): void
    {
        $request = Request::create('/proxy');
        $event = new PdfProxyRequestEvent('https://example.com/doc.pdf', $request);
        self::assertSame($request, $event->getRequest());
    }

    public function testSetResponseAndHasResponse(): void
    {
        $request = Request::create('/proxy');
        $event = new PdfProxyRequestEvent('https://example.com/doc.pdf', $request);

        self::assertFalse($event->hasResponse());
        self::assertNull($event->getResponse());

        $customResponse = new Response('cached pdf content', 200, ['Content-Type' => 'application/pdf']);
        $event->setResponse($customResponse);

        self::assertTrue($event->hasResponse());
        self::assertSame($customResponse, $event->getResponse());

        $event->setResponse(null);
        self::assertFalse($event->hasResponse());
        self::assertNull($event->getResponse());
    }
}
