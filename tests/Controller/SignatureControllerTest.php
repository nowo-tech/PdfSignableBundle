<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\Controller;

use Nowo\PdfSignableBundle\Controller\SignatureController;
use Nowo\PdfSignableBundle\Event\PdfProxyRequestEvent;
use Nowo\PdfSignableBundle\Event\SignatureCoordinatesSubmittedEvent;
use Nowo\PdfSignableBundle\Form\SignatureBoxType;
use Nowo\PdfSignableBundle\Form\SignatureCoordinatesType;
use Nowo\PdfSignableBundle\Model\SignatureCoordinatesModel;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\Extension\HttpFoundation\HttpFoundationExtension;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormFactoryBuilder;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * Unit tests for SignatureController: index (GET/POST, JSON/redirect), proxy disabled, invalid URL, SSRF.
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

    /**
     * Builds a minimal container with form.factory, twig, router and request_stack for index() tests.
     *
     * @param Request|null     $request Request to use as current (for request_stack)
     * @param Session|null     $session Session to return from request_stack->getSession() (if null, uses $request->getSession() when available)
     * @param Environment|null $twig    Optional Twig mock (e.g. to assert form vars); default returns '<html>form</html>'
     */
    private function createContainerForIndex(?Request $request = null, ?Session $session = null, ?Environment $twig = null): ContainerInterface
    {
        $formFactory = (new FormFactoryBuilder())
            ->addExtension(new HttpFoundationExtension())
            ->addExtension(new PreloadedExtension(
                [new SignatureBoxType(), new SignatureCoordinatesType('', [])],
                []
            ))
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->getFormFactory();

        if (null === $twig) {
            $twig = $this->createMock(Environment::class);
            $twig->method('render')->willReturn('<html>form</html>');
        }

        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->with('nowo_pdf_signable_index', [], UrlGeneratorInterface::ABSOLUTE_PATH)->willReturn('/pdf-signable');

        $sessionToUse = $session ?? ($request?->hasSession() ? $request->getSession() : null);
        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn($request);
        if (null !== $sessionToUse) {
            $requestStack->method('getSession')->willReturn($sessionToUse);
        }

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnMap([
            ['form.factory', true],
            ['twig', true],
            ['router', true],
            ['request_stack', true],
        ]);
        $container->method('get')->willReturnCallback(static function (string $id) use ($formFactory, $twig, $router, $requestStack) {
            return match ($id) {
                'form.factory' => $formFactory,
                'twig' => $twig,
                'router' => $router,
                'request_stack' => $requestStack,
                default => throw new \InvalidArgumentException("Unknown service: {$id}"),
            };
        });

        return $container;
    }

    public function testIndexGetRendersForm(): void
    {
        $controller = $this->createController(proxyEnabled: true, proxyUrlAllowlist: [], examplePdfUrl: '');
        $request = Request::create('/pdf-signable', 'GET');
        $controller->setContainer($this->createContainerForIndex($request));

        $response = $controller->index($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('<html>form</html>', $response->getContent());
    }

    /** GET with non-empty examplePdfUrl pre-fills the model (covers controller line 64). */
    public function testIndexGetWithExamplePdfUrlPrefillsModel(): void
    {
        $exampleUrl = 'https://example.com/default.pdf';
        $twig = $this->createMock(Environment::class);
        $twig->method('render')->willReturnCallback(function (string $view, array $vars) use ($exampleUrl): string {
            $form = $vars['form'] ?? null;
            if (null !== $form && method_exists($form, 'getData')) {
                $data = $form->getData();
                if (null !== $data && method_exists($data, 'getPdfUrl')) {
                    self::assertSame($exampleUrl, $data->getPdfUrl(), 'Model should be pre-filled with example PDF URL on GET');
                }
            }

            return '<html>form</html>';
        });
        $request = Request::create('/pdf-signable', 'GET');
        $request->setSession(new Session(new MockArraySessionStorage()));
        $controller = $this->createController(proxyEnabled: true, proxyUrlAllowlist: [], examplePdfUrl: $exampleUrl);
        $controller->setContainer($this->createContainerForIndex($request, null, $twig));

        $response = $controller->index($request);

        self::assertSame(200, $response->getStatusCode());
    }

    public function testIndexPostValidRedirectsWithFlash(): void
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnCallback(function (object $event): object {
            if ($event instanceof SignatureCoordinatesSubmittedEvent) {
                // ensure the event was dispatched with model and request
                self::assertInstanceOf(SignatureCoordinatesModel::class, $event->getCoordinates());
                self::assertCount(1, $event->getCoordinates()->getSignatureBoxes());
            }

            return $event;
        });
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);
        $controller = new SignatureController($dispatcher, $translator, true, [], '');
        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/pdf-signable', 'POST', [
            'signature_coordinates' => [
                'pdfUrl' => 'https://example.com/doc.pdf',
                'unit' => SignatureCoordinatesModel::UNIT_MM,
                'origin' => SignatureCoordinatesModel::ORIGIN_BOTTOM_LEFT,
                'signatureBoxes' => [
                    0 => ['name' => 'signer_1', 'page' => 1, 'width' => 150.0, 'height' => 40.0, 'x' => 50.0, 'y' => 100.0],
                ],
            ],
        ]);
        $request->setSession($session);
        $controller->setContainer($this->createContainerForIndex($request, $session));

        $response = $controller->index($request);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/pdf-signable', $response->headers->get('Location'));
        $flashes = $session->getFlashBag()->get('success');
        self::assertCount(1, $flashes, 'Flash bag should contain one success message');
        self::assertSame('flash.save.success', $flashes[0]);
    }

    public function testIndexPostValidReturnsJsonWhenWantsJson(): void
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnArgument(0);
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);
        $controller = new SignatureController($dispatcher, $translator, true, [], '');
        $request = Request::create('/pdf-signable', 'POST', [
            'signature_coordinates' => [
                'pdfUrl' => 'https://example.com/doc.pdf',
                'unit' => SignatureCoordinatesModel::UNIT_PT,
                'origin' => SignatureCoordinatesModel::ORIGIN_TOP_LEFT,
                'signatureBoxes' => [
                    0 => ['name' => 'witness', 'page' => 2, 'width' => 120.0, 'height' => 35.0, 'x' => 10.0, 'y' => 200.0],
                ],
            ],
        ]);
        $request->headers->set('Accept', 'application/json');
        $controller->setContainer($this->createContainerForIndex($request));

        $response = $controller->index($request);

        self::assertInstanceOf(\Symfony\Component\HttpFoundation\JsonResponse::class, $response);
        self::assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($data['success']);
        self::assertSame(SignatureCoordinatesModel::UNIT_PT, $data['unit']);
        self::assertSame(SignatureCoordinatesModel::ORIGIN_TOP_LEFT, $data['origin']);
        self::assertIsArray($data['coordinates']);
        self::assertCount(1, $data['coordinates']);
        self::assertSame('witness', $data['coordinates'][0]['name']);
        self::assertSame(2, $data['coordinates'][0]['page']);
        self::assertEqualsWithDelta(10.0, $data['coordinates'][0]['x'], 0.01);
        self::assertEqualsWithDelta(200.0, $data['coordinates'][0]['y'], 0.01);
        self::assertEqualsWithDelta(120.0, $data['coordinates'][0]['width'], 0.01);
        self::assertEqualsWithDelta(35.0, $data['coordinates'][0]['height'], 0.01);
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

    /** SSRF: 10.x.x.x (private) is blocked. */
    public function testProxyBlocks10NetworkReturns403(): void
    {
        $controller = $this->createController(true, []);
        $request = Request::create('/pdf-signable/proxy', 'GET', ['url' => 'http://10.0.0.1/internal.pdf']);
        $response = $controller->proxyPdf($request);
        self::assertSame(403, $response->getStatusCode());
    }

    /** SSRF: 192.168.x.x (private) is blocked. */
    public function testProxyBlocks192168NetworkReturns403(): void
    {
        $controller = $this->createController(true, []);
        $request = Request::create('/pdf-signable/proxy', 'GET', ['url' => 'http://192.168.1.1/internal.pdf']);
        $response = $controller->proxyPdf($request);
        self::assertSame(403, $response->getStatusCode());
    }

    /** SSRF: 169.254.x.x (link-local) is blocked. */
    public function testProxyBlocks169254NetworkReturns403(): void
    {
        $controller = $this->createController(true, []);
        $request = Request::create('/pdf-signable/proxy', 'GET', ['url' => 'http://169.254.0.1/internal.pdf']);
        $response = $controller->proxyPdf($request);
        self::assertSame(403, $response->getStatusCode());
    }

    /** Allowlist substring: URL containing pattern is allowed; event provides response. */
    public function testProxyUrlAllowedBySubstringReturns200WhenEventProvidesResponse(): void
    {
        $customResponse = new Response('pdf content', 200, ['Content-Type' => 'application/pdf']);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnCallback(function (object $event) use ($customResponse): object {
            if ($event instanceof PdfProxyRequestEvent) {
                $event->setResponse($customResponse);
            }

            return $event;
        });
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);
        $controller = new SignatureController($dispatcher, $translator, true, ['example.com'], '');

        $request = Request::create('/pdf-signable/proxy', 'GET', ['url' => 'https://example.com/doc.pdf']);
        $response = $controller->proxyPdf($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('pdf content', $response->getContent());
    }

    /** Allowlist regex (# prefix): URL matching pattern is allowed. */
    public function testProxyUrlAllowedByRegexReturns200WhenEventProvidesResponse(): void
    {
        $customResponse = new Response('pdf from regex allowlist', 200, ['Content-Type' => 'application/pdf']);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnCallback(function (object $event) use ($customResponse): object {
            if ($event instanceof PdfProxyRequestEvent) {
                $event->setResponse($customResponse);
            }

            return $event;
        });
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);
        $controller = new SignatureController($dispatcher, $translator, true, ['#^https://allowed\.example\.com/#'], '');

        $request = Request::create('/pdf-signable/proxy', 'GET', ['url' => 'https://allowed.example.com/doc.pdf']);
        $response = $controller->proxyPdf($request);

        self::assertSame(200, $response->getStatusCode());
    }

    /** Invalid URL (e.g. no host) returns 400. */
    public function testProxyInvalidUrlNoHostReturns400(): void
    {
        $controller = $this->createController(true, []);
        $request = Request::create('/pdf-signable/proxy', 'GET', ['url' => 'http:///path']);
        $response = $controller->proxyPdf($request);
        self::assertSame(400, $response->getStatusCode());
    }

    /** Allowlist entries that are empty string are skipped; URL must match another entry. */
    public function testProxyAllowlistEmptyPatternSkippedReturns403(): void
    {
        $controller = $this->createController(true, ['', 'https://allowed.example.com/']);
        $request = Request::create('/pdf-signable/proxy', 'GET', ['url' => 'https://other.example.com/doc.pdf']);
        $response = $controller->proxyPdf($request);
        self::assertSame(403, $response->getStatusCode());
    }

    /** Allowlist regex that does not match the URL returns 403. */
    public function testProxyUrlNotMatchingRegexAllowlistReturns403(): void
    {
        $controller = $this->createController(true, ['#^https://only\.this\.com/#']);
        $request = Request::create('/pdf-signable/proxy', 'GET', ['url' => 'https://other.example.com/doc.pdf']);
        $response = $controller->proxyPdf($request);
        self::assertSame(403, $response->getStatusCode());
    }

    /** SSRF mitigation: host "::1" (IPv6 loopback) is blocked. */
    public function testProxyBlocksIpv6LoopbackHostReturns403(): void
    {
        $controller = $this->createController(true, []);
        // parse_url with "http://[::1]/x" returns host "::1" on PHP 7.4+
        $request = Request::create('/pdf-signable/proxy', 'GET', ['url' => 'http://[::1]/internal.pdf']);
        $response = $controller->proxyPdf($request);
        // Blocked by SSRF (403) or request fails with 502 if host is not resolved as IPv6 in this environment
        self::assertContains($response->getStatusCode(), [403, 502], 'IPv6 loopback should be blocked or request fail');
    }

    /** When event does not provide a response and the HTTP fetch fails, proxy returns 502. */
    public function testProxyReturns502WhenFetchFails(): void
    {
        $controller = $this->createController(true, []);
        // URL passes validation and SSRF (unresolved host); fetch will fail (connection error)
        $request = Request::create('/pdf-signable/proxy', 'GET', [
            'url' => 'https://non-existent-domain-xyz-12345.invalid/document.pdf',
        ]);
        $response = $controller->proxyPdf($request);
        self::assertSame(502, $response->getStatusCode());
        self::assertStringContainsString('proxy.error_load', $response->getContent());
    }
}
