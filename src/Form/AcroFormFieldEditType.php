<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Form;

use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
use Nowo\PdfSignableBundle\AcroForm\AcroFormFieldEdit;
use Nowo\PdfSignableBundle\NowoPdfSignableBundle;
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
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function array_key_exists;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;

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
 *
 * @extends AbstractType<AcroFormFieldEdit>
 */
#[FormKitConfig(NowoPdfSignableBundle::FORM_KIT_PROFILE)]
final class AcroFormFieldEditType extends AbstractType
{
    use FormKitFieldTrait;
    use FormOptionsTrait;

    /**
     * @param array<int|string, array{value: scalar|null, label?: scalar|null}|scalar> $fieldNameChoices
     * @param array<int, int> $fontSizes
     * @param array<int, array<string, mixed>|string> $fontFamilies
     */
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
        $fieldNameMode      = $options['field_name_mode'];
        $fieldNameChoices   = $options['field_name_choices'];
        $fieldNameOtherText = $options['field_name_other_text'];
        $showFieldRect      = $options['show_field_rect'];
        $fontSizes          = $options['font_sizes'];
        $fontFamilies       = $options['font_families'];

        $rowFieldName       = ['row_attr' => ['class' => 'mb-2 acroform-edit-group acroform-edit-group-field-name']];
        $rowControlType     = ['row_attr' => ['class' => 'mb-2 acroform-edit-group acroform-edit-group-control-type']];
        $rowRect            = ['row_attr' => ['class' => 'mb-2 acroform-edit-group acroform-edit-group-rect']];
        $rowMaxLen          = ['row_attr' => ['class' => 'mb-2 acroform-edit-group acroform-edit-group-max-len', 'style' => 'display:none;']];
        $rowFlags           = ['row_attr' => ['class' => 'mb-2 acroform-edit-group acroform-edit-group-flags']];
        $rowOptions         = ['row_attr' => ['class' => 'mb-2 acroform-edit-group acroform-edit-group-options', 'style' => 'display:none;']];
        $rowDefaultText     = ['row_attr' => ['class' => 'mb-2 acroform-edit-group acroform-edit-group-default-text', 'style' => 'display:none;']];
        $rowDefaultCheckbox = ['row_attr' => ['class' => 'mb-2 acroform-edit-group acroform-edit-group-default-checkbox', 'style' => 'display:none;']];
        $rowCheckboxOpts    = ['row_attr' => ['class' => 'mb-2 acroform-edit-group acroform-edit-group-checkbox-options', 'style' => 'display:none;']];
        $rowFont            = ['row_attr' => ['class' => 'mb-2 acroform-edit-group acroform-edit-group-font', 'style' => 'display:none;']];

        $this->addKitField($builder, 'fieldId', HiddenType::class, ['label' => false, 'attr' => ['id' => 'acroform-edit-field-id']]);
        $this->addKitField($builder, 'page', HiddenType::class, ['label' => false, 'attr' => ['id' => 'acroform-edit-page'], 'required' => false]);
        $this->addKitField($builder, 'fieldName', $this->getFieldNameFieldType($fieldNameMode, $fieldNameChoices), array_merge($this->getFieldNameFieldOptions($fieldNameMode, $fieldNameChoices, $fieldNameOtherText), $rowFieldName));

        if ($fieldNameMode === 'choice' && $fieldNameChoices !== [] && $fieldNameOtherText !== '') {
            $this->addKitField($builder, 'fieldNameOther', TextType::class, array_merge([
                'mapped'     => false,
                'required'   => false,
                'label'      => 'acroform_editor.modal_field_name_placeholder',
                'attr'       => ['id' => 'acroform-edit-field-name-other', 'class' => 'form-control form-control-sm', 'autocomplete' => 'off'],
                'label_attr' => ['class' => 'form-label small'],
            ], $rowFieldName));
        }

        $this->addKitField($builder, 'controlType', ChoiceType::class, array_merge([
            'label'   => 'acroform_editor.modal_control_type',
            'choices' => [
                'text'     => 'text',
                'textarea' => 'textarea',
                'checkbox' => 'checkbox',
                'select'   => 'select',
                'choice'   => 'choice',
            ],
            'choice_translation_domain' => false,
            'attr'                      => ['id' => 'acroform-edit-control-type', 'class' => 'form-select form-select-sm'],
            'label_attr'                => ['class' => 'form-label small'],
        ], $rowControlType));

        if ($showFieldRect) {
            $this->addKitField($builder, 'rect', TextType::class, array_merge([
                'required'   => false,
                'label'      => 'acroform_editor.modal_rect_label',
                'attr'       => ['id' => 'acroform-edit-rect', 'class' => 'form-control form-control-sm', 'placeholder' => 'acroform_editor.modal_rect_placeholder'],
                'label_attr' => ['class' => 'form-label small'],
            ], $rowRect));
        }

