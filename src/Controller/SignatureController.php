<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Controller;

use Nowo\PdfSignableBundle\Event\BatchSignRequestedEvent;
use Nowo\PdfSignableBundle\Event\PdfProxyRequestEvent;
use Nowo\PdfSignableBundle\Event\PdfProxyResponseEvent;
use Nowo\PdfSignableBundle\Event\PdfSignableEvents;
use Nowo\PdfSignableBundle\Event\SignatureCoordinatesSubmittedEvent;
use Nowo\PdfSignableBundle\Form\SignatureCoordinatesType;
use Nowo\PdfSignableBundle\Model\AuditMetadata;
use Nowo\PdfSignableBundle\Model\SignatureCoordinatesModel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Default bundle controller: signature form page and PDF proxy endpoint.
 *
 * Exposes:
 * - GET/POST /pdf-signable: form page with SignatureCoordinatesType
 * - GET /pdf-signable/proxy?url=...: fetches external PDF to avoid CORS
 *
 * @internal this class is part of the bundle API; instantiation is handled by the container
 */
#[AsController]
final class SignatureController extends AbstractController
{
    /**
     * @param EventDispatcherInterface $eventDispatcher      Dispatches signature and proxy events
     * @param TranslatorInterface      $translator           Used for flash and error messages
     * @param bool                     $proxyEnabled         Whether the proxy route is enabled
     * @param list<string>             $proxyUrlAllowlist    When non-empty, proxy only allows these URL patterns
     * @param string                   $examplePdfUrl        Default PDF URL for form preload when not POST
     * @param bool                     $auditFillFromRequest When true, merge IP, user_agent, submitted_at into model audit before dispatch
     * @param LoggerInterface          $logger               Logger; proxy fetch failures are logged for debugging (502 cause)
     */
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly TranslatorInterface $translator,
        #[Autowire(param: 'nowo_pdf_signable.proxy_enabled')]
        private readonly bool $proxyEnabled = true,
        #[Autowire(param: 'nowo_pdf_signable.proxy_url_allowlist')]
        private readonly array $proxyUrlAllowlist = [],
        #[Autowire(param: 'nowo_pdf_signable.example_pdf_url')]
        private readonly string $examplePdfUrl = '',
        #[Autowire(param: 'nowo_pdf_signable.audit.fill_from_request')]
        private readonly bool $auditFillFromRequest = true,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Renders the signature coordinates form (and handles submit).
     *
     * @param Request $request The HTTP request (GET for display, POST for submit)
     *
     * @return Response Redirect on success, or the form page with optional JSON body for AJAX
     */
    #[Route('', name: 'nowo_pdf_signable_index', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $model = new SignatureCoordinatesModel();
        if (!$request->isMethod('POST') && '' !== $this->examplePdfUrl) {
            $model->setPdfUrl($this->examplePdfUrl);
        }
        $form = $this->createForm(SignatureCoordinatesType::class, $model);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $model = $form->getData();
            if ($this->auditFillFromRequest) {
                $audit = array_merge($model->getAuditMetadata(), [
                    AuditMetadata::SUBMITTED_AT => date('c'),
                    AuditMetadata::IP => $request->getClientIp() ?? '',
                    AuditMetadata::USER_AGENT => $request->headers->get('User-Agent', ''),
                ]);
                $model->setAuditMetadata($audit);
            }
            if ($request->request->getBoolean('batch_sign', false)) {
                $this->eventDispatcher->dispatch(
                    new BatchSignRequestedEvent($model, $request, null),
                    PdfSignableEvents::BATCH_SIGN_REQUESTED
                );
            }
            $this->eventDispatcher->dispatch(
                new SignatureCoordinatesSubmittedEvent($model, $request),
                PdfSignableEvents::SIGNATURE_COORDINATES_SUBMITTED
            );
            if ($this->wantsJson($request)) {
                return new JsonResponse([
                    'success' => true,
                    'coordinates' => $this->formatCoordinates($model),
                    'unit' => $model->getUnit(),
                    'origin' => $model->getOrigin(),
                ]);
            }
            $this->addFlash('success', $this->translator->trans('flash.save.success', [], 'nowo_pdf_signable'));

