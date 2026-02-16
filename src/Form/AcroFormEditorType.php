<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Form;

use Nowo\PdfSignableBundle\Model\AcroFormPageModel;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form type for AcroForm page: PDF viewer + AcroForm editor panel.
 *
 * Renders a full widget (viewer left, editor panel right). Options control
 * pdf_url, url visibility, document_key, load/post/apply/process URLs, and
 * editor config (label_mode, font_sizes, etc.). Use option "config" to apply
 * a named config from nowo_pdf_signable.acroform.configs (alias; default alias is "default").
 */
final class AcroFormEditorType extends AbstractType
{
    /**
     * @param string               $examplePdfUrl      Fallback PDF URL when pdf_url option is not set
     * @param array<string, array> $acroformConfigs    Configs by alias from nowo_pdf_signable.acroform.configs
     * @param string               $defaultConfigAlias Default alias when config option is null (e.g. "default")
     * @param bool                 $debug              When true, the frontend emits console logs
     * @param string               $labelMode          acroform.label_mode (deprecated)
     * @param array<int, string>   $labelChoices       acroform.label_choices (deprecated)
     * @param string               $labelOtherText     acroform.label_other_text (deprecated)
     * @param string               $fieldNameMode      acroform.field_name_mode default
     * @param array<int, string>   $fieldNameChoices   acroform.field_name_choices default
     * @param string               $fieldNameOtherText acroform.field_name_other_text default
     * @param bool                 $showFieldRect      acroform.show_field_rect default
     * @param array<int, int>      $fontSizes          acroform.font_sizes default
     * @param array<int, string>   $fontFamilies       acroform.font_families default
     * @param float                $minFieldWidth      acroform.min_field_width default
     * @param float                $minFieldHeight     acroform.min_field_height default
     */
    public function __construct(
        #[Autowire(param: 'nowo_pdf_signable.example_pdf_url')]
        private readonly string $examplePdfUrl = '',
        #[Autowire(param: 'nowo_pdf_signable.acroform.configs')]
        private readonly array $acroformConfigs = [],
        #[Autowire(param: 'nowo_pdf_signable.acroform.default_config_alias')]
        private readonly string $defaultConfigAlias = 'default',
        #[Autowire(param: 'nowo_pdf_signable.debug')]
        private readonly bool $debug = false,
        #[Autowire(param: 'nowo_pdf_signable.acroform.label_mode')]
        private readonly string $labelMode = 'input',
        #[Autowire(param: 'nowo_pdf_signable.acroform.label_choices')]
        private readonly array $labelChoices = [],
        #[Autowire(param: 'nowo_pdf_signable.acroform.label_other_text')]
        private readonly string $labelOtherText = '',
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
        #[Autowire(param: 'nowo_pdf_signable.acroform.min_field_width')]
        private readonly float $minFieldWidth = 12.0,
        #[Autowire(param: 'nowo_pdf_signable.acroform.min_field_height')]
        private readonly float $minFieldHeight = 12.0,
    ) {
    }

