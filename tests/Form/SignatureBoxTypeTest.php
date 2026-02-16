<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\Form;

use Nowo\PdfSignableBundle\Form\SignatureBoxType;
use Nowo\PdfSignableBundle\Model\SignatureBoxModel;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Validation;

/**
 * Tests for SignatureBoxType form type: options, submit and data mapping.
 */
final class SignatureBoxTypeTest extends TypeTestCase
{
    /**
     * Registers SignatureBoxType and the validator extension for form tests.
     *
     * @return array<int, \Symfony\Component\Form\FormExtensionInterface>
     */
    protected function getExtensions(): array
    {
        $type = new SignatureBoxType();
        $validator = Validation::createValidator();

        return [
            new PreloadedExtension([$type], []),
            new ValidatorExtension($validator),
        ];
    }

    public function testSubmitValidData(): void
    {
        $model = new SignatureBoxModel();
        $formData = [
            'name' => 'signer_1',
            'page' => 1,
            'width' => 150.0,
            'height' => 40.0,
            'x' => 50.5,
            'y' => 100.25,
        ];

        $form = $this->factory->create(SignatureBoxType::class, $model);
        $form->submit($formData);

        self::assertTrue($form->isSynchronized());
        self::assertSame('signer_1', $model->getName());
        self::assertSame(1, $model->getPage());
        self::assertSame(150.0, $model->getWidth());
        self::assertSame(40.0, $model->getHeight());
        self::assertSame(50.5, $model->getX());
        self::assertSame(100.25, $model->getY());
    }

    public function testConfigureOptions(): void
    {
        $form = $this->factory->create(SignatureBoxType::class, new SignatureBoxModel());
        self::assertInstanceOf(SignatureBoxType::class, $form->getConfig()->getType()->getInnerType());
    }

    public function testGetBlockPrefix(): void
    {
        $type = new SignatureBoxType();
        self::assertSame('signature_box', $type->getBlockPrefix());
    }

    /** PRE_SET_DATA listener pre-fills name with first choice when model name is empty. */
    public function testPreSetDataSelectsFirstChoiceWhenNameEmpty(): void
    {
        $model = new SignatureBoxModel();
        $this->factory->create(SignatureBoxType::class, $model, [
            'name_mode' => SignatureBoxType::NAME_MODE_CHOICE,
            'name_choices' => ['First' => 'first_val', 'Second' => 'second_val'],
        ]);
        self::assertSame('first_val', $model->getName());
    }

    public function testSubmitWithNameModeChoice(): void
    {
        $model = new SignatureBoxModel();
        $form = $this->factory->create(SignatureBoxType::class, $model, [
            'name_mode' => SignatureBoxType::NAME_MODE_CHOICE,
            'name_choices' => ['Signer 1' => 'signer_1', 'Witness' => 'witness'],
        ]);
        $form->submit([
            'name' => 'witness',
            'page' => 2,
            'width' => 100.0,
            'height' => 30.0,
            'x' => 10.0,
            'y' => 20.0,
        ]);

        self::assertTrue($form->isSynchronized());
        self::assertSame('witness', $model->getName());
        self::assertSame(2, $model->getPage());
    }

    public function testNameFieldHasNotBlankConstraint(): void
    {
        $form = $this->factory->create(SignatureBoxType::class, new SignatureBoxModel());
        $constraints = $form->get('name')->getConfig()->getOption('constraints');
        $notBlanks = array_filter($constraints ?? [], static fn ($c) => $c instanceof NotBlank);
        self::assertCount(1, $notBlanks);
    }

    public function testAllowedPagesRendersPageAsChoice(): void
    {
        $model = new SignatureBoxModel();
        $form = $this->factory->create(SignatureBoxType::class, $model, [
            'allowed_pages' => [1, 2, 3],
        ]);
        $pageField = $form->get('page');
        self::assertInstanceOf(ChoiceType::class, $pageField->getConfig()->getType()->getInnerType());
        $choices = $pageField->getConfig()->getOption('choices');
        self::assertSame([1 => 1, 2 => 2, 3 => 3], $choices);
    }

    public function testSubmitWithAllowedPages(): void
    {
        $model = new SignatureBoxModel();
        $form = $this->factory->create(SignatureBoxType::class, $model, [
            'allowed_pages' => [1, 2],
        ]);
        $form->submit([
            'name' => 'signer_1',
            'page' => '2',
            'width' => 120.0,
            'height' => 35.0,
            'x' => 10.0,
            'y' => 20.0,
        ]);

        self::assertTrue($form->isSynchronized());
        self::assertSame(2, $model->getPage());
    }

    public function testAllowedPagesFieldHasChoiceConstraint(): void
    {
        $form = $this->factory->create(SignatureBoxType::class, new SignatureBoxModel(), [
            'allowed_pages' => [1, 2],
        ]);
        $constraints = $form->get('page')->getConfig()->getOption('constraints');
        $choices = array_filter($constraints ?? [], static fn ($c) => $c instanceof Choice);
        self::assertCount(1, $choices);
    }

    public function testAngleEnabledFalseOmitsAngleField(): void
    {
        $form = $this->factory->create(SignatureBoxType::class, new SignatureBoxModel());
        self::assertFalse($form->has('angle'));
    }

