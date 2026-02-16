<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\Form;

use Nowo\PdfSignableBundle\AcroForm\AcroFormFieldEdit;
use Nowo\PdfSignableBundle\Form\AcroFormFieldEditType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\Validation;

/**
 * Tests for AcroFormFieldEditType: buildForm fields, configureOptions, buildView config.
 */
final class AcroFormFieldEditTypeTest extends TypeTestCase
{
    protected function getExtensions(): array
    {
        $type = new AcroFormFieldEditType(
            fieldNameMode: 'input',
            fieldNameChoices: [],
            fieldNameOtherText: 'Other',
            showFieldRect: true,
            fontSizes: [10, 12, 14],
            fontFamilies: ['Arial', 'Helvetica'],
        );
        $validator = Validation::createValidator();

        return [
            new PreloadedExtension([$type], []),
            new ValidatorExtension($validator),
        ];
    }

    public function testBuildFormWithFieldNameModeInput(): void
    {
        $form = $this->factory->create(AcroFormFieldEditType::class, new AcroFormFieldEdit(), [
            'field_name_mode' => 'input',
        ]);
        self::assertTrue($form->has('fieldName'));
        self::assertInstanceOf(TextType::class, $form->get('fieldName')->getConfig()->getType()->getInnerType());
        self::assertFalse($form->has('fieldNameOther'));
    }

    public function testBuildFormWithFieldNameModeChoiceAddsFieldNameOther(): void
    {
        $form = $this->factory->create(AcroFormFieldEditType::class, new AcroFormFieldEdit(), [
            'field_name_mode' => 'choice',
            'field_name_choices' => ['Option A' => 'a', 'Option B' => 'b'],
        ]);
        self::assertInstanceOf(ChoiceType::class, $form->get('fieldName')->getConfig()->getType()->getInnerType());
        self::assertTrue($form->has('fieldNameOther'));
    }

    public function testBuildFormWithFieldNameModeChoiceEmptyChoicesUsesTextType(): void
    {
        $form = $this->factory->create(AcroFormFieldEditType::class, new AcroFormFieldEdit(), [
            'field_name_mode' => 'choice',
            'field_name_choices' => [],
        ]);
        self::assertInstanceOf(TextType::class, $form->get('fieldName')->getConfig()->getType()->getInnerType());
    }

    public function testBuildFormWithShowFieldRectAddsRectField(): void
    {
        $form = $this->factory->create(AcroFormFieldEditType::class, new AcroFormFieldEdit(), [
            'show_field_rect' => true,
        ]);
        self::assertTrue($form->has('rect'));
    }

    public function testBuildFormWithShowFieldRectFalseOmitsRectField(): void
    {
        $form = $this->factory->create(AcroFormFieldEditType::class, new AcroFormFieldEdit(), [
            'show_field_rect' => false,
        ]);
        self::assertFalse($form->has('rect'));
    }

    public function testBuildFormWithFontSizesUsesChoiceType(): void
    {
        $form = $this->factory->create(AcroFormFieldEditType::class, new AcroFormFieldEdit(), [
            'font_sizes' => [10, 12, 14],
        ]);
        self::assertInstanceOf(ChoiceType::class, $form->get('fontSize')->getConfig()->getType()->getInnerType());
        $choices = $form->get('fontSize')->getConfig()->getOption('choices');
        self::assertSame([10 => 10, 12 => 12, 14 => 14], $choices);
    }

    public function testBuildFormWithEmptyFontSizesUsesIntegerType(): void
    {
        $form = $this->factory->create(AcroFormFieldEditType::class, new AcroFormFieldEdit(), [
            'font_sizes' => [],
        ]);
        self::assertInstanceOf(IntegerType::class, $form->get('fontSize')->getConfig()->getType()->getInnerType());
    }

