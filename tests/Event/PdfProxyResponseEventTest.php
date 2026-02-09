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
        $request = Request::create('/proxy');
        $response = new Response('pdf content', 200, ['Content-Type' => 'application/pdf']);
        $event = new PdfProxyResponseEvent('https://example.com/doc.pdf', $request, $response);

        self::assertSame('https://example.com/doc.pdf', $event->getUrl());
        self::assertSame($request, $event->getRequest());
        self::assertSame($response, $event->getResponse());

        $newResponse = new Response('modified content', 200);
        $event->setResponse($newResponse);
        self::assertSame($newResponse, $event->getResponse());
    }
}
