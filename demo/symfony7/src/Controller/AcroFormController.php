<?php

declare(strict_types=1);

namespace App\Controller;

use Nowo\PdfSignableBundle\AcroForm\AcroFormFieldEdit;
use Nowo\PdfSignableBundle\Form\AcroFormFieldEditType;
use Nowo\PdfSignableBundle\Model\AcroFormPageModel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use function is_array;

/**
 * Demo controller: AcroForm editor (save/load overrides, apply to PDF, process).
 */
class AcroFormController extends AbstractController
{
    private const FNMT_ACROFORM_PDF_URL = 'https://www.sede.fnmt.gob.es/documents/10445900/10545713/Contrato_emision_persona_fisica_ac_usuarios.pdf';

    public function __construct(
        #[Autowire(param: 'nowo_pdf_signable.debug')]
        private readonly bool $debug = false,
    ) {
    }

    #[Route('/demo-signature/acroform-editor', name: 'app_signature_acroform_editor', methods: ['GET', 'POST'])]
    public function acroformEditor(Request $request): Response
    {
        $explanation = '<ul class="mb-0"><li><strong>AcroForm editor (default)</strong> — PDF viewer (FNMT PDF preloaded) plus a panel to <strong>save and load overrides</strong>.</li><li>Uses config alias <code>default</code>: <code>url_field: false</code> and <code>document_key_field: false</code> — the PDF URL and Document key input rows are hidden.</li><li>Endpoints <code>GET/POST /pdf-signable/acroform/overrides</code> (session storage).</li></ul>';

        return $this->renderAcroFormEditor($request, 'demo-fnmt-acroform', 'AcroForm editor (save/load overrides)', $explanation, [
            'config' => 'default',
        ]);
    }

    #[Route('/demo-signature/acroform-editor-label-choice', name: 'app_signature_acroform_editor_label_choice', methods: ['GET', 'POST'])]
    public function acroformEditorLabelChoice(Request $request): Response
    {
        $explanation = '<ul class="mb-0"><li><strong>Label as dropdown</strong> — when editing a field, the label is a <code>select</code> with predefined options (Nombre, Apellidos, DNI, Fecha, Firma) plus <strong>Otro</strong> for free text.</li></ul>';

        return $this->renderAcroFormEditor($request, 'demo-fnmt-acroform-label-choice', 'AcroForm editor — Label as dropdown', $explanation, [
            'config' => 'label_dropdown',
        ]);
    }

    #[Route('/demo-signature/acroform-editor-no-coords', name: 'app_signature_acroform_editor_no_coords', methods: ['GET', 'POST'])]
    public function acroformEditorNoCoords(Request $request): Response
    {
        $explanation = '<ul class="mb-0"><li><strong>Coordinates hidden</strong> — when editing a field, the rect input is not shown. Config: <code>show_field_rect: false</code>.</li></ul>';

        return $this->renderAcroFormEditor($request, 'demo-fnmt-acroform-no-coords', 'AcroForm editor — Coordinates hidden', $explanation, [
            'show_field_rect' => false,
        ]);
    }

    #[Route('/demo-signature/acroform-editor-custom-fonts', name: 'app_signature_acroform_editor_custom_fonts', methods: ['GET', 'POST'])]
    public function acroformEditorCustomFonts(Request $request): Response
    {
        $explanation = '<ul class="mb-0"><li><strong>Custom font options</strong> — font size is a <code>select</code> and font family is limited to the configured list.</li></ul>';

        return $this->renderAcroFormEditor($request, 'demo-fnmt-acroform-custom-fonts', 'AcroForm editor — Custom font options', $explanation, [
            'config' => 'with_fonts',
        ]);
    }

    #[Route('/demo-signature/acroform-editor-all-options', name: 'app_signature_acroform_editor_all_options', methods: ['GET', 'POST'])]
    public function acroformEditorAllOptions(Request $request): Response
    {
        $explanation = '<ul class="mb-0"><li><strong>All options combined</strong> — label dropdown, coordinates hidden, custom font lists.</li></ul>';

        return $this->renderAcroFormEditor($request, 'demo-fnmt-acroform-all-options', 'AcroForm editor — All options', $explanation, [
            'config' => 'all_options',
        ]);
    }

    #[Route('/demo-signature/acroform-editor-min-size', name: 'app_signature_acroform_editor_min_size', methods: ['GET', 'POST'])]
    public function acroformEditorMinSize(Request $request): Response
    {
        $explanation = '<ul class="mb-0"><li>Same as <strong>AcroForm editor</strong> but with <code>min_field_width: 24</code>, <code>min_field_height: 24</code> (PDF points).</li></ul>';

        return $this->renderAcroFormEditor($request, 'demo-fnmt-acroform-min-size', 'AcroForm editor (min field size 24 pt)', $explanation, [
            'min_field_width'  => 24.0,
            'min_field_height' => 24.0,
        ]);
    }

