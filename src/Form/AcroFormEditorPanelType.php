<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Form;

use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
use Nowo\PdfSignableBundle\NowoPdfSignableBundle;
use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Client-side controls of the AcroForm editor panel (REQ-TWIG-005).
 *
 * Never submitted on its own: acroform-editor.js reads `#acroform-document-key` and
 * `#acroform-overrides-json` and talks to the overrides API. Both fields are unmapped and
 * keep those ids. Rendered with form_label / form_widget inside editor_root.html.twig.
 *
 * @extends AbstractType<mixed>
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
#[FormKitConfig(NowoPdfSignableBundle::FORM_KIT_PROFILE)]
final class AcroFormEditorPanelType extends AbstractType
{
    use FormKitFieldTrait;
    use FormOptionsTrait;

    /** HTML ids acroform-editor.js relies on. */
    public const FIELD_IDS = [
        'documentKey'   => 'acroform-document-key',
        'overridesJson' => 'acroform-overrides-json',
    ];

    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed> $options
     */
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addKitField($builder, 'documentKey', TextType::class, [
            'mapped'     => false,
            'required'   => false,
            'data'       => (string) $options['document_key'],
            'label'      => 'acroform_editor.document_key_label',
            'label_attr' => ['class' => 'form-label small'],
            'attr'       => [
                'class'       => 'form-control form-control-sm',
                'placeholder' => 'acroform_editor.document_key_placeholder',
            ],
        ]);
        $this->addKitField($builder, 'overridesJson', TextareaType::class, [
            'mapped'     => false,
            'required'   => false,
            'label'      => 'acroform_editor.draft_label',
            'label_attr' => ['class' => 'form-label small'],
            'attr'       => [
                'class'       => 'form-control font-monospace small',
                'rows'        => 6,
                'placeholder' => 'acroform_editor.draft_placeholder',
                'title'       => 'acroform_editor.draft_title',
            ],
        ]);
    }

    /**
     * @param FormView<mixed> $view
     * @param FormInterface<mixed> $form
     * @param array<string, mixed> $options
     */
    #[Override]
    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        foreach (self::FIELD_IDS as $name => $id) {
            $view->children[$name]->vars['id'] = $id;
        }
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection'    => false,
            'translation_domain' => 'NowoPdfSignableBundle',
            'document_key'       => '',
        ]);
        $resolver->setAllowedTypes('document_key', ['string', 'null']);
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'acroform_editor_panel';
    }
}
