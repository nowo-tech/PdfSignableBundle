<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Form;

use Nowo\PdfSignableBundle\Model\SignatureBoxModel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Form type for a single signature box: name, page, x, y, width, height.
 *
 * Used as entry type in SignatureCoordinatesType's collection. Name can be
 * free text (input) or a choice list via name_mode and name_choices.
 * Optional rotation angle when angle_enabled is true; page can be restricted via allowed_pages.
 */
final class SignatureBoxType extends AbstractType
{
    /** Box name is a free-text input. */
    public const NAME_MODE_INPUT = 'input';

    /** Box name is chosen from a dropdown (name_choices). */
    public const NAME_MODE_CHOICE = 'choice';

    /**
     * Builds the form: name, page, width, height, x, y (and optionally angle).
     *
     * @param FormBuilderInterface $builder Form builder
     * @param array<string, mixed> $options Resolved options (name_mode, name_choices, allowed_pages, angle_enabled, etc.)
     *
     * @return void
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if (self::NAME_MODE_CHOICE === $options['name_mode'] && [] !== $options['name_choices']) {
            // Pre-select first choice when name is empty (new box) so the dropdown shows a selected value
            $firstChoiceValue = array_values($options['name_choices'])[0] ?? null;
            $builder->addEventListener(FormEvents::PRE_SET_DATA, static function (FormEvent $event) use ($firstChoiceValue): void {
                $data = $event->getData();
                if ($data instanceof SignatureBoxModel && (null === $data->getName() || '' === $data->getName()) && null !== $firstChoiceValue) {
                    $data->setName($firstChoiceValue);
                }
            });
            // No empty option: required choice with no "Select role" / placeholder (first real choice must be selected)
            $builder->add('name', ChoiceType::class, [
                'label' => $options['name_label'],
                'choices' => $options['name_choices'],
                'required' => true,
                'placeholder' => $options['choice_placeholder'],
                'empty_data' => '',
                'constraints' => [new NotBlank(message: 'signature_box_type.name.required')],
                'attr' => [
                    'class' => 'signature-box-name form-control form-control-sm form-select',
                    'required' => 'required',
                ],
                'row_attr' => ['class' => 'col-8 col-md-9 col-lg-10 mb-2'],
            ]);
        } else {
            $builder->add('name', TextType::class, [
                'label' => $options['name_label'],
                'required' => true,
                'empty_data' => '',
                'constraints' => [new NotBlank(message: 'signature_box_type.name.required')],
                'attr' => [
                    'placeholder' => $options['name_placeholder'],
                    'class' => 'signature-box-name form-control form-control-sm',
                    'required' => 'required',
                ],
                'row_attr' => ['class' => 'col-8 col-md-9 col-lg-10 mb-2'],
            ]);
        }

        $allowedPages = $options['allowed_pages'];
        if (null !== $allowedPages && [] !== $allowedPages) {
            $allowedPages = array_map('intval', array_values($allowedPages));
            $allowedPages = array_values(array_unique(array_filter($allowedPages, fn (int $p) => $p >= 1)));
            $pageChoices = array_combine($allowedPages, $allowedPages);
            $builder->add('page', ChoiceType::class, [
                'label' => 'signature_box_type.page.label',
                'choices' => $pageChoices,
                'required' => true,
                'attr' => ['class' => 'signature-box-page form-control form-control-sm form-select'],
                'constraints' => [new Choice(choices: $allowedPages, message: 'signature_box_type.page.not_allowed')],
                'row_attr' => ['class' => 'col-4 col-md-3 col-lg-2 mb-2'],
            ]);
        } else {
            $builder->add('page', IntegerType::class, [
                'label' => 'signature_box_type.page.label',
                'attr' => ['min' => 1, 'step' => 1, 'class' => 'signature-box-page form-control form-control-sm'],
                'required' => true,
                'row_attr' => ['class' => 'col-4 col-md-3 col-lg-2 mb-2'],
            ]);
        }

        $builder
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
        if ($options['angle_enabled']) {
            $builder->add('angle', NumberType::class, [
                'label' => 'signature_box_type.angle.label',
                'empty_data' => 0,
                'attr' => ['min' => -180, 'max' => 180, 'step' => '0.1', 'class' => 'signature-box-angle form-control form-control-sm'],
                'row_attr' => ['class' => 'col mb-2'],
            ]);
        }
    }

    /**
     * Configures default options and allowed types for name, page and box fields.
     *
     * @param OptionsResolver $resolver The options resolver
     *
     * @return void
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

            /* @see ROADMAP.md "Page restriction" — limit which pages boxes can be placed on */
            'allowed_pages' => null,
            /* When true, show the rotation angle field (degrees). When false, angle is not in the form and defaults to 0. */
            'angle_enabled' => false,
            /* Additional constraints on the whole box (SignatureBoxModel); e.g. Callback for custom validation */
            'constraints' => [],
        ]);

        $resolver->setAllowedValues('name_mode', [self::NAME_MODE_INPUT, self::NAME_MODE_CHOICE]);
        $resolver->setAllowedTypes('constraints', 'array');
        $resolver->setAllowedTypes('angle_enabled', 'bool');
        $resolver->setAllowedTypes('name_choices', 'array');
        $resolver->setAllowedTypes('choice_placeholder', ['bool', 'string']);
        $resolver->setAllowedTypes('allowed_pages', ['array', 'null']);
        $resolver->setAllowedValues('allowed_pages', static function ($value): bool {
            if (null === $value) {
                return true;
            }
            if (!is_array($value)) {
                return false;
            }
            foreach ($value as $v) {
                $p = is_int($v) ? $v : (is_string($v) && is_numeric($v) ? (int) $v : -1);
                if ($p < 1) {
                    return false;
                }
            }

            return true;
        });
    }
}
