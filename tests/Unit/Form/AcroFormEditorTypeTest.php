<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\Form;

use Nowo\PdfSignableBundle\Form\AcroFormEditorType;
use Nowo\PdfSignableBundle\Model\AcroFormPageModel;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\Validation;

/**
 * Tests for AcroFormEditorType: block prefix, buildForm, configureOptions and buildView options.
 */
final class AcroFormEditorTypeTest extends TypeTestCase
{
    /**
     * @return array<int, PreloadedExtension|ValidatorExtension>
     */
    protected function getExtensions(): array
    {
        $type = new AcroFormEditorType(
            examplePdfUrl: 'https://example.com/default.pdf',
            acroformConfigs: [
                'default' => [
                    'label_mode'      => 'choice',
                    'field_name_mode' => 'choice',
                    'font_sizes'      => [10, 12, 14],
                ],
                'minimal' => [
                    'show_field_rect' => false,
                ],
            ],
            defaultConfigAlias: 'default',
            debug: false,
            labelMode: 'input',
            labelChoices: [],
            labelOtherText: 'Other',
            fieldNameMode: 'input',
            fieldNameChoices: [],
            fieldNameOtherText: 'Other',
            showFieldRect: true,
            fontSizes: [10, 12, 14, 16],
            fontFamilies: ['Arial', 'Helvetica'],
            minFieldWidth: 12.0,
            minFieldHeight: 12.0,
        );
        $validator = Validation::createValidator();

        return [
            new PreloadedExtension([$type], []),
            new ValidatorExtension($validator),
        ];
    }

    public function testGetBlockPrefix(): void
    {
        $type = new AcroFormEditorType();
        self::assertSame('acroform_editor', $type->getBlockPrefix());
    }

    public function testBuildFormAddsPdfUrlField(): void
    {
        $form = $this->factory->create(AcroFormEditorType::class, new AcroFormPageModel());
        self::assertTrue($form->has('pdfUrl'));
        self::assertInstanceOf(UrlType::class, $form->get('pdfUrl')->getConfig()->getType()->getInnerType());
    }

    public function testSubmitValidData(): void
    {
        $model = new AcroFormPageModel();
        $form  = $this->factory->create(AcroFormEditorType::class, $model, [
            'pdf_url' => 'https://example.com/doc.pdf',
        ]);
        $form->submit(['pdfUrl' => 'https://example.com/submitted.pdf']);

        self::assertTrue($form->isSynchronized());
        self::assertSame('https://example.com/submitted.pdf', $model->getPdfUrl());
    }

    public function testBuildViewPassesAcroformEditorOptions(): void
    {
        $form = $this->factory->create(AcroFormEditorType::class, new AcroFormPageModel(), [
            'pdf_url'      => 'https://custom.com/file.pdf',
            'document_key' => 'doc-123',
            'load_url'     => '/load',
            'debug'        => true,
        ]);
        $view = $form->createView();

        self::assertArrayHasKey('acroform_editor_options', $view->vars);
        $opts = $view->vars['acroform_editor_options'];
        self::assertSame('https://custom.com/file.pdf', $opts['pdf_url']);
        self::assertSame('doc-123', $opts['document_key']);
        self::assertSame('/load', $opts['load_url']);
        self::assertTrue($opts['debug']);
        self::assertTrue($opts['show_acroform']);
        self::assertTrue($opts['acroform_interactive']);
    }

    public function testBuildViewMergesNamedConfig(): void
    {
        $form = $this->factory->create(AcroFormEditorType::class, new AcroFormPageModel(), [
            'config' => 'default',
        ]);
        $view = $form->createView();
        $opts = $view->vars['acroform_editor_options'];
        self::assertSame('choice', $opts['label_mode']);
        self::assertSame([10, 12, 14], $opts['font_sizes']);
    }

    public function testBuildViewMergesConfigMinimal(): void
    {
        $form = $this->factory->create(AcroFormEditorType::class, new AcroFormPageModel(), [
            'config' => 'minimal',
        ]);
        $view = $form->createView();
        $opts = $view->vars['acroform_editor_options'];
        self::assertFalse($opts['show_field_rect']);
    }

    public function testBuildViewUsesExamplePdfUrlWhenPdfUrlOptionNull(): void
    {
        $form = $this->factory->create(AcroFormEditorType::class, new AcroFormPageModel());
        $view = $form->createView();
        self::assertSame('https://example.com/default.pdf', $view->vars['acroform_editor_options']['pdf_url']);
    }

