<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Controller;

use Nowo\PdfSignableBundle\Form\SignatureCoordinatesType;
use Nowo\PdfSignableBundle\Model\SignatureCoordinatesModel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Default bundle controller: signature form page and PDF proxy endpoint.
 *
 * Exposes:
 * - GET/POST /pdf-signable: form page with SignatureCoordinatesType
 * - GET /pdf-signable/proxy?url=...: fetches external PDF to avoid CORS
 */
#[AsController]
final class SignatureController extends AbstractController
{
    /**
     * @param TranslatorInterface $translator For flash and error messages
     * @param bool                $proxyEnabled Whether the proxy route is enabled
     * @param string              $examplePdfUrl Default PDF URL for form preload
     */
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly bool $proxyEnabled = true,
        private readonly string $examplePdfUrl = '',
    ) {
    }

    /**
     * Renders the signature coordinates form (and handles submit).
     *
     * @return Response Redirect on success, or the form page
     */
    #[Route('', name: 'nowo_pdf_signable_index', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $model = new SignatureCoordinatesModel();
        if (!$request->isMethod('POST') && $this->examplePdfUrl !== '') {
            $model->setPdfUrl($this->examplePdfUrl);
        }
        $form = $this->createForm(SignatureCoordinatesType::class, $model);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $model = $form->getData();
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
     */
    private function wantsJson(Request $request): bool
    {
        return $request->isXmlHttpRequest()
            || str_contains($request->headers->get('Accept', ''), 'application/json');
    }

    /**
     * Formats SignatureCoordinatesModel boxes as array for JSON.
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
            return new Response($content, Response::HTTP_OK, [
                'Content-Type' => $contentType,
                'Content-Disposition' => 'inline; filename="document.pdf"',
            ]);
        } catch (ExceptionInterface|\Throwable $e) {
            return new Response(
                $this->translator->trans('proxy.error_load', [], 'nowo_pdf_signable') . ' ' . $e->getMessage(),
                Response::HTTP_BAD_GATEWAY
            );
        }
    }
}