    public function getBlockPrefix(): string
    {
        return 'acroform_editor';
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('pdfUrl', UrlType::class, [
            'label' => 'page.url_label',
            'required' => false,
            'data' => $options['pdf_url'] ?? null,
            'attr' => [
                'class' => 'form-control pdf-url-input',
                'data-pdf-signable' => 'pdf-url',
                'placeholder' => 'https://example.com/document.pdf',
            ],
        ]);
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $opts = $this->resolveOptions($options);
        $view->vars['acroform_editor_options'] = $opts;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AcroFormPageModel::class,
            'config' => null,
            'pdf_url' => null,
            'url_field' => true,
            'show_load_pdf_button' => true,
            'document_key' => '',
            'load_url' => '',
            'post_url' => '',
            'apply_url' => '',
            'process_url' => '',
            'debug' => null,
            'label_mode' => null,
            'label_choices' => null,
            'label_other_text' => null,
            'field_name_mode' => null,
            'field_name_choices' => null,
            'field_name_other_text' => null,
            'show_field_rect' => null,
            'font_sizes' => null,
            'font_families' => null,
            'min_field_width' => null,
            'min_field_height' => null,
            'viewer_lazy_load' => null,
            'pdfjs_source' => null,
            'acroform_edit_form' => null,
        ]);
        $resolver->setAllowedTypes('config', ['null', 'string']);
        $resolver->setAllowedTypes('debug', ['null', 'bool']);
        $resolver->setAllowedTypes('label_mode', ['null', 'string']);
        $resolver->setAllowedTypes('label_choices', ['null', 'array']);
        $resolver->setAllowedTypes('label_other_text', ['null', 'string']);
        $resolver->setAllowedTypes('field_name_mode', ['null', 'string']);
        $resolver->setAllowedTypes('field_name_choices', ['null', 'array']);
        $resolver->setAllowedTypes('field_name_other_text', ['null', 'string']);
        $resolver->setAllowedTypes('show_field_rect', ['null', 'bool']);
        $resolver->setAllowedTypes('font_sizes', ['null', 'array']);
        $resolver->setAllowedTypes('font_families', ['null', 'array']);
        $resolver->setAllowedTypes('min_field_width', ['null', 'int', 'float']);
        $resolver->setAllowedTypes('min_field_height', ['null', 'int', 'float']);
        $resolver->setAllowedTypes('viewer_lazy_load', ['null', 'bool']);
        $resolver->setAllowedTypes('pdfjs_source', ['null', 'string']);
        $resolver->setAllowedTypes('acroform_edit_form', ['null', FormInterface::class, 'object']);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function resolveOptions(array $options): array
    {
        $defaults = [
            'pdf_url' => $options['pdf_url'] ?? ('' !== $this->examplePdfUrl ? $this->examplePdfUrl : null),
            'url_field' => $options['url_field'] ?? true,
            'show_load_pdf_button' => $options['show_load_pdf_button'] ?? true,
            'document_key' => $options['document_key'] ?? '',
            'load_url' => $options['load_url'] ?? '',
            'post_url' => $options['post_url'] ?? '',
            'apply_url' => $options['apply_url'] ?? '',
            'process_url' => $options['process_url'] ?? '',
            'debug' => $options['debug'] ?? $this->debug,
            'label_mode' => $options['label_mode'] ?? $this->labelMode,
            'label_choices' => $options['label_choices'] ?? $this->labelChoices,
            'label_other_text' => $options['label_other_text'] ?? $this->labelOtherText,
            'field_name_mode' => $options['field_name_mode'] ?? $this->fieldNameMode,
            'field_name_choices' => $options['field_name_choices'] ?? $this->fieldNameChoices,
            'field_name_other_text' => $options['field_name_other_text'] ?? $this->fieldNameOtherText,
            'show_field_rect' => $options['show_field_rect'] ?? $this->showFieldRect,
            'font_sizes' => $options['font_sizes'] ?? $this->fontSizes,
            'font_families' => $options['font_families'] ?? $this->fontFamilies,
            'min_field_width' => $options['min_field_width'] ?? $this->minFieldWidth,
            'min_field_height' => $options['min_field_height'] ?? $this->minFieldHeight,
            'acroform_edit_form' => $options['acroform_edit_form'] ?? null,
            'show_acroform' => true,
            'acroform_interactive' => true,
            'viewer_lazy_load' => $options['viewer_lazy_load'] ?? false,
            'pdfjs_source' => $options['pdfjs_source'] ?? 'npm',
        ];

        $configName = $options['config'] ?? null;
        $alias = (null !== $configName && '' !== $configName) ? $configName : $this->defaultConfigAlias;
        if ('' !== $alias && isset($this->acroformConfigs[$alias]) && \is_array($this->acroformConfigs[$alias])) {
            $defaults = array_merge($defaults, $this->acroformConfigs[$alias]);
        }

        return array_merge($defaults, array_intersect_key($options, $defaults));
    }
}
