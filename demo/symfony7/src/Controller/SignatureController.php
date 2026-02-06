<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\SignaturePageType;
use App\Model\SignaturePageModel;
use Nowo\PdfSignableBundle\Form\SignatureBoxType;
use Nowo\PdfSignableBundle\Form\SignatureCoordinatesType;
use Nowo\PdfSignableBundle\Model\SignatureBoxModel;
use Nowo\PdfSignableBundle\Model\SignatureCoordinatesModel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Demo controller: multiple pages with different SignatureCoordinatesType configurations.
 *
 * @see demo/symfony8/src/Controller/SignatureController.php
 */
class SignatureController extends AbstractController
{
    /**
     * @param string|null $examplePdfUrl From nowo_pdf_signable.example_pdf_url config
     */
    public function __construct(
        private readonly ?string $examplePdfUrl = null,
    ) {
    }

    /**
     * Default demo page: preset URL, restricted units, visible URL field.
     */
    #[Route('/demo-signature', name: 'app_signature', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $explanation = 'Default options: optional preset PDF URL from <code>nowo_pdf_signable.example_pdf_url</code>, '
            . 'units restricted to mm, cm, pt, and origin preset to bottom-left. The URL field is visible so the user can change it.';
        return $this->signaturePage($request, 'Default (preset URL, restricted units)', [], $explanation);
    }

    /**
     * Fixed PDF URL demo: URL field hidden, single document.
     */
    #[Route('/demo-signature/fixed-url', name: 'app_signature_fixed_url', methods: ['GET', 'POST'])]
    public function fixedUrl(Request $request): Response
    {
        if ($this->examplePdfUrl === null || $this->examplePdfUrl === '') {
            $this->addFlash('warning', 'Set nowo_pdf_signable.example_pdf_url in config to use this demo.');
        }
        $explanation = 'The PDF URL is fixed and the URL field is hidden (<code>url_field: false</code>, <code>pdf_url</code> set). '
            . 'Useful when the document is already known (e.g. a single template).';
        return $this->signaturePage($request, 'Fixed URL (no URL field)', [
            'pdf_url' => $this->examplePdfUrl ?? 'https://www.transportes.gob.es/recursos_mfom/paginabasica/recursos/11_07_2019_modelo_orientativo_de_contrato_de_arrendamiento_de_vivienda.pdf',
            'url_field' => false,
        ], $explanation);
    }

    /**
     * URL as dropdown demo: user selects document from a list.
     */
    #[Route('/demo-signature/url-choice', name: 'app_signature_url_choice', methods: ['GET', 'POST'])]
    public function urlChoice(Request $request): Response
    {
        $example = $this->examplePdfUrl ?? 'https://www.transportes.gob.es/recursos_mfom/paginabasica/recursos/11_07_2019_modelo_orientativo_de_contrato_de_arrendamiento_de_vivienda.pdf';
        $explanation = 'The URL is chosen from a dropdown (<code>url_mode: \'choice\'</code>, <code>url_choices</code>). '
            . 'User picks a document by label instead of pasting a URL.';
        return $this->signaturePage($request, 'URL as dropdown', [
            'url_mode' => SignatureCoordinatesType::URL_MODE_CHOICE,
            'url_choices' => [
                'Contrato arrendamiento (default)' => $example,
                'Contrato trabajo indefinido' => 'https://www.aicode.org/FORMULARIOS/2012/Modelo%20contrato%20de%20trabajo%20indefinido%20ordinario.pdf',
                'Sample (Mozilla)' => 'https://mozilla.github.io/pdf.js/web/compressed.tracemonkey-pldi-09.pdf',
            ],
            'url_placeholder' => 'Select a document',
        ], $explanation);
    }

    /**
     * Limited boxes demo: min 1, max 4 boxes; box name as dropdown.
     */
    #[Route('/demo-signature/limited-boxes', name: 'app_signature_limited_boxes', methods: ['GET', 'POST'])]
    public function limitedBoxes(Request $request): Response
    {
        $explanation = 'The collection has a minimum of 1 and maximum of 4 boxes (<code>min_entries: 1</code>, <code>max_entries: 4</code>). '
            . 'The box name is a dropdown (<code>signature_box_options.name_mode: \'choice\'</code>) with options: Signer 1, Signer 2, Witness. '
            . 'The "Add box" button is hidden when the limit is reached.';
        return $this->signaturePage($request, 'Limited boxes (max 4) + name as selector', [
            'min_entries' => 1,
            'max_entries' => 4,
            'signature_box_options' => [
                'name_mode' => SignatureBoxType::NAME_MODE_CHOICE,
                'name_choices' => [
                    'Signer 1' => 'signer_1',
                    'Signer 2' => 'signer_2',
                    'Witness' => 'witness',
                ],
                'name_placeholder' => 'Select role',
            ],
        ], $explanation);
    }

    /**
     * Predefined boxes demo: model pre-filled with two boxes, fixed URL, max 5 boxes.
     */
    #[Route('/demo-signature/predefined', name: 'app_signature_predefined', methods: ['GET', 'POST'])]
    public function predefinedBoxes(Request $request): Response
    {
        $model = new SignaturePageModel();
        $model->getSignatureCoordinates()->setPdfUrl(
            $this->examplePdfUrl ?? 'https://www.transportes.gob.es/recursos_mfom/paginabasica/recursos/11_07_2019_modelo_orientativo_de_contrato_de_arrendamiento_de_vivienda.pdf'
        );
        $model->getSignatureCoordinates()->addSignatureBox(
            (new SignatureBoxModel())->setName('signer_1')->setPage(1)->setWidth(150)->setHeight(40)->setX(50)->setY(700)
        );
        $model->getSignatureCoordinates()->addSignatureBox(
            (new SignatureBoxModel())->setName('signer_2')->setPage(1)->setWidth(150)->setHeight(40)->setX(50)->setY(650)
        );

        $form = $this->createForm(SignaturePageType::class, $model, [
            'signature_options' => [
                'url_field' => false,
                'max_entries' => 5,
            ],
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $model = $form->getData();
            if ($this->wantsJson($request)) {
                $coords = $model->getSignatureCoordinates();
                return new JsonResponse([
                    'success' => true,
                    'coordinates' => $this->formatCoordinates($coords),
                    'unit' => $coords->getUnit(),
                    'origin' => $coords->getOrigin(),
                ]);
            }
            $this->addFlash('success', 'Coordinates saved (demo).');
            return $this->redirectToRoute('app_signature_predefined');
        }

        $explanation = 'The model is pre-filled with two signature boxes (<code>signer_1</code> and <code>signer_2</code> on page 1). '
            . 'The URL is fixed (<code>url_field: false</code>). The collection allows up to 5 boxes (<code>max_entries: 5</code>). '
            . 'User can move/resize the existing boxes and add up to 3 more.';

        return $this->render('signature/index.html.twig', [
            'form' => $form,
            'page_title' => 'Predefined boxes (2 initial boxes, max 5)',
            'config_explanation' => $explanation,
        ]);
    }

    /**
     * Renders a signature demo page with the given options and configuration explanation.
     *
     * @param array<string, mixed> $signatureOptions Options passed to SignatureCoordinatesType
     *
     * @return Response
     */
    private function signaturePage(Request $request, string $pageTitle, array $signatureOptions, string $configExplanation): Response
    {
        $model = new SignaturePageModel();
        $form = $this->createForm(SignaturePageType::class, $model, [
            'signature_options' => $signatureOptions,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $model = $form->getData();
            if ($this->wantsJson($request)) {
                $coords = $model->getSignatureCoordinates();
                return new JsonResponse([
                    'success' => true,
                    'coordinates' => $this->formatCoordinates($coords),
                    'unit' => $coords->getUnit(),
                    'origin' => $coords->getOrigin(),
                ]);
            }
            $this->addFlash('success', 'Coordinates saved (demo).');
            return $this->redirectToRoute($request->attributes->get('_route'));
        }

        return $this->render('signature/index.html.twig', [
            'form' => $form,
            'page_title' => $pageTitle,
            'config_explanation' => $configExplanation,
        ]);
    }

    private function wantsJson(Request $request): bool
    {
        return $request->isXmlHttpRequest()
            || str_contains($request->headers->get('Accept', ''), 'application/json');
    }

    /**
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
}