    public function testDataClassIsAcroFormPageModel(): void
    {
        $form = $this->factory->create(AcroFormEditorType::class, new AcroFormPageModel());
        self::assertSame(AcroFormPageModel::class, $form->getConfig()->getDataClass());
    }

    /** When config is null or empty string, defaultConfigAlias is used for merging. */
    public function testBuildViewWithConfigNullUsesDefaultAlias(): void
    {
        $form = $this->factory->create(AcroFormEditorType::class, new AcroFormPageModel(), ['config' => null]);
        $view = $form->createView();
        $opts = $view->vars['acroform_editor_options'];
        self::assertSame('choice', $opts['label_mode']);
    }

    public function testBuildViewWithConfigEmptyStringUsesDefaultAlias(): void
    {
        $form = $this->factory->create(AcroFormEditorType::class, new AcroFormPageModel(), ['config' => '']);
        $view = $form->createView();
        $opts = $view->vars['acroform_editor_options'];
        self::assertSame('choice', $opts['label_mode']);
    }

    /** buildForm sets initial pdfUrl from options when provided. */
    public function testBuildFormPdfUrlOptionSetsFieldData(): void
    {
        $model = new AcroFormPageModel();
        $form  = $this->factory->create(AcroFormEditorType::class, $model, [
            'pdf_url' => 'https://example.com/preload.pdf',
        ]);
        self::assertSame('https://example.com/preload.pdf', $form->get('pdfUrl')->getData());
    }

    /** field_name_choices as associative array (label => value) is normalized to list of {value, label}. */
    public function testBuildViewNormalizesFieldNameChoicesAssociativeArray(): void
    {
        $form = $this->factory->create(AcroFormEditorType::class, new AcroFormPageModel(), [
            'field_name_choices' => ['Nombre' => 'nombre', 'Apellidos' => 'apellidos'],
        ]);
        $view = $form->createView();
        $opts = $view->vars['acroform_editor_options'];
        self::assertIsArray($opts['field_name_choices']);
        self::assertCount(2, $opts['field_name_choices']);
        self::assertSame(['value' => 'nombre', 'label' => 'Nombre'], $opts['field_name_choices'][0]);
        self::assertSame(['value' => 'apellidos', 'label' => 'Apellidos'], $opts['field_name_choices'][1]);
    }

    /** label_choices as associative array is normalized to list. */
    public function testBuildViewNormalizesLabelChoicesAssociativeArray(): void
    {
        $form = $this->factory->create(AcroFormEditorType::class, new AcroFormPageModel(), [
            'label_choices' => ['Etiqueta A' => 'a', 'Etiqueta B' => 'b'],
        ]);
        $view = $form->createView();
        $opts = $view->vars['acroform_editor_options'];
        self::assertIsArray($opts['label_choices']);
        self::assertCount(2, $opts['label_choices']);
        self::assertSame(['value' => 'a', 'label' => 'Etiqueta A'], $opts['label_choices'][0]);
    }

    /** url_field and document_key_field false are passed to view. */
    public function testBuildViewUrlFieldAndDocumentKeyFieldFalse(): void
    {
        $form = $this->factory->create(AcroFormEditorType::class, new AcroFormPageModel(), [
            'url_field'          => false,
            'document_key_field' => false,
        ]);
        $view = $form->createView();
        $opts = $view->vars['acroform_editor_options'];
        self::assertFalse($opts['url_field']);
        self::assertFalse($opts['document_key_field']);
    }

    /** field_name_choices with numeric keys (non-list) uses value as label when key is not string. */
    public function testBuildViewNormalizesFieldNameChoicesWithNumericKeys(): void
    {
        $form = $this->factory->create(AcroFormEditorType::class, new AcroFormPageModel(), [
            'field_name_choices' => [1 => 'val1', 2 => 'val2'],
        ]);
        $view = $form->createView();
        $opts = $view->vars['acroform_editor_options'];
        self::assertCount(2, $opts['field_name_choices']);
        self::assertSame(['value' => 'val1', 'label' => 'val1'], $opts['field_name_choices'][0]);
        self::assertSame(['value' => 'val2', 'label' => 'val2'], $opts['field_name_choices'][1]);
    }

    public function testBuildViewKeepsFieldNameChoicesWhenAlreadyList(): void
    {
        $choices = [
            ['value' => 'dni', 'label' => 'Documento'],
            ['value' => 'email', 'label' => 'Email'],
        ];
        $form = $this->factory->create(AcroFormEditorType::class, new AcroFormPageModel(), [
            'field_name_choices' => $choices,
        ]);
        $view = $form->createView();
        $opts = $view->vars['acroform_editor_options'];
        self::assertSame($choices, $opts['field_name_choices']);
    }
}
