<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Form;

use Nowo\PdfSignableBundle\AcroForm\AcroFormFieldEdit;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form type for editing a single AcroForm field (modal).
 *
 * Options (custom config overrides bundle defaults when passed):
 * - field_name_mode: 'input' | 'choice'
 * - field_name_choices: list of strings or [value, label?]
 * - field_name_other_text: string for "Other" option in field name select
 * - show_field_rect: bool
 * - font_sizes: list of int (empty = number input)
 * - font_families: list of string or [value, label?]
 *
 * Renders with fixed IDs (acroform-edit-*) so the AcroForm editor JS can fill/read values.
 * Only field name is shown/edited (no label).
 */
final class AcroFormFieldEditType extends AbstractType
{
    public function __construct(
        #[Autowire(param: 'nowo_pdf_signable.acroform.field_name_mode')]
        private readonly string $fieldNameMode = 'input',
        #[Autowire(param: 'nowo_pdf_signable.acroform.field_name_choices')]
        private readonly array $fieldNameChoices = [],
        #[Autowire(param: 'nowo_pdf_signable.acroform.field_name_other_text')]
        private readonly string $fieldNameOtherText = '',
        #[Autowire(param: 'nowo_pdf_signable.acroform.show_field_rect')]
        private readonly bool $showFieldRect = true,
        #[Autowire(param: 'nowo_pdf_signable.acroform.font_sizes')]
        private readonly array $fontSizes = [],
        #[Autowire(param: 'nowo_pdf_signable.acroform.font_families')]
        private readonly array $fontFamilies = [],
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $fieldNameMode = $options['field_name_mode'];
        $fieldNameChoices = $options['field_name_choices'];
        $fieldNameOtherText = $options['field_name_other_text'];
        $showFieldRect = $options['show_field_rect'];
        $fontSizes = $options['font_sizes'];
        $fontFamilies = $options['font_families'];

        $rowFieldName = ['row_attr' => ['class' => 'mb-2 acroform-edit-group acroform-edit-group-field-name']];
        $rowControlType = ['row_attr' => ['class' => 'mb-2 acroform-edit-group acroform-edit-group-control-type']];
        $rowRect = ['row_attr' => ['class' => 'mb-2 acroform-edit-group acroform-edit-group-rect']];
        $rowMaxLen = ['row_attr' => ['class' => 'mb-2 acroform-edit-group acroform-edit-group-max-len', 'style' => 'display:none;']];
        $rowFlags = ['row_attr' => ['class' => 'mb-2 acroform-edit-group acroform-edit-group-flags']];
        $rowOptions = ['row_attr' => ['class' => 'mb-2 acroform-edit-group acroform-edit-group-options', 'style' => 'display:none;']];
        $rowDefaultText = ['row_attr' => ['class' => 'mb-2 acroform-edit-group acroform-edit-group-default-text', 'style' => 'display:none;']];
        $rowDefaultCheckbox = ['row_attr' => ['class' => 'mb-2 acroform-edit-group acroform-edit-group-default-checkbox', 'style' => 'display:none;']];
        $rowCheckboxOpts = ['row_attr' => ['class' => 'mb-2 acroform-edit-group acroform-edit-group-checkbox-options', 'style' => 'display:none;']];
        $rowFont = ['row_attr' => ['class' => 'mb-2 acroform-edit-group acroform-edit-group-font', 'style' => 'display:none;']];

        $builder
            ->add('fieldId', HiddenType::class, ['attr' => ['id' => 'acroform-edit-field-id']])
            ->add('page', HiddenType::class, ['attr' => ['id' => 'acroform-edit-page'], 'required' => false])
            ->add('fieldName', $this->getFieldNameFieldType($fieldNameMode, $fieldNameChoices), array_merge($this->getFieldNameFieldOptions($fieldNameMode, $fieldNameChoices, $fieldNameOtherText), $rowFieldName));

        if ('choice' === $fieldNameMode && [] !== $fieldNameChoices && '' !== $fieldNameOtherText) {
            $builder->add('fieldNameOther', TextType::class, array_merge([
                'mapped' => false,
                'required' => false,
                'attr' => ['id' => 'acroform-edit-field-name-other', 'class' => 'form-control form-control-sm', 'autocomplete' => 'off'],
                'label_attr' => ['class' => 'form-label small'],
            ], $rowFieldName));
        }

        $builder
        ->add('controlType', ChoiceType::class, array_merge([
            'choices' => [
                'text' => 'text',
                'textarea' => 'textarea',
                'checkbox' => 'checkbox',
                'select' => 'select',
                'choice' => 'choice',
            ],
            'attr' => ['id' => 'acroform-edit-control-type', 'class' => 'form-select form-select-sm'],
            'label_attr' => ['class' => 'form-label small'],
        ], $rowControlType));

        if ($showFieldRect) {
            $builder->add('rect', TextType::class, array_merge([
                'required' => false,
                'attr' => ['id' => 'acroform-edit-rect', 'class' => 'form-control form-control-sm', 'placeholder' => '0, 0, 100, 20'],
                'label_attr' => ['class' => 'form-label small'],
            ], $rowRect));
        }

        $builder->add('maxLen', IntegerType::class, array_merge([
            'required' => false,
            'attr' => ['id' => 'acroform-edit-max-len', 'class' => 'form-control form-control-sm', 'min' => 0, 'placeholder' => ''],
            'label_attr' => ['class' => 'form-label small'],
        ], $rowMaxLen))
            ->add('hidden', CheckboxType::class, array_merge([
                'required' => false,
                'label_attr' => ['class' => 'form-check-label small'],
                'attr' => ['id' => 'acroform-edit-hidden', 'class' => 'form-check-input'],
            ], $rowFlags))
            ->add('createIfMissing', CheckboxType::class, array_merge([
                'required' => false,
                'label_attr' => ['class' => 'form-check-label small'],
                'attr' => ['id' => 'acroform-edit-create-if-missing', 'class' => 'form-check-input'],
            ], $rowFlags))
            ->add('options', TextareaType::class, array_merge([
                'required' => false,
                'attr' => ['id' => 'acroform-edit-options', 'class' => 'form-control form-control-sm', 'rows' => 3],
                'label_attr' => ['class' => 'form-label small'],
            ], $rowOptions))
            ->add('defaultValue', TextType::class, array_merge([
                'required' => false,
                'attr' => ['id' => 'acroform-edit-default-value', 'class' => 'form-control form-control-sm'],
                'label_attr' => ['class' => 'form-label small'],
            ], $rowDefaultText))
            ->add('defaultChecked', CheckboxType::class, array_merge([
                'required' => false,
                'label_attr' => ['class' => 'form-check-label small'],
                'attr' => ['id' => 'acroform-edit-default-checked', 'class' => 'form-check-input'],
            ], $rowDefaultCheckbox))
            ->add('checkboxValueOn', TextType::class, array_merge([
                'required' => false,
                'attr' => ['id' => 'acroform-edit-checkbox-value-on', 'class' => 'form-control form-control-sm', 'placeholder' => '1'],
                'label_attr' => ['class' => 'form-label small'],
            ], $rowCheckboxOpts))
            ->add('checkboxValueOff', TextType::class, array_merge([
                'required' => false,
                'attr' => ['id' => 'acroform-edit-checkbox-value-off', 'class' => 'form-control form-control-sm', 'placeholder' => '0'],
                'label_attr' => ['class' => 'form-label small'],
            ], $rowCheckboxOpts))
            ->add('checkboxIcon', ChoiceType::class, array_merge([
                'choices' => [
                    'check' => 'check',
                    'cross' => 'cross',
                    'dot' => 'dot',
                ],
                'attr' => ['id' => 'acroform-edit-checkbox-icon', 'class' => 'form-select form-select-sm'],
                'label_attr' => ['class' => 'form-label small'],
            ], $rowCheckboxOpts));

        $fontSizeOptions = [
            'required' => false,
            'attr' => ['id' => 'acroform-edit-font-size', 'class' => 'form-control form-control-sm', 'min' => 1, 'max' => 72],
            'label_attr' => ['class' => 'form-label small mb-0'],
        ];
        if ([] !== $fontSizes) {
            $builder->add('fontSize', ChoiceType::class, array_merge($fontSizeOptions, $rowFont, [
                'choices' => array_combine($fontSizes, $fontSizes),
                'attr' => ['id' => 'acroform-edit-font-size', 'class' => 'form-select form-select-sm', 'required' => true],
            ]));
        } else {
            $builder->add('fontSize', IntegerType::class, array_merge($fontSizeOptions, $rowFont));
        }

        $fontFamilyChoices = $this->normalizeFontFamilies($fontFamilies);
        $builder->add('fontFamily', ChoiceType::class, array_merge([
            'choices' => $fontFamilyChoices,
            'attr' => ['id' => 'acroform-edit-font-family', 'class' => 'form-select form-select-sm'],
            'label_attr' => ['class' => 'form-label small mb-0'],
        ], $rowFont))
            ->add('fontAutoSize', CheckboxType::class, array_merge([
                'required' => false,
                'label_attr' => ['class' => 'form-check-label small'],
                'attr' => ['id' => 'acroform-edit-font-auto-size', 'class' => 'form-check-input'],
            ], $rowFont));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AcroFormFieldEdit::class,
            'csrf_protection' => false,
            'field_name_mode' => $this->fieldNameMode,
            'field_name_choices' => $this->fieldNameChoices,
            'field_name_other_text' => $this->fieldNameOtherText,
            'show_field_rect' => $this->showFieldRect,
            'font_sizes' => $this->fontSizes,
            'font_families' => $this->fontFamilies,
        ]);
        $resolver->setAllowedTypes('field_name_mode', 'string');
        $resolver->setAllowedTypes('field_name_choices', 'array');
        $resolver->setAllowedTypes('field_name_other_text', 'string');
        $resolver->setAllowedTypes('show_field_rect', 'bool');
        $resolver->setAllowedTypes('font_sizes', 'array');
        $resolver->setAllowedTypes('font_families', 'array');
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['acroform_edit_config'] = [
            'field_name_mode' => $options['field_name_mode'],
            'field_name_choices' => $options['field_name_choices'],
            'field_name_other_text' => $options['field_name_other_text'],
            'show_field_rect' => $options['show_field_rect'],
            'font_sizes' => $options['font_sizes'],
            'font_families' => $options['font_families'],
        ];
    }

    private function getFieldNameFieldType(string $fieldNameMode, array $fieldNameChoices): string
    {
        if ('choice' === $fieldNameMode && [] !== $fieldNameChoices) {
            return ChoiceType::class;
        }

        return TextType::class;
    }

    private function getFieldNameFieldOptions(string $fieldNameMode, array $fieldNameChoices, string $fieldNameOtherText): array
    {
        if ('choice' === $fieldNameMode && [] !== $fieldNameChoices) {
            $choices = [];
            foreach ($fieldNameChoices as $item) {
                if (\is_array($item) && isset($item['value'])) {
                    $choices[$item['label'] ?? $item['value']] = $item['value'];
                } elseif (\is_string($item)) {
                    $pipe = strpos($item, '|');
                    if (false !== $pipe) {
                        $choices[trim(substr($item, $pipe + 1))] = trim(substr($item, 0, $pipe));
                    } else {
                        $choices[$item] = $item;
                    }
                }
            }
            if ('' !== $fieldNameOtherText) {
                $choices[$fieldNameOtherText] = '__other__';
            }

            return [
                'choices' => $choices,
                'required' => true,
                'placeholder' => '—',
                'attr' => ['id' => 'acroform-edit-field-name-select', 'class' => 'form-select form-select-sm', 'autocomplete' => 'off'],
                'label' => 'acroform_editor.modal_field_name',
                'label_attr' => ['class' => 'form-label small'],
            ];
        }

        return [
            'required' => false,
            'label' => 'acroform_editor.modal_field_name',
            'attr' => ['id' => 'acroform-edit-field-name', 'class' => 'form-control form-control-sm', 'placeholder' => 'PDF field name (/T)', 'autocomplete' => 'off'],
            'label_attr' => ['class' => 'form-label small'],
        ];
    }

    /**
     * @param array<int, string|array{value: string, label?: string}> $fontFamilies
     *
     * @return array<string, string>
     */
    private function normalizeFontFamilies(array $fontFamilies): array
    {
        if ([] === $fontFamilies) {
            return [
                'sans-serif' => 'sans-serif',
                'Arial' => 'Arial',
                'Helvetica' => 'Helvetica',
                'Times New Roman' => 'Times New Roman',
                'Courier New' => 'Courier New',
                'serif' => 'serif',
                'monospace' => 'monospace',
            ];
        }
        $out = [];
        foreach ($fontFamilies as $item) {
            if (\is_array($item) && isset($item['value'])) {
                $out[$item['label'] ?? $item['value']] = $item['value'];
            } elseif (\is_string($item)) {
                $pipe = strpos($item, '|');
                if (false !== $pipe) {
                    $out[trim(substr($item, $pipe + 1))] = trim(substr($item, 0, $pipe));
                } else {
                    $out[$item] = $item;
                }
            }
        }

        return $out;
    }
}