            return $this->redirectToRoute('nowo_pdf_signable_index');
        }

        return $this->render('@NowoPdfSignable/signature/index.html.twig', [
            'form' => $form,
        ]);
    }

    /**
     * Returns true if the request prefers JSON (Accept: application/json or X-Requested-With: XMLHttpRequest).
     *
     * @param Request $request The HTTP request
     *
     * @return bool True if the client expects a JSON response
     */
    private function wantsJson(Request $request): bool
    {
        return $request->isXmlHttpRequest()
            || str_contains($request->headers->get('Accept', ''), 'application/json');
    }

    /**
     * Formats SignatureCoordinatesModel boxes as array for JSON.
     *
     * @param SignatureCoordinatesModel $model The coordinates model
     *
     * @return array<int, array{name: string, page: int, x: float, y: float, width: float, height: float}>
     */
    private function formatCoordinates(SignatureCoordinatesModel $model): array
    {
        $out = [];
        foreach ($model->getSignatureBoxes() as $box) {
            $out[] = [
                'name' => $box->getName(),
                'page' => $box->getPage(),
                'x' => $box->getX(),
                'y' => $box->getY(),
                'width' => $box->getWidth(),
                'height' => $box->getHeight(),
            ];
        }

        return $out;
    }

    /**
     * Proxies an external PDF URL to avoid CORS when loading in the viewer.
     *
     * @param Request $request The HTTP request (query param: url)
     *
     * @return Response PDF content (200), or error (400, 403, 502)
     */
    #[Route('/proxy', name: 'nowo_pdf_signable_proxy', methods: ['GET'])]
    public function proxyPdf(Request $request): Response
    {
        if (!$this->proxyEnabled) {
            return new Response('Proxy disabled', Response::HTTP_FORBIDDEN);
        }
        $url = $request->query->get('url');
        if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
            return new Response(
                $this->translator->trans('proxy.invalid_url', [], 'nowo_pdf_signable'),
                Response::HTTP_BAD_REQUEST
            );
        }
        if ([] !== $this->proxyUrlAllowlist && !$this->isUrlAllowedByAllowlist($url)) {
            return new Response(
                $this->translator->trans('proxy.url_not_allowed', [], 'nowo_pdf_signable'),
                Response::HTTP_FORBIDDEN
            );
        }
        if ($this->isUrlBlockedForSsrf($url)) {
            return new Response(
                $this->translator->trans('proxy.url_not_allowed', [], 'nowo_pdf_signable'),
                Response::HTTP_FORBIDDEN
            );
        }

        $requestEvent = new PdfProxyRequestEvent($url, $request);
        $this->eventDispatcher->dispatch($requestEvent, PdfSignableEvents::PDF_PROXY_REQUEST);
        $url = $requestEvent->getUrl();
        if ($requestEvent->hasResponse()) {
            return $requestEvent->getResponse();
        }

        try {
            $client = HttpClient::create();
            $response = $client->request('GET', $url, [
                'timeout' => 30,
                'max_redirects' => 5,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept' => 'application/pdf,*/*',
                ],
            ]);
            $statusCode = $response->getStatusCode();
            if ($statusCode < 200 || $statusCode >= 300) {
                throw new \RuntimeException("Upstream returned HTTP {$statusCode}");
            }
            $content = $response->getContent();
            $headers = $response->getHeaders();
            $contentType = $headers['content-type'][0] ?? 'application/pdf';
            if (!str_contains($contentType, 'pdf')) {
                $contentType = 'application/pdf';
            }
            $responseObj = new Response($content, Response::HTTP_OK, [
                'Content-Type' => $contentType,
                'Content-Disposition' => 'inline; filename="document.pdf"',
            ]);
            $responseEvent = new PdfProxyResponseEvent($url, $request, $responseObj);
            $this->eventDispatcher->dispatch($responseEvent, PdfSignableEvents::PDF_PROXY_RESPONSE);

            return $responseEvent->getResponse();
        } catch (ExceptionInterface|\Throwable $e) {
            $this->logger->warning('PDF proxy could not fetch URL: {url}. Reason: {reason}', [
                'url' => $url,
                'reason' => $e->getMessage(),
                'exception' => $e,
            ]);
            // Do not expose exception message to the client (information disclosure)
            return new Response(
                $this->translator->trans('proxy.error_load', [], 'nowo_pdf_signable'),
                Response::HTTP_BAD_GATEWAY
            );
        }
    }

    /**
     * Returns true if the URL targets a private or local host (SSRF mitigation).
     * Blocks 127.0.0.0/8, ::1, 10.0.0.0/8, 192.168.0.0/16, 169.254.0.0/16 and hostname "localhost".
     *
     * @param string $url The URL to check
     *
     * @return bool True if the URL should be blocked
     */
    private function isUrlBlockedForSsrf(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (null === $host || '' === $host) {
            return true;
        }
        $hostLower = strtolower($host);
        if ('localhost' === $hostLower || '::1' === $hostLower) {
            return true;
        }
        $ip = $host;
        if (!filter_var($host, FILTER_VALIDATE_IP)) {
            $resolved = gethostbyname($host);
            if ($resolved === $host) {
                return false; // Could not resolve; let the fetch fail
            }
            $ip = $resolved;
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $long = ip2long($ip);
            if (false === $long) {
                return true;
            }
            $u = (float) sprintf('%u', $long);

            // 127.0.0.0/8, 10.0.0.0/8, 192.168.0.0/16, 169.254.0.0/16
            return ($u >= 2130706432 && $u <= 2147483647)
                || ($u >= 167772160 && $u <= 184549375)
                || ($u >= 3232235520 && $u <= 3232301055)
                || ($u >= 2851995648 && $u <= 2852061183);
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return str_starts_with($ip, '::1') || str_starts_with($ip, 'fe80:');
        }

        return false;
    }

    /**
     * Returns true if the URL is allowed by proxy_url_allowlist (substring or regex).
     *
     * @param string $url The URL to check
     *
     * @return bool True if the URL matches at least one allowlist entry
     */
    private function isUrlAllowedByAllowlist(string $url): bool
    {
        foreach ($this->proxyUrlAllowlist as $pattern) {
            if ('' === $pattern) {
                continue;
            }
            if (str_starts_with($pattern, '#')) {
                if (1 === @preg_match($pattern, $url)) {
                    return true;
                }
                continue;
            }
            if (str_contains($url, $pattern)) {
                return true;
            }
        }

        return false;
    }
}
