<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Form;

use Nowo\PdfSignableBundle\Model\SignatureBoxModel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;

use function is_array;
use function is_int;
use function is_string;

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
                'label'       => $options['name_label'],
                'choices'     => $options['name_choices'],
                'required'    => true,
                'placeholder' => $options['choice_placeholder'],
                'empty_data'  => '',
                'constraints' => [new NotBlank(message: 'signature_box_type.name.required')],
                'attr'        => [
                    'class'             => 'signature-box-name form-control form-control-sm form-select',
                    'data-pdf-signable' => 'name',
                    'required'          => 'required',
                ],
                'row_attr' => ['class' => 'col-8 col-md-9 col-lg-10 mb-2'],
            ]);
        } else {
            $builder->add('name', TextType::class, [
                'label'       => $options['name_label'],
                'required'    => true,
                'empty_data'  => '',
                'constraints' => [new NotBlank(message: 'signature_box_type.name.required')],
                'attr'        => [
                    'placeholder'       => $options['name_placeholder'],
                    'class'             => 'signature-box-name form-control form-control-sm',
                    'data-pdf-signable' => 'name',
                    'required'          => 'required',
                ],
                'row_attr' => ['class' => 'col-8 col-md-9 col-lg-10 mb-2'],
            ]);
        }

        $allowedPages = $options['allowed_pages'];
        if ($allowedPages !== null && $allowedPages !== []) {
            $allowedPages = array_map('intval', array_values($allowedPages));
            $allowedPages = array_values(array_unique(array_filter($allowedPages, static fn (int $p) => $p >= 1)));
            $pageChoices  = array_combine($allowedPages, $allowedPages);
            $builder->add('page', ChoiceType::class, [
                'label'       => 'signature_box_type.page.label',
                'choices'     => $pageChoices,
                'required'    => true,
                'attr'        => ['class' => 'signature-box-page form-control form-control-sm form-select', 'data-pdf-signable' => 'page'],
                'constraints' => [new Choice(choices: $allowedPages, message: 'signature_box_type.page.not_allowed')],
                'row_attr'    => ['class' => 'col-4 col-md-3 col-lg-2 mb-2'],
            ]);
        } else {
            $builder->add('page', IntegerType::class, [
                'label'    => 'signature_box_type.page.label',
                'attr'     => ['min' => 1, 'step' => 1, 'class' => 'signature-box-page form-control form-control-sm', 'data-pdf-signable' => 'page'],
                'required' => true,
                'row_attr' => ['class' => 'col-4 col-md-3 col-lg-2 mb-2'],
            ]);
        }

        $defaultWidth  = $options['default_box_width'] !== null ? (float) $options['default_box_width'] : null;
        $defaultHeight = $options['default_box_height'] !== null ? (float) $options['default_box_height'] : null;
        $lockWidth     = $options['lock_box_width'];
        $lockHeight    = $options['lock_box_height'];
        $minWidth      = $options['min_box_width'] !== null ? (float) $options['min_box_width'] : 10;
        $minHeight     = $options['min_box_height'] !== null ? (float) $options['min_box_height'] : 10;

        $builder->addEventListener(FormEvents::PRE_SET_DATA, static function (FormEvent $event) use ($defaultWidth, $defaultHeight, $lockWidth, $lockHeight): void {
            $data = $event->getData();
            if (!$data instanceof SignatureBoxModel) {
                return;
            }
            if ($lockWidth && $defaultWidth !== null) {
                $data->setWidth($defaultWidth);
            } elseif ($data->getWidth() === null && $defaultWidth !== null) {
                $data->setWidth($defaultWidth);
            }
            if ($lockHeight && $defaultHeight !== null) {
                $data->setHeight($defaultHeight);
            } elseif ($data->getHeight() === null && $defaultHeight !== null) {
                $data->setHeight($defaultHeight);
            }
        });

        $builder
            ->add('width', NumberType::class, [
                'label'      => 'signature_box_type.width.label',
                'empty_data' => $defaultWidth,
                'attr'       => ['min' => $minWidth, 'step' => '0.01', 'class' => 'signature-box-width form-control form-control-sm', 'data-pdf-signable' => 'width'],
                'row_attr'   => ['class' => 'col mb-2'],
            ])
            ->add('height', NumberType::class, [
                'label'      => 'signature_box_type.height.label',
                'empty_data' => $defaultHeight,
                'attr'       => ['min' => $minHeight, 'step' => '0.01', 'class' => 'signature-box-height form-control form-control-sm', 'data-pdf-signable' => 'height'],
                'row_attr'   => ['class' => 'col mb-2'],
            ])
            ->add('x', NumberType::class, [
                'label'    => 'signature_box_type.x.label',
                'attr'     => ['min' => 0, 'step' => '0.01', 'class' => 'signature-box-x form-control form-control-sm', 'data-pdf-signable' => 'x'],
                'row_attr' => ['class' => 'col mb-2'],
            ])
            ->add('y', NumberType::class, [
                'label'    => 'signature_box_type.y.label',
                'attr'     => ['min' => 0, 'step' => '0.01', 'class' => 'signature-box-y form-control form-control-sm', 'data-pdf-signable' => 'y'],
                'row_attr' => ['class' => 'col mb-2'],
            ]);
        if ($options['angle_enabled']) {
            $builder->add('angle', NumberType::class, [
                'label'      => 'signature_box_type.angle.label',
                'empty_data' => 0,
                'attr'       => ['min' => -180, 'max' => 180, 'step' => '0.1', 'class' => 'signature-box-angle form-control form-control-sm', 'data-pdf-signable' => 'angle'],
                'row_attr'   => ['class' => 'col mb-2'],
            ]);
        }
        if ($options['enable_signature_capture'] || $options['enable_signature_upload']) {
            $builder->add('signatureData', HiddenType::class, [
                'required' => false,
                'attr'     => ['class' => 'signature-box-signature-data', 'data-pdf-signable' => 'signature-data'],
            ]);
            $builder->add('signedAt', HiddenType::class, [
                'required' => false,
                'attr'     => ['class' => 'signature-box-signed-at', 'data-pdf-signable' => 'signed-at'],
            ]);
        }
    }

    /**
     * Passes signature capture options to the view for the widget (draw pad / upload).
     *
     * @param FormView $view The form view
     * @param FormInterface $form The form
     * @param array<string, mixed> $options Resolved options (enable_signature_capture, signing_only, lock_box_*, etc.)
     */
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['enable_signature_capture'] = $options['enable_signature_capture'];
        $view->vars['enable_signature_upload']  = $options['enable_signature_upload'];
        $view->vars['signing_only']             = $options['signing_only'];
        $view->vars['hide_coordinate_fields']   = $options['hide_coordinate_fields'];
        $view->vars['default_box_width']        = $options['default_box_width'];
        $view->vars['default_box_height']       = $options['default_box_height'];
        $view->vars['lock_box_width']           = $options['lock_box_width'];
        $view->vars['lock_box_height']          = $options['lock_box_height'];
        $view->vars['min_box_width']            = $options['min_box_width'];
        $view->vars['min_box_height']           = $options['min_box_height'];
        $view->vars['hide_position_fields']     = $options['hide_position_fields'];
    }

    /**
     * Configures default options and allowed types for name, page and box fields.
     *
     * @param OptionsResolver $resolver The options resolver
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'         => SignatureBoxModel::class,
            'translation_domain' => 'nowo_pdf_signable',

            'name_mode'          => self::NAME_MODE_INPUT,
            'name_choices'       => [],
            'name_label'         => false,
            'name_placeholder'   => 'signature_box_type.name.placeholder',
            'choice_placeholder' => false,

            /* @see ROADMAP.md "Page restriction" — limit which pages boxes can be placed on */
            'allowed_pages' => null,
            /* When true, show the rotation angle field (degrees). When false, angle is not in the form and defaults to 0. */
            'angle_enabled' => false,
            /* When true, show draw pad (canvas) to capture signature as image (low legal validity). */
            'enable_signature_capture' => false,
            /* When true, show file input to upload a signature image (same storage as draw). */
            'enable_signature_upload' => false,
            /* When true, hide coordinate fields and show only box name (read-only) and signature capture (for predefined sign-only flows). */
            'signing_only' => false,
            /* When true, hide width, height, x, y (and angle) fields in the UI; values are still submitted (e.g. from PDF overlay). */
            'hide_coordinate_fields' => false,
            /* Default width/height for new boxes (form unit). When lock_box_* is true, the field is hidden and this value is used. */
            'default_box_width'  => null,
            'default_box_height' => null,
            'lock_box_width'     => false,
            'lock_box_height'    => false,
            /* Minimum width/height for signature boxes (form unit). Null = use 10 in HTML and no client clamp. */
            'min_box_width'  => null,
            'min_box_height' => null,
            /* When true, hide x and y fields in the UI; values are still submitted (e.g. from PDF overlay). */
            'hide_position_fields' => false,
            /* Additional constraints on the whole box (SignatureBoxModel); e.g. Callback for custom validation */
            'constraints' => [],
        ]);

        $resolver->setAllowedValues('name_mode', [self::NAME_MODE_INPUT, self::NAME_MODE_CHOICE]);
        $resolver->setAllowedTypes('constraints', 'array');
        $resolver->setAllowedTypes('angle_enabled', 'bool');
        $resolver->setAllowedTypes('enable_signature_capture', 'bool');
        $resolver->setAllowedTypes('enable_signature_upload', 'bool');
        $resolver->setAllowedTypes('signing_only', 'bool');
        $resolver->setAllowedTypes('hide_coordinate_fields', 'bool');
        $resolver->setAllowedTypes('default_box_width', ['float', 'int', 'null']);
        $resolver->setAllowedTypes('default_box_height', ['float', 'int', 'null']);
        $resolver->setAllowedTypes('lock_box_width', 'bool');
        $resolver->setAllowedTypes('lock_box_height', 'bool');
        $resolver->setAllowedTypes('min_box_width', ['float', 'int', 'null']);
        $resolver->setAllowedTypes('min_box_height', ['float', 'int', 'null']);
        $resolver->setAllowedTypes('hide_position_fields', 'bool');
        $resolver->setAllowedTypes('name_choices', 'array');
        $resolver->setAllowedTypes('choice_placeholder', ['bool', 'string']);
        $resolver->setAllowedTypes('allowed_pages', ['array', 'null']);
        $resolver->setAllowedValues('allowed_pages', static function ($value): bool {
            if ($value === null) {
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
