<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Twig;

use Nowo\PdfSignableBundle\AcroForm\AcroFormFieldEdit;
use Nowo\PdfSignableBundle\Form\AcroFormEditorPanelType;
use Nowo\PdfSignableBundle\Form\AcroFormFieldEditType;
use Override;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormView;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

use function array_key_exists;
use function is_array;

/**
 * Builds the Symfony Form views of the AcroForm editor panel (REQ-TWIG-005), so
 * acroform/editor_root.html.twig and acroform/_edit_modal_body.html.twig never fall back to
 * raw HTML controls, even when a host includes them without passing a form.
 *
 * - nowo_pdf_signable_acroform_panel_form(documentKey) — document key + overrides JSON (AcroFormEditorPanelType)
 * - nowo_pdf_signable_acroform_edit_form(config) — edit-field modal (AcroFormFieldEditType, same acroform-edit-* ids)
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class AcroFormFormsTwigExtension extends AbstractExtension
{
    /** Editor config keys forwarded to AcroFormFieldEditType when present. */
    private const EDIT_OPTION_KEYS = [
        'field_name_mode',
        'field_name_choices',
        'field_name_other_text',
        'show_field_rect',
        'font_sizes',
        'font_families',
    ];

    public function __construct(
        private readonly FormFactoryInterface $formFactory,
    ) {
    }

    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('nowo_pdf_signable_acroform_panel_form', $this->panelForm(...)),
            new TwigFunction('nowo_pdf_signable_acroform_edit_form', $this->editForm(...)),
        ];
    }

    public function panelForm(?string $documentKey = ''): FormView
    {
        return $this->formFactory->create(AcroFormEditorPanelType::class, null, [
            'document_key' => $documentKey ?? '',
        ])->createView();
    }

    /**
     * @param array<string, mixed>|null $config Editor config (field_name_mode, field_name_choices, …); missing keys use the bundle defaults
     */
    public function editForm(?array $config = []): FormView
    {
        // Not required in HTML: the modal lives inside the host page form and must not block its submit.
        $options = ['required' => false];
        foreach (self::EDIT_OPTION_KEYS as $key) {
            if (is_array($config) && array_key_exists($key, $config) && $config[$key] !== null) {
                $options[$key] = $config[$key];
            }
        }

        return $this->formFactory->create(AcroFormFieldEditType::class, new AcroFormFieldEdit(), $options)->createView();
    }
}
