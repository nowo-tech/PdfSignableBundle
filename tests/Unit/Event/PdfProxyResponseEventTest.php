<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\Event;

use Nowo\PdfSignableBundle\Event\PdfProxyResponseEvent;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests for PdfProxyResponseEvent.
 */
final class PdfProxyResponseEventTest extends TestCase
{
    public function testGettersAndSetResponse(): void
    {
        $request  = Request::create('/proxy');
        $response = new Response('pdf content', 200, ['Content-Type' => 'application/pdf']);
        $event    = new PdfProxyResponseEvent('https://example.com/doc.pdf', $request, $response);

        self::assertSame('https://example.com/doc.pdf', $event->getUrl());
        self::assertSame($request, $event->getRequest());
        self::assertSame($response, $event->getResponse());

        $newResponse = new Response('modified content', 200);
        $event->setResponse($newResponse);
        self::assertSame($newResponse, $event->getResponse());

        self::assertSame('https://example.com/doc.pdf', $event->getUrl());
        self::assertSame($request, $event->getRequest());
    }

    public function testGetUrlAndGetRequestReturnConstructorValues(): void
    {
        $request  = Request::create('/proxy', 'GET', ['url' => 'https://cdn.example.com/file.pdf']);
        $response = new Response('body', 200);
        $event    = new PdfProxyResponseEvent('https://cdn.example.com/file.pdf', $request, $response);

        self::assertSame('https://cdn.example.com/file.pdf', $event->getUrl());
        self::assertSame($request, $event->getRequest());
    }
}
