<?php

declare(strict_types=1);

namespace App\Form;

use Nowo\PdfSignableBundle\Form\AcroFormEditorType;
use Nowo\PdfSignableBundle\Model\AcroFormPageModel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Demo form type that embeds AcroFormEditorType (PDF viewer + AcroForm panel).
 *
 * The child "acroFormEditor" is not a property of AcroFormPageModel; it is unmapped.
 * We sync the same model to the child for display and copy back pdfUrl/documentKey on submit.
 *
 * Accepts optional "acroform_options" to customize the AcroForm block per page
 * (e.g. config: 'with_fonts', document_key, label_mode, font_sizes).
 */
final class AcroFormPageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $acroformOptions = array_merge([], $options['acroform_options'] ?? []);
        $builder->add('acroFormEditor', AcroFormEditorType::class, array_merge([
            'label'  => false,
            'mapped' => false,
        ], $acroformOptions));

        $builder->addEventListener(FormEvents::PRE_SET_DATA, static function (FormEvent $event): void {
            $data = $event->getData();
            if ($data instanceof AcroFormPageModel && $event->getForm()->has('acroFormEditor')) {
                $event->getForm()->get('acroFormEditor')->setData($data);
            }
        });

        $builder->addEventListener(FormEvents::POST_SUBMIT, static function (FormEvent $event): void {
            $form = $event->getForm();
            $data = $form->getData();
            if ($data instanceof AcroFormPageModel && $form->has('acroFormEditor')) {
                $childData = $form->get('acroFormEditor')->getData();
                if ($childData instanceof AcroFormPageModel) {
                    $data->setPdfUrl($childData->getPdfUrl());
                    $data->setDocumentKey($childData->getDocumentKey());
                }
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'       => AcroFormPageModel::class,
            'acroform_options' => [],
        ]);
        $resolver->setAllowedTypes('acroform_options', 'array');
    }
}