        $this->addKitField($builder, 'maxLen', IntegerType::class, array_merge([
            'required'   => false,
            'label'      => 'acroform_editor.modal_max_len',
            'attr'       => ['id' => 'acroform-edit-max-len', 'class' => 'form-control form-control-sm', 'min' => 0, 'placeholder' => 'acroform_editor.modal_max_len_placeholder'],
            'label_attr' => ['class' => 'form-label small'],
        ], $rowMaxLen));
        $this->addKitField($builder, 'hidden', CheckboxType::class, array_merge([
            'required'   => false,
            'label'      => 'acroform_editor.modal_hidden',
            'label_attr' => ['class' => 'form-check-label small'],
            'attr'       => ['id' => 'acroform-edit-hidden', 'class' => 'form-check-input'],
        ], $rowFlags));
        $this->addKitField($builder, 'createIfMissing', CheckboxType::class, array_merge([
            'required'   => false,
            'label'      => 'acroform_editor.modal_create_if_missing',
            'label_attr' => ['class' => 'form-check-label small'],
            'attr'       => ['id' => 'acroform-edit-create-if-missing', 'class' => 'form-check-input'],
        ], $rowFlags));
        $this->addKitField($builder, 'options', TextareaType::class, array_merge([
            'required'   => false,
            'label'      => 'acroform_editor.modal_options_label',
            'attr'       => ['id' => 'acroform-edit-options', 'class' => 'form-control form-control-sm', 'rows' => 3],
            'label_attr' => ['class' => 'form-label small'],
        ], $rowOptions));
        $this->addKitField($builder, 'defaultValue', TextType::class, array_merge([
            'required'   => false,
            'label'      => 'acroform_editor.modal_default_value',
            'attr'       => ['id' => 'acroform-edit-default-value', 'class' => 'form-control form-control-sm'],
            'label_attr' => ['class' => 'form-label small'],
        ], $rowDefaultText));
        $this->addKitField($builder, 'defaultChecked', CheckboxType::class, array_merge([
            'required'   => false,
            'label'      => 'acroform_editor.modal_default_checked',
            'label_attr' => ['class' => 'form-check-label small'],
            'attr'       => ['id' => 'acroform-edit-default-checked', 'class' => 'form-check-input'],
        ], $rowDefaultCheckbox));
        $this->addKitField($builder, 'checkboxValueOn', TextType::class, array_merge([
            'required'   => false,
            'label'      => 'acroform_editor.modal_checkbox_value_on',
            'attr'       => ['id' => 'acroform-edit-checkbox-value-on', 'class' => 'form-control form-control-sm', 'placeholder' => '1'],
            'label_attr' => ['class' => 'form-label small'],
        ], $rowCheckboxOpts));
        $this->addKitField($builder, 'checkboxValueOff', TextType::class, array_merge([
            'required'   => false,
            'label'      => 'acroform_editor.modal_checkbox_value_off',
            'attr'       => ['id' => 'acroform-edit-checkbox-value-off', 'class' => 'form-control form-control-sm', 'placeholder' => '0'],
            'label_attr' => ['class' => 'form-label small'],
        ], $rowCheckboxOpts));
        $this->addKitField($builder, 'checkboxIcon', ChoiceType::class, array_merge([
            'label'   => 'acroform_editor.modal_checkbox_icon',
            'choices' => [
                'acroform_editor.modal_checkbox_icon_check' => 'check',
                'acroform_editor.modal_checkbox_icon_cross' => 'cross',
                'acroform_editor.modal_checkbox_icon_dot'   => 'dot',
            ],
            'attr'       => ['id' => 'acroform-edit-checkbox-icon', 'class' => 'form-select form-select-sm'],
            'label_attr' => ['class' => 'form-label small'],
        ], $rowCheckboxOpts));

        $fontSizeOptions = [
            'required'   => false,
            'label'      => 'acroform_editor.modal_font_size',
            'attr'       => ['id' => 'acroform-edit-font-size', 'class' => 'form-control form-control-sm', 'min' => 1, 'max' => 72],
            'label_attr' => ['class' => 'form-label small mb-0'],
        ];
        if ($fontSizes !== []) {
            $this->addKitField($builder, 'fontSize', ChoiceType::class, array_merge($fontSizeOptions, $rowFont, [
                'choices'                   => array_combine($fontSizes, $fontSizes),
                'choice_translation_domain' => false,
                'attr'                      => ['id' => 'acroform-edit-font-size', 'class' => 'form-select form-select-sm', 'required' => true],
            ]));
        } else {
            $this->addKitField($builder, 'fontSize', IntegerType::class, array_merge($fontSizeOptions, $rowFont));
        }

