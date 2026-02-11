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
     * No named config: all options passed in code (form baseOptions only).
     */
    #[Route('/demo-signature', name: 'app_signature', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $explanation = '<ul class="mb-0"><li>No <code>config</code> passed to the type</li><li>Units, origin and example URL from form baseOptions + bundle <code>example_pdf_url</code></li><li>Nothing from <code>nowo_pdf_signable.configs</code></li></ul>';
        return $this->signaturePage($request, 'No config (inline options only)', [], $explanation);
    }

    /**
     * Uses named config "default" from nowo_pdf_signable.configs.
     */
    #[Route('/demo-signature/default-config', name: 'app_signature_default_config', methods: ['GET', 'POST'])]
    public function defaultConfig(Request $request): Response
    {
        $explanation = '<ul class="mb-0"><li><code>config: \'default\'</code></li><li><code>units</code>, <code>unit_default</code>, <code>origin_default</code> from <code>configs.default</code></li><li>Options passed in code override the config</li></ul>';
        return $this->signaturePage($request, 'Default config (from YAML)', [
            'config' => 'default',
        ], $explanation);
    }

    /**
     * Uses named config "fixed_url" from nowo_pdf_signable.configs.
     */
    #[Route('/demo-signature/fixed-url', name: 'app_signature_fixed_url', methods: ['GET', 'POST'])]
    public function fixedUrl(Request $request): Response
    {
        $explanation = '<ul class="mb-0"><li><code>config: \'fixed_url\'</code></li><li><code>url_field: false</code>, <code>show_load_pdf_button: false</code> — URL and Load PDF button hidden</li><li><code>unit_field: false</code>, <code>origin_field: false</code> — unit and origin hidden (fixed to default)</li><li>Single document; only signature boxes form visible</li></ul>';
        return $this->signaturePage($request, 'Fixed URL config (from YAML)', [
            'config' => 'fixed_url',
        ], $explanation);
    }

    /**
     * Uses named config "fixed_url" but overrides unit_default in code.
     */
    #[Route('/demo-signature/fixed-url-overridden', name: 'app_signature_fixed_url_overridden', methods: ['GET', 'POST'])]
    public function fixedUrlOverridden(Request $request): Response
    {
        $explanation = '<ul class="mb-0"><li>Same <code>config: \'fixed_url\'</code></li><li>Override in code: <code>unit_default: \'pt\'</code> (unit field is hidden; submitted value is pt)</li><li>Shows that form options override the named config</li></ul>';
        return $this->signaturePage($request, 'Fixed URL config overridden (config + override)', [
            'config' => 'fixed_url',
            'unit_default' => SignatureCoordinatesModel::UNIT_PT,
        ], $explanation);
    }

    /**
     * URL as dropdown: user selects document from a list.
     */
    #[Route('/demo-signature/url-choice', name: 'app_signature_url_choice', methods: ['GET', 'POST'])]
    public function urlChoice(Request $request): Response
    {
        $example = $this->examplePdfUrl ?? 'https://www.transportes.gob.es/recursos_mfom/paginabasica/recursos/11_07_2019_modelo_orientativo_de_contrato_de_arrendamiento_de_vivienda.pdf';
        $explanation = '<ul class="mb-0"><li><code>url_mode: \'choice\'</code>, <code>url_choices</code> (label → URL)</li><li>User picks document by label instead of pasting URL</li><li>Fixed set of PDFs (templates/models)</li></ul>';
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
     * Limited boxes: min 1, max 4; name as dropdown; all names must be unique.
     */
    #[Route('/demo-signature/limited-boxes', name: 'app_signature_limited_boxes', methods: ['GET', 'POST'])]
    public function limitedBoxes(Request $request): Response
    {
        $explanation = '<ul class="mb-0"><li><code>min_entries: 1</code>, <code>max_entries: 4</code></li><li><code>unique_box_names: true</code> — no duplicate names</li><li><code>name_mode: choice</code>, <code>name_choices</code> (Signer 1, Signer 2, Witness)</li><li>Name required; "Add box" hidden at max</li></ul>';
        return $this->signaturePage($request, 'Limited boxes (max 4) + name as selector', [
            'min_entries' => 1,
            'max_entries' => 4,
            'unique_box_names' => true,
            'signature_box_options' => [
                'name_mode' => SignatureBoxType::NAME_MODE_CHOICE,
                'name_choices' => [
                    'Signer 1' => 'signer_1',
                    'Signer 2' => 'signer_2',
                    'Witness' => 'witness',
                ],
            ],
        ], $explanation);
    }

    /**
     * Same signer (name) can have multiple boxes (multiple locations); duplicate names allowed.
     */
    #[Route('/demo-signature/same-signer-multiple', name: 'app_signature_same_signer_multiple', methods: ['GET', 'POST'])]
    public function sameSignerMultiple(Request $request): Response
    {
        $explanation = '<ul class="mb-0"><li><code>unique_box_names: false</code> — duplicate names allowed</li><li>Same name on several boxes = same signer, multiple signature locations</li><li><code>name_mode: choice</code> — e.g. pick "Signer 1" twice for two positions</li><li>Overlay shows same color per name and disambiguator (e.g. <code>signer_1 (1)</code>, <code>signer_1 (2)</code>)</li></ul>';
        return $this->signaturePage($request, 'Same signer, multiple locations', [
            'min_entries' => 1,
            'max_entries' => 6,
            'unique_box_names' => false,
            'signature_box_options' => [
                'name_mode' => SignatureBoxType::NAME_MODE_CHOICE,
                'name_choices' => [
                    'Signer 1' => 'signer_1',
                    'Signer 2' => 'signer_2',
                    'Witness' => 'witness',
                ],
            ],
        ], $explanation);
    }

    /**
     * Only certain names must be unique; others may repeat (unique_box_names as array).
     */
    #[Route('/demo-signature/unique-per-name', name: 'app_signature_unique_per_name', methods: ['GET', 'POST'])]
    public function uniquePerName(Request $request): Response
    {
        $explanation = '<ul class="mb-0"><li><code>unique_box_names: [\'signer_1\', \'witness\']</code> — only these must be unique</li><li><code>signer_2</code> may appear on multiple boxes</li><li><code>name_mode: choice</code> with Signer 1, Signer 2, Witness</li><li>Use case: one "Signer 1" and one "Witness", but "Signer 2" can sign in several places</li></ul>';
        return $this->signaturePage($request, 'Unique per name (array)', [
            'min_entries' => 1,
            'max_entries' => 6,
            'unique_box_names' => ['signer_1', 'witness'],
            'signature_box_options' => [
                'name_mode' => SignatureBoxType::NAME_MODE_CHOICE,
                'name_choices' => [
                    'Signer 1' => 'signer_1',
                    'Signer 2' => 'signer_2',
                    'Witness' => 'witness',
                ],
            ],
        ], $explanation);
    }

    /**
     * Page restriction: boxes can only be placed on allowed pages (e.g. page 1 only).
     */
    #[Route('/demo-signature/page-restriction', name: 'app_signature_page_restriction', methods: ['GET', 'POST'])]
    public function pageRestriction(Request $request): Response
    {
        $explanation = '<ul class="mb-0"><li><code>allowed_pages: [1]</code> — page field is a dropdown with only page 1</li><li>Use case: single-page contract; restrict boxes to first page</li><li>Validation rejects any other page</li></ul>';
        return $this->signaturePage($request, 'Page restriction (allowed_pages)', [
            'allowed_pages' => [1],
            'min_entries' => 0,
            'max_entries' => 4,
        ], $explanation);
    }

    /**
     * Sorted boxes: on submit, boxes are ordered by page, then Y, then X.
     */
    #[Route('/demo-signature/sorted-boxes', name: 'app_signature_sorted_boxes', methods: ['GET', 'POST'])]
    public function sortedBoxes(Request $request): Response
    {
        $explanation = '<ul class="mb-0"><li><code>sort_boxes: true</code> — on submit, boxes are sorted by page, then Y, then X</li><li>Saved/exported order is deterministic (e.g. for downstream signing)</li></ul>';
        return $this->signaturePage($request, 'Sorted boxes (sort_boxes)', [
            'sort_boxes' => true,
            'min_entries' => 0,
            'max_entries' => 6,
        ], $explanation);
    }

    /**
     * No overlapping boxes: validation rejects when two boxes on the same page overlap.
     */
    #[Route('/demo-signature/no-overlap', name: 'app_signature_no_overlap', methods: ['GET', 'POST'])]
    public function noOverlap(Request $request): Response
    {
        $explanation = '<ul class="mb-0"><li><code>prevent_box_overlap: true</code> (default) — boxes on the same page cannot overlap</li><li>Frontend: drag/resize that would overlap is reverted and a message is shown</li><li>On submit, overlapping boxes trigger a validation error</li></ul>';
        return $this->signaturePage($request, 'No overlapping boxes (prevent_box_overlap)', [
            'prevent_box_overlap' => true,
            'min_entries' => 0,
            'max_entries' => 6,
        ], $explanation);
    }

    /**
     * Rotation enabled: each box has an angle field and the viewer shows a rotate handle.
     */
    #[Route('/demo-signature/rotation', name: 'app_signature_rotation', methods: ['GET', 'POST'])]
    public function rotation(Request $request): Response
    {
        $explanation = '<ul class="mb-0"><li><code>enable_rotation: true</code> — each box has an <strong>angle</strong> field (degrees)</li><li>Viewer shows a <strong>rotate handle</strong> above each overlay; drag to rotate</li><li>Use case: tilted signature boxes or stamps</li></ul>';
        return $this->signaturePage($request, 'Rotation (enable_rotation)', [
            'enable_rotation' => true,
            'min_entries' => 0,
            'max_entries' => 6,
        ], $explanation);
    }

    /**
     * Default dimensions per box name: when the user selects a name, width/height/x/y/angle are filled from the map.
     */
    #[Route('/demo-signature/defaults-by-name', name: 'app_signature_defaults_by_name', methods: ['GET', 'POST'])]
    public function defaultsByName(Request $request): Response
    {
        $explanation = '<ul class="mb-0"><li><code>box_defaults_by_name</code> — map of name to default <code>width</code>, <code>height</code>, <code>x</code>, <code>y</code>, <code>angle</code></li><li>When the user selects a name (dropdown or input), the frontend fills in those fields</li><li><code>name_mode: choice</code> with Signer 1, Signer 2, Witness; each has different default size/position</li></ul>';
        return $this->signaturePage($request, 'Default values per box name', [
            'box_defaults_by_name' => [
                'signer_1' => ['width' => 180, 'height' => 45, 'x' => 80, 'y' => 700, 'angle' => 0],
                'signer_2' => ['width' => 150, 'height' => 40, 'x' => 80, 'y' => 650, 'angle' => 0],
                'witness' => ['width' => 120, 'height' => 35, 'x' => 400, 'y' => 700, 'angle' => 0],
            ],
            'signature_box_options' => [
                'name_mode' => SignatureBoxType::NAME_MODE_CHOICE,
                'name_choices' => [
                    'Signer 1' => 'signer_1',
                    'Signer 2' => 'signer_2',
                    'Witness' => 'witness',
                ],
            ],
            'unit_default' => SignatureCoordinatesModel::UNIT_PT,
            'min_entries' => 0,
            'max_entries' => 6,
        ], $explanation);
    }

    /**
     * Overlap allowed: prevent_box_overlap false — boxes on the same page may overlap (e.g. for testing or special layouts).
     */
    #[Route('/demo-signature/allow-overlap', name: 'app_signature_allow_overlap', methods: ['GET', 'POST'])]
    public function allowOverlap(Request $request): Response
    {
        $explanation = '<ul class="mb-0"><li><code>prevent_box_overlap: false</code> — overlapping boxes on the same page are <strong>allowed</strong></li><li>No frontend revert; no validation error on submit</li><li>Use case: testing, or layouts where overlap is intentional</li></ul>';
        return $this->signaturePage($request, 'Allow overlapping boxes', [
            'prevent_box_overlap' => false,
            'min_entries' => 0,
            'max_entries' => 6,
        ], $explanation);
    }

    /**
     * Snap to grid + snap to boxes: coarse grid (10 mm), two pre-placed boxes so snap is obvious.
     */
    #[Route('/demo-signature/snap-to-grid', name: 'app_signature_snap_to_grid', methods: ['GET', 'POST'])]
    public function snapToGrid(Request $request): Response
    {
        $model = new SignaturePageModel();
        $defaultPdfUrl = $this->examplePdfUrl ?? 'https://www.transportes.gob.es/recursos_mfom/paginabasica/recursos/11_07_2019_modelo_orientativo_de_contrato_de_arrendamiento_de_vivienda.pdf';

        if (!$request->isMethod('POST')) {
            $coords = $model->getSignatureCoordinates();
            $coords->setPdfUrl($defaultPdfUrl);
            $coords->setUnit(SignatureCoordinatesModel::UNIT_MM);
            $coords->addSignatureBox(
                (new SignatureBoxModel())->setName('signer_1')->setPage(1)->setWidth(60)->setHeight(25)->setX(20)->setY(250)
            );
            $coords->addSignatureBox(
                (new SignatureBoxModel())->setName('signer_2')->setPage(1)->setWidth(60)->setHeight(25)->setX(20)->setY(200)
            );
        }

        $form = $this->createForm(SignaturePageType::class, $model, [
            'signature_options' => [
                'pdf_url' => $defaultPdfUrl,
                'url_field' => false,
                'snap_to_grid' => 10,
                'snap_to_boxes' => true,
                'min_entries' => 0,
                'max_entries' => 6,
                'unit_default' => SignatureCoordinatesModel::UNIT_MM,
            ],
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
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
            } else {
                $this->addFlash('error', 'Please correct the errors in the form below.');
            }
        }

        $explanation = '<ul class="mb-0"><li><strong>Two boxes are pre-placed.</strong> Drag one near the other — edges snap to align (snap to boxes).</li><li><code>snap_to_grid: 10</code> — position and size snap to a <strong>10 mm</strong> grid; move any box to see it jump to the grid.</li><li><code>snap_to_boxes: true</code> — when within ~10 px of another box edge, the box snaps to it.</li><li>Fixed PDF URL so the document loads automatically.</li></ul>';
        return $this->render('signature/index.html.twig', [
            'form' => $form,
            'page_title' => 'Snap to grid + snap to boxes',
            'config_explanation' => $explanation,
        ]);
    }

    /**
     * Guides and grid: show a visual grid on the PDF (e.g. every 10 mm) to align boxes.
     */
    #[Route('/demo-signature/guides-and-grid', name: 'app_signature_guides_and_grid', methods: ['GET', 'POST'])]
    public function guidesAndGrid(Request $request): Response
    {
        $explanation = '<ul class="mb-0"><li><code>show_grid: true</code> — a grid overlay is drawn on the PDF</li><li><code>grid_step: 10</code> — grid lines every 10 mm (in the form unit)</li><li>Use case: align signature boxes to a visible grid</li></ul>';
        return $this->signaturePage($request, 'Guides and grid (show_grid + grid_step)', [
            'show_grid' => true,
            'grid_step' => 10.0,
            'unit_default' => SignatureCoordinatesModel::UNIT_MM,
            'min_entries' => 0,
            'max_entries' => 6,
        ], $explanation);
    }

    /**
     * Viewer lazy load: PDF.js and the viewer script load only when the coordinates block is visible (IntersectionObserver).
     */
    #[Route('/demo-signature/lazy-load', name: 'app_signature_lazy_load', methods: ['GET', 'POST'])]
    public function lazyLoad(Request $request): Response
    {
        $explanation = '<ul class="mb-0"><li><code>viewer_lazy_load: true</code> — PDF.js and pdf-signable.js are loaded only when the widget enters the viewport</li><li>Uses <strong>IntersectionObserver</strong> with a small rootMargin so scripts load just before the block is visible</li><li>Use case: long pages or multiple widgets; reduces initial load when the form is below the fold</li></ul>';
        return $this->signaturePage($request, 'Viewer lazy load (IntersectionObserver)', [
            'viewer_lazy_load' => true,
            'min_entries' => 0,
            'max_entries' => 6,
        ], $explanation);
    }

    /**
     * Latest features combined: page restriction, sorted boxes, no overlap, snap options.
     */
    #[Route('/demo-signature/latest-features', name: 'app_signature_latest_features', methods: ['GET', 'POST'])]
    public function latestFeatures(Request $request): Response
    {
        $explanation = '<ul class="mb-0"><li><code>allowed_pages: [1]</code> — boxes only on page 1</li><li><code>sort_boxes: true</code> — saved order by page, Y, X</li><li><code>prevent_box_overlap: true</code> — no overlapping; frontend reverts invalid drag/resize</li><li><code>snap_to_grid: 5</code>, <code>snap_to_boxes: true</code> — snap while dragging</li><li>Combined demo for the latest form options</li></ul>';
        return $this->signaturePage($request, 'Latest features (page restriction + sort + no overlap + snap)', [
            'allowed_pages' => [1],
            'sort_boxes' => true,
            'prevent_box_overlap' => true,
            'snap_to_grid' => 5,
            'snap_to_boxes' => true,
            'min_entries' => 0,
            'max_entries' => 5,
        ], $explanation);
    }

    /**
     * Predefined boxes demo: model pre-filled with two boxes, fixed URL, max 5 boxes.
     */
    /**
     * Draw signature in box: each box has a canvas to draw (or finger); image shown in overlay. Low legal validity.
     */
    #[Route('/demo-signing/draw', name: 'app_signing_draw', methods: ['GET', 'POST'])]
    public function signingDraw(Request $request): Response
    {
        $explanation = '<ul class="mb-0"><li><code>enable_signature_capture: true</code> — draw pad per box</li><li>Draw with mouse or finger; image in overlay. Low legal validity.</li></ul>';
        return $this->signaturePage($request, 'Draw signature in box', [
            'enable_signature_capture' => true,
            'min_entries' => 0,
            'max_entries' => 4,
        ], $explanation);
    }

    /**
     * Draw or upload signature image per box.
     */
    #[Route('/demo-signing/upload', name: 'app_signing_upload', methods: ['GET', 'POST'])]
    public function signingUpload(Request $request): Response
    {
        $explanation = '<ul class="mb-0"><li><code>enable_signature_capture</code> + <code>enable_signature_upload: true</code></li><li>Draw or upload image per box</li></ul>';
        return $this->signaturePage($request, 'Draw or upload signature image', [
            'enable_signature_capture' => true,
            'enable_signature_upload' => true,
            'min_entries' => 0,
            'max_entries' => 4,
        ], $explanation);
    }

    /**
     * Legal disclaimer text and optional URL above the viewer.
     */
    #[Route('/demo-signing/legal-disclaimer', name: 'app_signing_legal_disclaimer', methods: ['GET', 'POST'])]
    public function signingLegalDisclaimer(Request $request): Response
    {
        $explanation = '<ul class="mb-0"><li><code>signing_legal_disclaimer</code> + optional URL</li><li>Inform users about legal effect</li></ul>';
        return $this->signaturePage($request, 'Legal disclaimer (signing)', [
            'signing_legal_disclaimer' => 'This is a <strong>simple signature</strong> (draw or image). It has no qualified legal validity.',
            'signing_legal_disclaimer_url' => '#',
            'min_entries' => 0,
            'max_entries' => 4,
        ], $explanation);
    }

    /**
     * Predefined boxes for signing only: boxes already placed, user only draws or uploads signature in each.
     */
    #[Route('/demo-signing/predefined-boxes', name: 'app_signing_predefined_boxes', methods: ['GET', 'POST'])]
    public function signingPredefinedBoxes(Request $request): Response
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

        $explanation = '<ul class="mb-0"><li><strong>Boxes already placed</strong> — two fixed positions (Signer 1, Signer 2) on page 1</li><li><code>min_entries: 2</code>, <code>max_entries: 2</code> — cannot add or remove boxes</li><li>User only <strong>draws or uploads</strong> the signature in each box</li></ul>';

        return $this->render('signature/index.html.twig', [
            'form' => $form,
            'page_title' => 'Predefined boxes — sign only (draw or upload)',
            'config_explanation' => $explanation,
        ]);
    }

    /**
     * Info page: signing options, AutoFirma, qualified signatures and legal validity.
     */
    #[Route('/demo-signing/options', name: 'app_signing_options', methods: ['GET'])]
    public function signingOptions(): Response
    {
        return $this->render('signature/signing_options.html.twig');
    }

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
            $coords = $model->getSignatureCoordinates();
            $this->addFlash('success', 'Coordinates saved (demo). ' . $this->formatCoordinatesForFlash($coords));
            // return $this->redirectToRoute('app_signature_predefined');
        }

        $explanation = '<ul class="mb-0"><li>Model pre-filled with two boxes (<code>signer_1</code>, <code>signer_2</code> on page 1)</li><li><code>url_field: false</code>, <code>max_entries: 5</code></li><li>User can move/resize existing boxes and add up to 3 more</li></ul>';

        return $this->render('signature/index.html.twig', [
            'form' => $form,
            'page_title' => 'Predefined boxes (2 initial boxes, max 5)',
            'config_explanation' => $explanation,
        ]);
    }

    /**
     * Renders a signature demo page with the given options and configuration explanation.
     *
     * @param Request               $request           The HTTP request (GET or POST)
     * @param string                $pageTitle         Title for the page
     * @param array<string, mixed>  $signatureOptions  Options passed to SignatureCoordinatesType
     * @param string                $configExplanation HTML explanation of the demo config
     *
     * @return Response The rendered page or redirect/JSON on success
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
            $coords = $model->getSignatureCoordinates();
            $this->addFlash('success', 'Coordinates saved (demo). ' . $this->formatCoordinatesForFlash($coords));
            // return $this->redirectToRoute($request->attributes->get('_route'));
        }

        return $this->render('signature/index.html.twig', [
            'form' => $form,
            'page_title' => $pageTitle,
            'config_explanation' => $configExplanation,
        ]);
    }

    /**
     * Returns whether the request prefers a JSON response (Accept: application/json or X-Requested-With: XMLHttpRequest).
     *
     * @param Request $request The HTTP request
     *
     * @return bool True if the client expects JSON
     */
    private function wantsJson(Request $request): bool
    {
        return $request->isXmlHttpRequest()
            || str_contains($request->headers->get('Accept', ''), 'application/json');
    }

    /**
     * Formats the coordinates model as an array of box data for JSON output.
     *
     * @param SignatureCoordinatesModel $model The coordinates model
     *
     * @return array<int, array{name: string, page: int, x: float, y: float, width: float, height: float, angle: float}>
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
                'angle' => $box->getAngle(),
            ];
        }
        return $out;
    }

    /**
     * Formats the signature coordinates model as HTML for flash messages (bullets, name first).
     *
     * @param SignatureCoordinatesModel $model The coordinates model
     *
     * @return string HTML fragment (unit, origin and list of boxes)
     */
    private function formatCoordinatesForFlash(SignatureCoordinatesModel $model): string
    {
        $boxes = $this->formatCoordinates($model);
        $unit = $model->getUnit();
        $origin = $model->getOrigin();
        $intro = sprintf('Unit: %s, origin: %s.', $unit, $origin);
        if ($boxes === []) {
            return $intro . ' No boxes.';
        }
        $items = array_map(static function (array $b) use ($unit): string {
            $name = htmlspecialchars($b['name'], ENT_QUOTES, 'UTF-8');
            $angle = isset($b['angle']) ? (float) $b['angle'] : 0.0;
            return sprintf(
                '<li><strong>%s</strong>: page %d, x=%s, y=%s, %s×%s (%s), angle=%s°</li>',
                $name,
                $b['page'],
                (string) $b['x'],
                (string) $b['y'],
                (string) $b['width'],
                (string) $b['height'],
                $unit,
                (string) $angle
            );
        }, $boxes);
        return $intro . ' <ul class="mb-0 mt-1">' . implode('', $items) . '</ul>';
    }
}