    public function testAngleEnabledTrueAddsAngleFieldAndSubmits(): void
    {
        $model = new SignatureBoxModel();
        $form = $this->factory->create(SignatureBoxType::class, $model, [
            'angle_enabled' => true,
        ]);
        self::assertTrue($form->has('angle'));
        $form->submit([
            'name' => 'signer_1',
            'page' => 1,
            'width' => 150.0,
            'height' => 40.0,
            'x' => 50.0,
            'y' => 100.0,
            'angle' => -15.5,
        ]);
        self::assertTrue($form->isSynchronized());
        self::assertSame(-15.5, $model->getAngle());
    }

    public function testEnableSignatureCaptureAddsSignatureDataField(): void
    {
        $form = $this->factory->create(SignatureBoxType::class, new SignatureBoxModel(), [
            'enable_signature_capture' => true,
        ]);
        self::assertTrue($form->has('signatureData'));
    }

    public function testEnableSignatureUploadAddsSignatureDataField(): void
    {
        $form = $this->factory->create(SignatureBoxType::class, new SignatureBoxModel(), [
            'enable_signature_upload' => true,
        ]);
        self::assertTrue($form->has('signatureData'));
    }

    public function testSubmitWithSignatureData(): void
    {
        $model = new SignatureBoxModel();
        $form = $this->factory->create(SignatureBoxType::class, $model, [
            'enable_signature_capture' => true,
        ]);
        $dataUrl = 'data:image/png;base64,iVBORw0KGgo=';
        $form->submit([
            'name' => 'signer_1',
            'page' => 1,
            'width' => 150.0,
            'height' => 40.0,
            'x' => 50.0,
            'y' => 100.0,
            'signatureData' => $dataUrl,
        ]);
        self::assertTrue($form->isSynchronized());
        self::assertSame($dataUrl, $model->getSignatureData());
    }

    /** allowed_pages must contain only positive integers; invalid values trigger OptionsResolver. */
    public function testAllowedPagesInvalidValueThrows(): void
    {
        $this->expectException(InvalidOptionsException::class);
        $this->factory->create(SignatureBoxType::class, new SignatureBoxModel(), [
            'allowed_pages' => [0],
        ]);
    }

    /** allowed_pages must be array or null; non-array triggers OptionsResolver. */
    public function testAllowedPagesNonArrayThrows(): void
    {
        $this->expectException(InvalidOptionsException::class);
        $this->factory->create(SignatureBoxType::class, new SignatureBoxModel(), [
            'allowed_pages' => 'not-an-array',
        ]);
    }

    /** When name_mode is choice but name_choices is empty, name field is TextType (same as input mode). */
    public function testNameModeChoiceWithEmptyChoicesUsesTextType(): void
    {
        $form = $this->factory->create(SignatureBoxType::class, new SignatureBoxModel(), [
            'name_mode' => SignatureBoxType::NAME_MODE_CHOICE,
            'name_choices' => [],
        ]);
        self::assertInstanceOf(TextType::class, $form->get('name')->getConfig()->getType()->getInnerType());
    }

    public function testMinBoxWidthHeightPassedToViewAndFieldAttr(): void
    {
        $form = $this->factory->create(SignatureBoxType::class, new SignatureBoxModel());
        $view = $form->createView();
        self::assertNull($view->vars['min_box_width']);
        self::assertNull($view->vars['min_box_height']);
        self::assertSame(10, $form->get('width')->getConfig()->getOption('attr')['min']);
        self::assertSame(10, $form->get('height')->getConfig()->getOption('attr')['min']);

        $form2 = $this->factory->create(SignatureBoxType::class, new SignatureBoxModel(), [
            'min_box_width' => 30.0,
            'min_box_height' => 20.0,
        ]);
        $view2 = $form2->createView();
        self::assertSame(30.0, $view2->vars['min_box_width']);
        self::assertSame(20.0, $view2->vars['min_box_height']);
        self::assertSame(30.0, $form2->get('width')->getConfig()->getOption('attr')['min']);
        self::assertSame(20.0, $form2->get('height')->getConfig()->getOption('attr')['min']);
    }

    /** buildView passes signing_only, hide_coordinate_fields, hide_position_fields to the widget. */
    public function testBuildViewPassesWidgetOptions(): void
    {
        $form = $this->factory->create(SignatureBoxType::class, new SignatureBoxModel(), [
            'signing_only' => true,
            'hide_coordinate_fields' => true,
            'hide_position_fields' => true,
            'lock_box_width' => true,
            'lock_box_height' => true,
            'default_box_width' => 100.0,
            'default_box_height' => 30.0,
        ]);
        $view = $form->createView();

        self::assertTrue($view->vars['signing_only']);
        self::assertTrue($view->vars['hide_coordinate_fields']);
        self::assertTrue($view->vars['hide_position_fields']);
        self::assertTrue($view->vars['lock_box_width']);
        self::assertTrue($view->vars['lock_box_height']);
        self::assertSame(100.0, $view->vars['default_box_width']);
        self::assertSame(30.0, $view->vars['default_box_height']);
    }

    /** PRE_SET_DATA applies lock_box_width/height with default values. */
    public function testPreSetDataLockBoxWidthHeight(): void
    {
        $model = new SignatureBoxModel();
        $model->setWidth(200.0)->setHeight(50.0);

        $this->factory->create(SignatureBoxType::class, $model, [
            'lock_box_width' => true,
            'lock_box_height' => true,
            'default_box_width' => 120.0,
            'default_box_height' => 25.0,
        ]);

        self::assertSame(120.0, $model->getWidth());
        self::assertSame(25.0, $model->getHeight());
    }
}
