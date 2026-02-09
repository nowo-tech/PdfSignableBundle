<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\Controller;

use Nowo\PdfSignableBundle\Controller\SignatureController;
use Nowo\PdfSignableBundle\Event\PdfSignableEvents;
use Nowo\PdfSignableBundle\Event\PdfProxyRequestEvent;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Unit tests for SignatureController: proxy disabled, invalid URL, JSON detection.
 */
final class SignatureControllerTest extends TestCase
{
    /**
     * Creates the bundle SignatureController with optional proxy, allowlist and example URL.
     *
     * @param bool         $proxyEnabled      Whether the proxy route is enabled
     * @param list<string> $proxyUrlAllowlist URL allowlist for the proxy (empty = no restriction)
     * @param string       $examplePdfUrl     Default PDF URL for the form
     *
     * @return SignatureController The controller instance with mocked dispatcher and translator
     */
    private function createController(bool $proxyEnabled = true, array $proxyUrlAllowlist = [], string $examplePdfUrl = ''): SignatureController
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnArgument(0);
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new SignatureController($dispatcher, $translator, $proxyEnabled, $proxyUrlAllowlist, $examplePdfUrl);
    }

    public function testProxyDisabledReturns403(): void
    {
        $controller = $this->createController(proxyEnabled: false);
        $request = Request::create('/pdf-signable/proxy', 'GET', ['url' => 'https://example.com/doc.pdf']);

        $response = $controller->proxyPdf($request);

        self::assertSame(403, $response->getStatusCode());
        self::assertStringContainsString('disabled', $response->getContent());
    }

    public function testProxyInvalidUrlReturns400(): void
    {
        $controller = $this->createController();
        $request = Request::create('/pdf-signable/proxy', 'GET', ['url' => 'not-a-valid-url']);

        $response = $controller->proxyPdf($request);

        self::assertSame(400, $response->getStatusCode());
    }

    public function testProxyMissingUrlReturns400(): void
    {
        $controller = $this->createController();
        $request = Request::create('/pdf-signable/proxy', 'GET');

        $response = $controller->proxyPdf($request);

        self::assertSame(400, $response->getStatusCode());
    }

    public function testProxyReturnsCustomResponseWhenEventProvidesOne(): void
    {
        $customResponse = new Response('custom pdf from cache', 200, ['Content-Type' => 'application/pdf']);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnCallback(function (object $event) use ($customResponse): object {
            if ($event instanceof PdfProxyRequestEvent) {
                $event->setResponse($customResponse);
            }
            return $event;
        });
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);
        $controller = new SignatureController($dispatcher, $translator, true, [], '');

        $request = Request::create('/pdf-signable/proxy', 'GET', ['url' => 'https://example.com/doc.pdf']);
        $response = $controller->proxyPdf($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('custom pdf from cache', $response->getContent());
        self::assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function testProxyUrlNotInAllowlistReturns403(): void
    {
        $controller = $this->createController(true, ['https://allowed.example.com/']);
        $request = Request::create('/pdf-signable/proxy', 'GET', ['url' => 'https://other.example.com/doc.pdf']);

        $response = $controller->proxyPdf($request);

        self::assertSame(403, $response->getStatusCode());
        self::assertStringContainsString('proxy.url_not_allowed', $response->getContent());
    }

    /**
     * SSRF mitigation: requests to localhost are blocked even when allowlist is empty.
     */
    public function testProxyBlocksLocalhostReturns403(): void
    {
        $controller = $this->createController(true, []);
        $request = Request::create('/pdf-signable/proxy', 'GET', ['url' => 'http://localhost/test.pdf']);

        $response = $controller->proxyPdf($request);

        self::assertSame(403, $response->getStatusCode());
    }

    /**
     * SSRF mitigation: requests to 127.0.0.1 are blocked.
     */
    public function testProxyBlocks127Returns403(): void
    {
        $controller = $this->createController(true, []);
        $request = Request::create('/pdf-signable/proxy', 'GET', ['url' => 'http://127.0.0.1/internal.pdf']);

        $response = $controller->proxyPdf($request);

        self::assertSame(403, $response->getStatusCode());
    }
}