    public function testSubmitValidData(): void
    {
        $model = new AcroFormFieldEdit();
        $form = $this->factory->create(AcroFormFieldEditType::class, $model);
        $form->submit([
            'fieldId' => 'field_1',
            'page' => '2',
            'fieldName' => 'FullName',
            'controlType' => 'text',
            'rect' => '100, 200, 300, 220',
            'maxLen' => '50',
            'hidden' => false,
            'createIfMissing' => false,
            'options' => '',
            'defaultValue' => '',
            'defaultChecked' => false,
            'checkboxValueOn' => '1',
            'checkboxValueOff' => '0',
            'checkboxIcon' => 'check',
            'fontSize' => '12',
            'fontFamily' => 'Arial',
            'fontAutoSize' => true,
        ]);

        self::assertTrue($form->isSynchronized());
        self::assertSame('field_1', $model->fieldId);
        self::assertSame(2, $model->page);
        self::assertSame('FullName', $model->fieldName);
        self::assertSame('text', $model->controlType);
        self::assertSame('100, 200, 300, 220', $model->rect);
        self::assertSame(50, $model->maxLen);
        self::assertFalse($model->hidden);
        self::assertFalse($model->createIfMissing);
        self::assertSame(12, $model->fontSize);
        self::assertSame('Arial', $model->fontFamily);
        self::assertTrue($model->fontAutoSize);
    }

    public function testBuildViewPassesAcroformEditConfig(): void
    {
        $form = $this->factory->create(AcroFormFieldEditType::class, new AcroFormFieldEdit(), [
            'field_name_mode' => 'choice',
            'field_name_choices' => ['A' => 'a'],
            'font_sizes' => [10, 12],
        ]);
        $view = $form->createView();
        self::assertArrayHasKey('acroform_edit_config', $view->vars);
        $config = $view->vars['acroform_edit_config'];
        self::assertSame('choice', $config['field_name_mode']);
        self::assertSame(['A' => 'a'], $config['field_name_choices']);
        self::assertSame([10, 12], $config['font_sizes']);
    }

    public function testBuildViewIncludesShowFieldRectAndFieldNameOtherText(): void
    {
        $form = $this->factory->create(AcroFormFieldEditType::class, new AcroFormFieldEdit(), [
            'show_field_rect' => false,
            'field_name_other_text' => 'Other value',
        ]);
        $view = $form->createView();
        $config = $view->vars['acroform_edit_config'];
        self::assertFalse($config['show_field_rect']);
        self::assertSame('Other value', $config['field_name_other_text']);
        self::assertArrayHasKey('font_families', $config);
    }

    public function testDataClassIsAcroFormFieldEdit(): void
    {
        $form = $this->factory->create(AcroFormFieldEditType::class, new AcroFormFieldEdit());
        self::assertSame(AcroFormFieldEdit::class, $form->getConfig()->getDataClass());
    }

    public function testCsrfProtectionDisabled(): void
    {
        $form = $this->factory->create(AcroFormFieldEditType::class, new AcroFormFieldEdit());
        self::assertFalse($form->getConfig()->getOption('csrf_protection'));
    }

    public function testFormHasFieldNameMaxLenHiddenCreateIfMissing(): void
    {
        $form = $this->factory->create(AcroFormFieldEditType::class, new AcroFormFieldEdit());
        self::assertTrue($form->has('fieldName'));
        self::assertTrue($form->has('maxLen'));
        self::assertTrue($form->has('hidden'));
        self::assertTrue($form->has('createIfMissing'));
    }

    public function testSubmitWithHiddenAndCreateIfMissing(): void
    {
        $model = new AcroFormFieldEdit();
        $form = $this->factory->create(AcroFormFieldEditType::class, $model);
        $form->submit([
            'fieldId' => 'new_1',
            'page' => '1',
            'fieldName' => 'NewField',
            'controlType' => 'text',
            'rect' => '0, 0, 100, 20',
            'maxLen' => '80',
            'hidden' => true,
            'createIfMissing' => true,
            'options' => '',
            'defaultValue' => '',
            'defaultChecked' => false,
            'checkboxValueOn' => '1',
            'checkboxValueOff' => '0',
            'checkboxIcon' => 'check',
            'fontSize' => '11',
            'fontFamily' => 'sans-serif',
            'fontAutoSize' => false,
        ]);
        self::assertTrue($form->isSynchronized());
        self::assertTrue($model->hidden);
        self::assertTrue($model->createIfMissing);
        self::assertSame(80, $model->maxLen);
        self::assertSame('NewField', $model->fieldName);
    }
}
