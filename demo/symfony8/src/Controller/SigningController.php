<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\SignaturePageType;
use App\Model\SignaturePageModel;
use Nowo\PdfSignableBundle\Form\SignatureBoxType;
use Nowo\PdfSignableBundle\Model\SignatureBoxModel;
use Nowo\PdfSignableBundle\Model\SignatureCoordinatesModel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Demo controller: signing (draw, upload, legal disclaimer, predefined boxes).
 */
class SigningController extends AbstractController
{
    use DemoSignatureTrait;

    public function __construct(
        #[Autowire(param: 'nowo_pdf_signable.example_pdf_url')]
        private readonly ?string $examplePdfUrl = null,
    ) {
    }

    #[Route('/demo-signing/draw', name: 'app_signing_draw', methods: ['GET', 'POST'])]
    public function draw(Request $request): Response
    {
        $explanation = '<ul class="mb-0"><li><code>enable_signature_capture: true</code> — each box has a <strong>draw pad</strong> (canvas)</li><li>Draw with mouse or finger; the image is stored and shown in the PDF overlay</li><li><strong>Low legal validity</strong> — simple acceptance/consent; not a qualified electronic signature</li></ul>';
        return $this->signaturePage($request, 'Draw signature in box', [
            'enable_signature_capture' => true,
            'min_entries' => 0,
            'max_entries' => 4,
        ], $explanation);
    }

    #[Route('/demo-signing/upload', name: 'app_signing_upload', methods: ['GET', 'POST'])]
    public function upload(Request $request): Response
    {
        $explanation = '<ul class="mb-0"><li><code>enable_signature_capture: true</code>, <code>enable_signature_upload: true</code></li><li>Each box: <strong>draw</strong> in the canvas or <strong>upload</strong> an image file</li><li>Same storage (base64 data URL); image is shown in the overlay</li></ul>';
        return $this->signaturePage($request, 'Draw or upload signature image', [
            'enable_signature_capture' => true,
            'enable_signature_upload' => true,
            'min_entries' => 0,
            'max_entries' => 4,
        ], $explanation);
    }

    #[Route('/demo-signing/legal-disclaimer', name: 'app_signing_legal_disclaimer', methods: ['GET', 'POST'])]
    public function legalDisclaimer(Request $request): Response
    {
        $explanation = '<ul class="mb-0"><li><code>signing_legal_disclaimer</code> — short text shown above the PDF viewer</li><li><code>signing_legal_disclaimer_url</code> — optional link (e.g. terms of use)</li><li>Use case: inform users about the legal effect of the signing method</li></ul>';
        return $this->signaturePage($request, 'Legal disclaimer (signing)', [
            'signing_legal_disclaimer' => 'This is a <strong>simple signature</strong> (draw or image). It has no qualified legal validity. For legally binding signatures, use a qualified trust service.',
            'signing_legal_disclaimer_url' => '#',
            'min_entries' => 0,
            'max_entries' => 4,
        ], $explanation);
    }

    #[Route('/demo-signing/predefined-boxes', name: 'app_signing_predefined_boxes', methods: ['GET', 'POST'])]
    public function predefinedBoxes(Request $request): Response
    {
        $model = new SignaturePageModel();
        $defaultPdfUrl = $this->examplePdfUrl ?? 'https://www.transportes.gob.es/recursos_mfom/paginabasica/recursos/11_07_2019_modelo_orientativo_de_contrato_de_arrendamiento_de_vivienda.pdf';

        if (!$request->isMethod('POST')) {
            $coords = $model->getSignatureCoordinates();
            $coords->setPdfUrl($defaultPdfUrl);
            $coords->setUnit(SignatureCoordinatesModel::UNIT_PT);
            $coords->setOrigin(SignatureCoordinatesModel::ORIGIN_BOTTOM_LEFT);
            $coords->addSignatureBox(
                (new SignatureBoxModel())->setName('signer_1')->setPage(1)->setWidth(150)->setHeight(40)->setX(50)->setY(700)
            );
            $coords->addSignatureBox(
                (new SignatureBoxModel())->setName('signer_2')->setPage(1)->setWidth(150)->setHeight(40)->setX(50)->setY(650)
            );
        }

        $form = $this->createForm(SignaturePageType::class, $model, [
            'signature_options' => [
                'pdf_url' => $defaultPdfUrl,
                'url_field' => false,
                'unit_default' => SignatureCoordinatesModel::UNIT_PT,
                'min_entries' => 2,
                'max_entries' => 2,
                'enable_signature_capture' => true,
                'enable_signature_upload' => true,
                'signing_only' => true,
                'signature_box_options' => [
                    'name_mode' => SignatureBoxType::NAME_MODE_CHOICE,
                    'name_choices' => ['Signer 1' => 'signer_1', 'Signer 2' => 'signer_2'],
                ],
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
            $coords = $model->getSignatureCoordinates();
            $this->addFlash('success', 'Coordinates saved (demo). ' . $this->formatCoordinatesForFlash($coords));
        }
        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('error', 'Please correct the errors in the form below.');
        }

        $explanation = '<ul class="mb-0"><li><strong>Boxes already placed</strong> — two fixed positions (Signer 1, Signer 2) on page 1</li><li><code>signing_only: true</code> — only signer name and signature pad/upload are shown; coordinates are not editable</li><li><code>min_entries: 2</code>, <code>max_entries: 2</code> — cannot add or remove boxes</li><li>User only <strong>draws or uploads</strong> the signature in each box</li></ul>';

        return $this->render('signature/index.html.twig', [
            'form' => $form,
            'page_title' => 'Predefined boxes — sign only (draw or upload)',
            'config_explanation' => $explanation,
        ]);
    }

    #[Route('/demo-signing/options', name: 'app_signing_options', methods: ['GET'])]
    public function options(): Response
    {
        return $this->render('signature/signing_options.html.twig');
    }
}
