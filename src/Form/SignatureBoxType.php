<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Form;

use Nowo\PdfSignableBundle\Model\SignatureBoxModel;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Form type for a single signature box: name, page, x, y, width, height.
 *
 * Used as entry type in SignatureCoordinatesType's collection. Name can be
 * free text (input) or a choice list via name_mode and name_choices.
 */
final class SignatureBoxType extends AbstractType
{
    /** Box name is a free-text input. */
    public const NAME_MODE_INPUT = 'input';

    /** Box name is chosen from a dropdown (name_choices). */
    public const NAME_MODE_CHOICE = 'choice';

    /**
     * Builds the form: name, page, width, height, x, y.
     *
     * @param array<string, mixed> $options Resolved options (name_mode, name_choices, etc.)
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if ($options['name_mode'] === self::NAME_MODE_CHOICE && $options['name_choices'] !== []) {
            // Pre-select first choice when name is empty (new box) so the dropdown shows a selected value
            $firstChoiceValue = array_values($options['name_choices'])[0] ?? null;
            $builder->addEventListener(FormEvents::PRE_SET_DATA, static function (FormEvent $event) use ($firstChoiceValue): void {
                $data = $event->getData();
                if ($data instanceof SignatureBoxModel && ($data->getName() === null || $data->getName() === '') && $firstChoiceValue !== null) {
                    $data->setName($firstChoiceValue);
                }
            });
            // No empty option: required choice with no "Select role" / placeholder (first real choice must be selected)
            $builder->add('name', ChoiceType::class, [
                'label' => $options['name_label'],
                'choices' => $options['name_choices'],
                'required' => true,
                'placeholder' => $options['choice_placeholder'],
                'constraints' => [new NotBlank(message: 'signature_box_type.name.required')],
                'attr' => [
                    'class' => 'signature-box-name form-control form-control-sm form-select',
                ],
                'row_attr' => ['class' => 'col-8 col-md-9 col-lg-10 mb-2'],
            ]);
        } else {
            $builder->add('name', TextType::class, [
                'label' => $options['name_label'],
                'required' => true,
                'constraints' => [new NotBlank(message: 'signature_box_type.name.required')],
                'attr' => [
                    'placeholder' => $options['name_placeholder'],
                    'class' => 'signature-box-name form-control form-control-sm',
                ],
                'row_attr' => ['class' => 'col-8 col-md-9 col-lg-10 mb-2'],
            ]);
        }

        $builder
            ->add('page', IntegerType::class, [
                'label' => 'signature_box_type.page.label',
                'attr' => ['min' => 1, 'step' => 1, 'class' => 'signature-box-page form-control form-control-sm'],
                'required' => true,
                'row_attr' => ['class' => 'col-4 col-md-3 col-lg-2 mb-2'],
            ])
            ->add('width', NumberType::class, [
                'label' => 'signature_box_type.width.label',
                'attr' => ['min' => 10, 'step' => '0.01', 'class' => 'signature-box-width form-control form-control-sm'],
                'row_attr' => ['class' => 'col mb-2'],
            ])
            ->add('height', NumberType::class, [
                'label' => 'signature_box_type.height.label',
                'attr' => ['min' => 10, 'step' => '0.01', 'class' => 'signature-box-height form-control form-control-sm'],
                'row_attr' => ['class' => 'col mb-2'],
            ])
            ->add('x', NumberType::class, [
                'label' => 'signature_box_type.x.label',
                'attr' => ['min' => 0, 'step' => '0.01', 'class' => 'signature-box-x form-control form-control-sm'],
                'row_attr' => ['class' => 'col mb-2'],
            ])
            ->add('y', NumberType::class, [
                'label' => 'signature_box_type.y.label',
                'attr' => ['min' => 0, 'step' => '0.01', 'class' => 'signature-box-y form-control form-control-sm'],
                'row_attr' => ['class' => 'col mb-2'],
            ]);
    }

    /**
     * Configures data_class, translation_domain and name field options.
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SignatureBoxModel::class,
            'translation_domain' => 'nowo_pdf_signable',

            'name_mode' => self::NAME_MODE_INPUT,
            'name_choices' => [],
            'name_label' => false,
            'name_placeholder' => 'signature_box_type.name.placeholder',
            'choice_placeholder' => false,
        ]);

        $resolver->setAllowedValues('name_mode', [self::NAME_MODE_INPUT, self::NAME_MODE_CHOICE]);
        $resolver->setAllowedTypes('name_choices', 'array');
        $resolver->setAllowedTypes('choice_placeholder', ['bool', 'string']);
    }
}
