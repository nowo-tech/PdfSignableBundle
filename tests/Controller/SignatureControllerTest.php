<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\Controller;

use Nowo\PdfSignableBundle\Controller\SignatureController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Unit tests for SignatureController: proxy disabled, invalid URL, JSON detection.
 */
final class SignatureControllerTest extends TestCase
{
    private function createController(bool $proxyEnabled = true, string $examplePdfUrl = ''): SignatureController
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new SignatureController($translator, $proxyEnabled, $examplePdfUrl);
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
}