        $fontFamilyChoices = $this->normalizeFontFamilies($fontFamilies);
        $this->addKitField($builder, 'fontFamily', ChoiceType::class, array_merge([
            'label'                     => 'acroform_editor.modal_font_family',
            'choices'                   => $fontFamilyChoices,
            'choice_translation_domain' => false,
            'attr'                      => ['id' => 'acroform-edit-font-family', 'class' => 'form-select form-select-sm'],
            'label_attr'                => ['class' => 'form-label small mb-0'],
        ], $rowFont));
        $this->addKitField($builder, 'fontAutoSize', CheckboxType::class, array_merge([
            'required'   => false,
            'label'      => 'acroform_editor.modal_font_auto_size',
            'label_attr' => ['class' => 'form-check-label small'],
            'attr'       => ['id' => 'acroform-edit-font-auto-size', 'class' => 'form-check-input'],
        ], $rowFont));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'            => AcroFormFieldEdit::class,
            'translation_domain'    => 'NowoPdfSignableBundle',
            'csrf_protection'       => false,
            'field_name_mode'       => $this->fieldNameMode,
            'field_name_choices'    => $this->fieldNameChoices,
            'field_name_other_text' => $this->fieldNameOtherText,
            'show_field_rect'       => $this->showFieldRect,
            'font_sizes'            => $this->fontSizes,
            'font_families'         => $this->fontFamilies,
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
            'field_name_mode'       => $options['field_name_mode'],
            'field_name_choices'    => $options['field_name_choices'],
            'field_name_other_text' => $options['field_name_other_text'],
            'show_field_rect'       => $options['show_field_rect'],
            'font_sizes'            => $options['font_sizes'],
            'font_families'         => $options['font_families'],
        ];
    }

    /**
     * @param array<int|string, array{value: scalar|null, label?: scalar|null}|scalar> $fieldNameChoices
     *
     * @return class-string<FormTypeInterface<mixed>>
     */
    private function getFieldNameFieldType(string $fieldNameMode, array $fieldNameChoices): string
    {
        if ($fieldNameMode === 'choice' && $fieldNameChoices !== []) {
            return ChoiceType::class;
        }

        return TextType::class;
    }

    /**
     * @param array<int|string, array{value: scalar|null, label?: scalar|null}|scalar> $fieldNameChoices
     *
     * @return array<string, mixed>
     */
    private function getFieldNameFieldOptions(string $fieldNameMode, array $fieldNameChoices, string $fieldNameOtherText): array
    {
        if ($fieldNameMode === 'choice' && $fieldNameChoices !== []) {
            $choices = [];
            if (!array_is_list($fieldNameChoices)) {
                foreach ($fieldNameChoices as $label => $value) {
                    if (!is_string($value) && !is_int($value) && !is_float($value) && !is_bool($value) && $value !== null) {
                        continue;
                    }
                    $valueString                                        = (string) $value;
                    $choices[is_string($label) ? $label : $valueString] = $valueString;
                }
            } else {
                foreach ($fieldNameChoices as $item) {
                    if (is_array($item) && array_key_exists('value', $item)) {
                        $value = $item['value'];
                        if (!is_string($value) && !is_int($value) && !is_float($value) && !is_bool($value) && $value !== null) {
                            continue;
                        }
                        $label                    = $item['label'] ?? $value;
                        $choices[(string) $label] = (string) $value;
                    } elseif (is_string($item)) {
                        $pipe = strpos($item, '|');
                        if ($pipe !== false) {
                            $choices[trim(substr($item, $pipe + 1))] = trim(substr($item, 0, $pipe));
                        } else {
                            $choices[$item] = $item;
                        }
                    }
                }
            }
            if ($fieldNameOtherText !== '') {
                $choices[$fieldNameOtherText] = '__other__';
            }

            return [
                'choices'     => $choices,
                'required'    => true,
                'placeholder' => '—',
                'attr'        => ['id' => 'acroform-edit-field-name-select', 'class' => 'form-select form-select-sm', 'autocomplete' => 'off'],
                'label'       => 'acroform_editor.modal_field_name',
                'label_attr'  => ['class' => 'form-label small'],
            ];
        }

        return [
            'required'   => false,
            'label'      => 'acroform_editor.modal_field_name',
            'attr'       => ['id' => 'acroform-edit-field-name', 'class' => 'form-control form-control-sm', 'placeholder' => 'acroform_editor.modal_field_name_placeholder', 'autocomplete' => 'off'],
            'label_attr' => ['class' => 'form-label small'],
        ];
    }

    /**
     * @param array<int, array{value: string, label?: string}|string> $fontFamilies
     *
     * @return array<string, string>
     */
    private function normalizeFontFamilies(array $fontFamilies): array
    {
        if ($fontFamilies === []) {
            return [
                'sans-serif'      => 'sans-serif',
                'Arial'           => 'Arial',
                'Helvetica'       => 'Helvetica',
                'Times New Roman' => 'Times New Roman',
                'Courier New'     => 'Courier New',
                'serif'           => 'serif',
                'monospace'       => 'monospace',
            ];
        }
        $out = [];
        foreach ($fontFamilies as $item) {
            if (is_array($item) && array_key_exists('value', $item)) {
                $value = $item['value'];
                if (!is_string($value) || $value === '') {
                    continue;
                }
                $label                = $item['label'] ?? $value;
                $out[(string) $label] = $value;
            } elseif (is_string($item)) {
                $pipe = strpos($item, '|');
                if ($pipe !== false) {
                    $out[trim(substr($item, $pipe + 1))] = trim(substr($item, 0, $pipe));
                } else {
                    $out[$item] = $item;
                }
            }
        }

        return $out;
    }
}