    #[Route('/demo-signature/acroform-editor-with-url', name: 'app_signature_acroform_editor_with_url', methods: ['GET', 'POST'])]
    public function acroformEditorWithUrl(Request $request): Response
    {
        $explanation = '<ul class="mb-0"><li><strong>URL field visible</strong> — config <code>with_url</code> with <code>url_field: true</code>. The PDF URL input row is shown (compare with default which has <code>url_field: false</code>).</li></ul>';

        return $this->renderAcroFormEditor($request, 'demo-fnmt-acroform-with-url', 'AcroForm editor — URL field visible', $explanation, [
            'config' => 'with_url',
        ]);
    }

    #[Route('/demo-signature/acroform-editor-field-names-map', name: 'app_signature_acroform_editor_field_names_map', methods: ['GET', 'POST'])]
    public function acroformEditorFieldNamesMap(Request $request): Response
    {
        $explanation = '<ul class="mb-0"><li><strong>Field names as associative array</strong> — <code>field_name_choices</code> as a map (label => value). In YAML: <code>Nombre: nombre</code>, <code>Apellidos: apellidos</code>, etc.</li></ul>';

        return $this->renderAcroFormEditor($request, 'demo-fnmt-acroform-field-names-map', 'AcroForm editor — Field names (map)', $explanation, [
            'config' => 'field_names_map',
        ]);
    }

    #[Route('/demo-signature/acroform-editor-field-names-pipe', name: 'app_signature_acroform_editor_field_names_pipe', methods: ['GET', 'POST'])]
    public function acroformEditorFieldNamesPipe(Request $request): Response
    {
        $explanation = '<ul class="mb-0"><li><strong>Field names as value|Label list</strong> — <code>field_name_choices: [\'nombre|Nombre\', \'apellidos|Apellidos\', ...]</code>.</li></ul>';

        return $this->renderAcroFormEditor($request, 'demo-fnmt-acroform-field-names-pipe', 'AcroForm editor — Field names (value|Label)', $explanation, [
            'config' => 'field_names_pipe',
        ]);
    }

    /** @param array<string, mixed> $options */
    private function renderAcroFormEditor(Request $request, string $documentKey, string $pageTitle, string $explanation, array $options = []): Response
    {
        $configName = $options['config'] ?? null;
        $resolved   = $options;
        if ($configName !== null && $configName !== '') {
            $acroformConfigs = $this->getParameter('nowo_pdf_signable.acroform.configs');
            if (is_array($acroformConfigs) && isset($acroformConfigs[$configName]) && is_array($acroformConfigs[$configName])) {
                $resolved = array_merge($resolved, $acroformConfigs[$configName]);
            }
        }

        $model = new AcroFormPageModel();
        $model->setPdfUrl(self::FNMT_ACROFORM_PDF_URL);
        $model->setDocumentKey($documentKey);

        $acroformEditForm = $this->createForm(AcroFormFieldEditType::class, new AcroFormFieldEdit(), [
            'field_name_mode'       => $resolved['field_name_mode'] ?? $resolved['label_mode'] ?? $this->getParameter('nowo_pdf_signable.acroform.field_name_mode'),
            'field_name_choices'    => $resolved['field_name_choices'] ?? $resolved['label_choices'] ?? $this->getParameter('nowo_pdf_signable.acroform.field_name_choices'),
            'field_name_other_text' => $resolved['field_name_other_text'] ?? $resolved['label_other_text'] ?? $this->getParameter('nowo_pdf_signable.acroform.field_name_other_text'),
            'show_field_rect'       => $resolved['show_field_rect'] ?? $this->getParameter('nowo_pdf_signable.acroform.show_field_rect'),
            'font_sizes'            => $resolved['font_sizes'] ?? $this->getParameter('nowo_pdf_signable.acroform.font_sizes'),
            'font_families'         => $resolved['font_families'] ?? $this->getParameter('nowo_pdf_signable.acroform.font_families'),
        ])->createView();

        $acroformOptions = array_merge([
            'pdf_url'            => self::FNMT_ACROFORM_PDF_URL,
            'document_key'       => $documentKey,
            'load_url'           => $this->generateUrl('nowo_pdf_signable_acroform_overrides_load'),
            'post_url'           => $this->generateUrl('nowo_pdf_signable_acroform_overrides_save'),
            'apply_url'          => $this->generateUrl('nowo_pdf_signable_acroform_apply'),
            'process_url'        => $this->generateUrl('nowo_pdf_signable_acroform_process'),
            'debug'              => $this->debug,
            'acroform_edit_form' => $acroformEditForm,
        ], $resolved);

        $form = $this->createForm(\App\Form\AcroFormPageType::class, $model, [
            'acroform_options' => $acroformOptions,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->addFlash('success', 'Form saved (demo).');
        }
        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('error', 'Please correct the errors in the form below.');
        }

        return $this->render('signature/acroform_editor.html.twig', [
            'form'               => $form,
            'page_title'         => $pageTitle,
            'config_explanation' => $explanation,
        ]);
    }
}
