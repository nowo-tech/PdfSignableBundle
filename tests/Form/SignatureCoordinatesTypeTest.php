<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\Form;

use Nowo\PdfSignableBundle\Form\SignatureBoxType;
use Nowo\PdfSignableBundle\Form\SignatureCoordinatesType;
use Nowo\PdfSignableBundle\Model\SignatureBoxModel;
use Nowo\PdfSignableBundle\Model\SignatureCoordinatesModel;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Validation;

/**
 * Tests for SignatureCoordinatesType: static helpers, options and form building.
 */
final class SignatureCoordinatesTypeTest extends TypeTestCase
{
    /**
     * Registers SignatureBoxType, SignatureCoordinatesType (with a named config) and the validator extension.
     *
     * @return array<int, \Symfony\Component\Form\FormExtensionInterface>
     */
    protected function getExtensions(): array
    {
        $signatureBoxType = new SignatureBoxType();
        $signatureCoordinatesType = new SignatureCoordinatesType('', [
            'my_preset' => [
                'unit_default' => SignatureCoordinatesModel::UNIT_PT,
                'origin_default' => SignatureCoordinatesModel::ORIGIN_TOP_LEFT,
            ],
            'fixed_url' => [
                'pdf_url' => 'https://example.com/fixed.pdf',
                'url_field' => false,
                'show_load_pdf_button' => false,
                'unit_field' => false,
                'origin_field' => false,
                'unit_default' => SignatureCoordinatesModel::UNIT_MM,
                'origin_default' => SignatureCoordinatesModel::ORIGIN_BOTTOM_LEFT,
            ],
        ]);
        $validator = Validation::createValidator();

        return [
            new PreloadedExtension(
                [$signatureBoxType, $signatureCoordinatesType],
                []
            ),
            new ValidatorExtension($validator),
        ];
    }

    public function testGetAllUnits(): void
    {
        $units = SignatureCoordinatesType::getAllUnits();
        self::assertContains(SignatureCoordinatesModel::UNIT_MM, $units);
        self::assertContains(SignatureCoordinatesModel::UNIT_PT, $units);
        self::assertContains(SignatureCoordinatesModel::UNIT_CM, $units);
        self::assertContains(SignatureCoordinatesModel::UNIT_PX, $units);
        self::assertContains(SignatureCoordinatesModel::UNIT_IN, $units);
        self::assertCount(5, $units);
    }

    public function testGetAllOrigins(): void
    {
        $origins = SignatureCoordinatesType::getAllOrigins();
        self::assertContains(SignatureCoordinatesModel::ORIGIN_TOP_LEFT, $origins);
        self::assertContains(SignatureCoordinatesModel::ORIGIN_BOTTOM_LEFT, $origins);
        self::assertContains(SignatureCoordinatesModel::ORIGIN_TOP_RIGHT, $origins);
        self::assertContains(SignatureCoordinatesModel::ORIGIN_BOTTOM_RIGHT, $origins);
        self::assertCount(4, $origins);
    }

    public function testFormWithNamedConfigMergesOptions(): void
    {
        $model = new SignatureCoordinatesModel();
        $model->setPdfUrl('https://example.com/doc.pdf');
        $form = $this->factory->create(SignatureCoordinatesType::class, $model, [
            'config' => 'my_preset',
        ]);
        $form->submit([
            'pdfUrl' => 'https://example.com/doc.pdf',
            'unit' => SignatureCoordinatesModel::UNIT_PT,
            'origin' => SignatureCoordinatesModel::ORIGIN_TOP_LEFT,
            'signatureBoxes' => [],
        ]);

        self::assertTrue($form->isSynchronized());
        $data = $form->getData();
        self::assertSame(SignatureCoordinatesModel::UNIT_PT, $data->getUnit());
        self::assertSame(SignatureCoordinatesModel::ORIGIN_TOP_LEFT, $data->getOrigin());
    }

    public function testFormBuildsAndSubmits(): void
    {
        $model = new SignatureCoordinatesModel();
        $model->setPdfUrl('https://example.com/doc.pdf');
        $model->setUnit(SignatureCoordinatesModel::UNIT_MM);
        $model->setOrigin(SignatureCoordinatesModel::ORIGIN_BOTTOM_LEFT);

        $box = new SignatureBoxModel();
        $box->setName('signer_1')->setPage(1)->setX(50)->setY(100)->setWidth(150)->setHeight(40);
        $model->addSignatureBox($box);

        $formData = [
            'pdfUrl' => 'https://example.com/doc.pdf',
            'unit' => SignatureCoordinatesModel::UNIT_MM,
            'origin' => SignatureCoordinatesModel::ORIGIN_BOTTOM_LEFT,
            'signatureBoxes' => [
                0 => [
                    'name' => 'signer_1',
                    'page' => 1,
                    'width' => 150.0,
                    'height' => 40.0,
                    'x' => 50.0,
                    'y' => 100.0,
                ],
            ],
        ];

        $form = $this->factory->create(SignatureCoordinatesType::class, $model);
        $form->submit($formData);

        self::assertTrue($form->isSynchronized());
        $data = $form->getData();
        self::assertInstanceOf(SignatureCoordinatesModel::class, $data);
        self::assertSame('https://example.com/doc.pdf', $data->getPdfUrl());
        self::assertSame(SignatureCoordinatesModel::UNIT_MM, $data->getUnit());
        self::assertCount(1, $data->getSignatureBoxes());
        self::assertSame('signer_1', $data->getSignatureBoxes()[0]->getName());
    }

    public function testUniqueBoxNamesFalseAddsNoUniqueConstraint(): void
    {
        $model = new SignatureCoordinatesModel();
        $model->setPdfUrl('https://example.com/doc.pdf');
        $form = $this->factory->create(SignatureCoordinatesType::class, $model, [
            'unique_box_names' => false,
            'prevent_box_overlap' => false,
        ]);
        $constraints = $form->get('signatureBoxes')->getConfig()->getOption('constraints');
        $callbacks = array_filter($constraints ?? [], static fn ($c) => $c instanceof Callback);
        self::assertCount(0, $callbacks);
    }

    public function testUniqueBoxNamesTrueAddsUniqueConstraint(): void
    {
        $model = new SignatureCoordinatesModel();
        $model->setPdfUrl('https://example.com/doc.pdf');
        $form = $this->factory->create(SignatureCoordinatesType::class, $model, [
            'unique_box_names' => true,
            'prevent_box_overlap' => false,
        ]);
        $constraints = $form->get('signatureBoxes')->getConfig()->getOption('constraints');
        $callbacks = array_filter($constraints ?? [], static fn ($c) => $c instanceof Callback);
        self::assertCount(1, $callbacks);
    }

    public function testUniqueBoxNamesArrayAddsUniqueConstraint(): void
    {
        $model = new SignatureCoordinatesModel();
        $model->setPdfUrl('https://example.com/doc.pdf');
        $form = $this->factory->create(SignatureCoordinatesType::class, $model, [
            'unique_box_names' => ['signer_1', 'witness'],
            'prevent_box_overlap' => false,
        ]);
        $constraints = $form->get('signatureBoxes')->getConfig()->getOption('constraints');
        $callbacks = array_filter($constraints ?? [], static fn ($c) => $c instanceof Callback);
        self::assertCount(1, $callbacks);
    }

    public function testAllowedPagesPassedToView(): void
    {
        $model = new SignatureCoordinatesModel();
        $model->setPdfUrl('https://example.com/doc.pdf');
        $form = $this->factory->create(SignatureCoordinatesType::class, $model, [
            'allowed_pages' => [1, 2],
        ]);
        $view = $form->createView();
        self::assertArrayHasKey('signature_coordinates_options', $view->vars);
        self::assertSame([1, 2], $view->vars['signature_coordinates_options']['allowed_pages']);
    }

    public function testPreventBoxOverlapPassedToViewAndDefaultsTrue(): void
    {
        $model = new SignatureCoordinatesModel();
        $model->setPdfUrl('https://example.com/doc.pdf');
        $form = $this->factory->create(SignatureCoordinatesType::class, $model);
        $view = $form->createView();
        self::assertTrue($view->vars['signature_coordinates_options']['prevent_box_overlap']);
        $form2 = $this->factory->create(SignatureCoordinatesType::class, $model, ['prevent_box_overlap' => false]);
        $view2 = $form2->createView();
        self::assertFalse($view2->vars['signature_coordinates_options']['prevent_box_overlap']);
    }

    public function testEnableRotationPassedToViewAndDefaultsFalse(): void
    {
        $model = new SignatureCoordinatesModel();
        $model->setPdfUrl('https://example.com/doc.pdf');
        $form = $this->factory->create(SignatureCoordinatesType::class, $model);
        $view = $form->createView();
        self::assertArrayHasKey('enable_rotation', $view->vars['signature_coordinates_options']);
        self::assertFalse($view->vars['signature_coordinates_options']['enable_rotation']);
        $form2 = $this->factory->create(SignatureCoordinatesType::class, $model, ['enable_rotation' => true]);
        $view2 = $form2->createView();
        self::assertTrue($view2->vars['signature_coordinates_options']['enable_rotation']);
    }

    public function testBoxDefaultsByNamePassedToView(): void
    {
        $model = new SignatureCoordinatesModel();
        $model->setPdfUrl('https://example.com/doc.pdf');
        $defaults = [
            'signer_1' => ['width' => 180, 'height' => 45, 'x' => 80, 'y' => 700],
            'witness' => ['width' => 120, 'height' => 35],
        ];
        $form = $this->factory->create(SignatureCoordinatesType::class, $model, [
            'box_defaults_by_name' => $defaults,
        ]);
        $view = $form->createView();
        self::assertSame($defaults, $view->vars['signature_coordinates_options']['box_defaults_by_name']);
    }

    public function testEnableSignatureCaptureAndDisclaimerPassedToView(): void
    {
        $model = new SignatureCoordinatesModel();
        $model->setPdfUrl('https://example.com/doc.pdf');
        $form = $this->factory->create(SignatureCoordinatesType::class, $model);
        $view = $form->createView();
        $opts = $view->vars['signature_coordinates_options'];
        self::assertFalse($opts['enable_signature_capture']);
        self::assertFalse($opts['enable_signature_upload']);
        self::assertNull($opts['signing_legal_disclaimer']);
        self::assertNull($opts['signing_legal_disclaimer_url']);

        $form2 = $this->factory->create(SignatureCoordinatesType::class, $model, [
            'enable_signature_capture' => true,
            'enable_signature_upload' => true,
            'signing_legal_disclaimer' => 'Simple signature – no qualified validity.',
            'signing_legal_disclaimer_url' => 'https://example.com/terms',
        ]);
        $view2 = $form2->createView();
        $opts2 = $view2->vars['signature_coordinates_options'];
        self::assertTrue($opts2['enable_signature_capture']);
        self::assertTrue($opts2['enable_signature_upload']);
        self::assertSame('Simple signature – no qualified validity.', $opts2['signing_legal_disclaimer']);
        self::assertSame('https://example.com/terms', $opts2['signing_legal_disclaimer_url']);
        self::assertFalse($opts2['signing_require_consent']);
        self::assertSame('signing.consent_label', $opts2['signing_consent_label']);
        self::assertFalse($opts2['signing_only']);

        $form3 = $this->factory->create(SignatureCoordinatesType::class, $model, [
            'signing_require_consent' => true,
            'signing_consent_label' => 'I accept.',
        ]);
        $view3 = $form3->createView();
        $opts3 = $view3->vars['signature_coordinates_options'];
        self::assertTrue($opts3['signing_require_consent']);
        self::assertSame('I accept.', $opts3['signing_consent_label']);
        self::assertTrue($form3->has('signingConsent'));
        $form3->submit([
            'pdfUrl' => 'https://example.com/doc.pdf',
            'unit' => SignatureCoordinatesModel::UNIT_MM,
            'origin' => SignatureCoordinatesModel::ORIGIN_BOTTOM_LEFT,
            'signatureBoxes' => [],
            'signingConsent' => '1',
        ]);
        self::assertTrue($form3->isValid());
        self::assertTrue($form3->getData()->getSigningConsent());

        $modelNoConsent = new SignatureCoordinatesModel();
        $form3b = $this->factory->create(SignatureCoordinatesType::class, $modelNoConsent, [
            'signing_require_consent' => true,
            'signing_consent_label' => 'I accept.',
        ]);
        $form3b->submit([
            'pdfUrl' => 'https://example.com/doc.pdf',
            'unit' => SignatureCoordinatesModel::UNIT_MM,
            'origin' => SignatureCoordinatesModel::ORIGIN_BOTTOM_LEFT,
            'signatureBoxes' => [],
            'signingConsent' => null,
        ]);
        self::assertFalse($form3b->isValid());

        $form4 = $this->factory->create(SignatureCoordinatesType::class, $model, [
            'signing_only' => true,
        ]);
        $view4 = $form4->createView();
        $opts4 = $view4->vars['signature_coordinates_options'];
        self::assertTrue($opts4['signing_only']);
    }

    public function testSortBoxesReordersOnSubmit(): void
    {
        $model = new SignatureCoordinatesModel();
        $model->setPdfUrl('https://example.com/doc.pdf');
        $model->setUnit(SignatureCoordinatesModel::UNIT_MM);
        $model->setOrigin(SignatureCoordinatesModel::ORIGIN_BOTTOM_LEFT);
        $form = $this->factory->create(SignatureCoordinatesType::class, $model, [
            'sort_boxes' => true,
        ]);
        // Submit boxes in "wrong" order: page 2 first, then page 1; on page 1, higher Y first
        $form->submit([
            'pdfUrl' => 'https://example.com/doc.pdf',
            'unit' => SignatureCoordinatesModel::UNIT_MM,
            'origin' => SignatureCoordinatesModel::ORIGIN_BOTTOM_LEFT,
            'signatureBoxes' => [
                0 => ['name' => 'b', 'page' => 2, 'width' => 100.0, 'height' => 40.0, 'x' => 10.0, 'y' => 50.0],
                1 => ['name' => 'a', 'page' => 1, 'width' => 100.0, 'height' => 40.0, 'x' => 10.0, 'y' => 200.0],
                2 => ['name' => 'c', 'page' => 1, 'width' => 100.0, 'height' => 40.0, 'x' => 10.0, 'y' => 100.0],
            ],
        ]);

        self::assertTrue($form->isSynchronized());
        $data = $form->getData();
        $boxes = $data->getSignatureBoxes();
        self::assertCount(3, $boxes);
        // Expected order: page 1 (y 100, x 10), page 1 (y 200, x 10), page 2 (y 50, x 10)
        self::assertSame('c', $boxes[0]->getName());
        self::assertSame(1, $boxes[0]->getPage());
        self::assertEqualsWithDelta(100.0, $boxes[0]->getY(), 0.01);
        self::assertSame('a', $boxes[1]->getName());
        self::assertSame(1, $boxes[1]->getPage());
        self::assertEqualsWithDelta(200.0, $boxes[1]->getY(), 0.01);
        self::assertSame('b', $boxes[2]->getName());
        self::assertSame(2, $boxes[2]->getPage());
    }

    public function testPreventBoxOverlapAddsOverlapConstraint(): void
    {
        // When prevent_box_overlap is true, the collection has a Callback constraint that rejects overlapping boxes.
        // Overlap logic is covered by testBoxesOverlapHelper; here we only assert the constraint is present.
        $form = $this->factory->create(SignatureCoordinatesType::class, new SignatureCoordinatesModel(), [
            'prevent_box_overlap' => true,
            'unique_box_names' => false,
        ]);
        $constraints = $form->get('signatureBoxes')->getConfig()->getOption('constraints');
        $callbacks = array_filter($constraints ?? [], static fn ($c) => $c instanceof Callback);
        self::assertCount(1, $callbacks, 'Exactly one Callback (overlap) should be present when prevent_box_overlap is true and unique_box_names is false');

        // Ensure the overlap helper would detect overlapping boxes with the same coordinates as in the doc
        $boxA = (new SignatureBoxModel())->setPage(1)->setWidth(100)->setHeight(40)->setX(10)->setY(100);
        $boxB = (new SignatureBoxModel())->setPage(1)->setWidth(100)->setHeight(40)->setX(50)->setY(120);
        self::assertTrue(SignatureCoordinatesType::boxesOverlap($boxA, $boxB), 'Sanity: these two boxes overlap');
    }

    public function testPreventBoxOverlapAllowsNonOverlappingBoxes(): void
    {
        $model = new SignatureCoordinatesModel();
        $model->setPdfUrl('https://example.com/doc.pdf');
        $form = $this->factory->create(SignatureCoordinatesType::class, $model, [
            'prevent_box_overlap' => true,
        ]);
        $form->submit([
            'pdfUrl' => 'https://example.com/doc.pdf',
            'unit' => SignatureCoordinatesModel::UNIT_MM,
            'origin' => SignatureCoordinatesModel::ORIGIN_BOTTOM_LEFT,
            'signatureBoxes' => [
                0 => ['name' => 'a', 'page' => 1, 'width' => 100.0, 'height' => 40.0, 'x' => 10.0, 'y' => 100.0],
                1 => ['name' => 'b', 'page' => 1, 'width' => 100.0, 'height' => 40.0, 'x' => 10.0, 'y' => 50.0],
            ],
        ]);
        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isValid());
    }

    public function testPreventBoxOverlapFalseAllowsOverlappingBoxes(): void
    {
        $model = new SignatureCoordinatesModel();
        $model->setPdfUrl('https://example.com/doc.pdf');
        $form = $this->factory->create(SignatureCoordinatesType::class, $model, [
            'prevent_box_overlap' => false,
        ]);
        $form->submit([
            'pdfUrl' => 'https://example.com/doc.pdf',
            'unit' => SignatureCoordinatesModel::UNIT_MM,
            'origin' => SignatureCoordinatesModel::ORIGIN_BOTTOM_LEFT,
            'signatureBoxes' => [
                0 => ['name' => 'a', 'page' => 1, 'width' => 100.0, 'height' => 40.0, 'x' => 10.0, 'y' => 100.0],
                1 => ['name' => 'b', 'page' => 1, 'width' => 100.0, 'height' => 40.0, 'x' => 50.0, 'y' => 120.0],
            ],
        ]);
        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isValid());
    }

    public function testBoxesOverlapHelper(): void
    {
        $a = (new SignatureBoxModel())->setPage(1)->setX(0)->setY(0)->setWidth(100)->setHeight(40);
        $b = (new SignatureBoxModel())->setPage(1)->setX(50)->setY(20)->setWidth(100)->setHeight(40);
        self::assertTrue(SignatureCoordinatesType::boxesOverlap($a, $b));
        $c = (new SignatureBoxModel())->setPage(1)->setX(0)->setY(0)->setWidth(100)->setHeight(40);
        $d = (new SignatureBoxModel())->setPage(1)->setX(100)->setY(0)->setWidth(50)->setHeight(40);
        self::assertFalse(SignatureCoordinatesType::boxesOverlap($c, $d));
        $e = (new SignatureBoxModel())->setPage(1)->setX(0)->setY(0)->setWidth(50)->setHeight(50);
        $f = (new SignatureBoxModel())->setPage(2)->setX(0)->setY(0)->setWidth(50)->setHeight(50);
        self::assertFalse(SignatureCoordinatesType::boxesOverlap($e, $f));
    }

    /**
     * Latest features combined: allowed_pages, sort_boxes, prevent_box_overlap (as in demo "latest-features").
     * Asserts view receives all options and valid submit yields sorted, non-overlapping boxes.
     */
    public function testLatestFeaturesOptionsCombined(): void
    {
        $model = new SignatureCoordinatesModel();
        $model->setPdfUrl('https://example.com/doc.pdf');
        $form = $this->factory->create(SignatureCoordinatesType::class, $model, [
            'allowed_pages' => [1],
            'sort_boxes' => true,
            'prevent_box_overlap' => true,
            'max_entries' => 5,
        ]);
        $view = $form->createView();
        $opts = $view->vars['signature_coordinates_options'];
        self::assertSame([1], $opts['allowed_pages']);
        self::assertTrue($opts['prevent_box_overlap']);

        // Submit two non-overlapping boxes on page 1 in "wrong" order; expect sorted by Y then X
        $form->submit([
            'pdfUrl' => 'https://example.com/doc.pdf',
            'unit' => SignatureCoordinatesModel::UNIT_MM,
            'origin' => SignatureCoordinatesModel::ORIGIN_BOTTOM_LEFT,
            'signatureBoxes' => [
                0 => ['name' => 'b', 'page' => 1, 'width' => 100.0, 'height' => 40.0, 'x' => 50.0, 'y' => 200.0],
                1 => ['name' => 'a', 'page' => 1, 'width' => 100.0, 'height' => 40.0, 'x' => 10.0, 'y' => 100.0],
            ],
        ]);
        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isValid());
        $data = $form->getData();
        $boxes = $data->getSignatureBoxes();
        self::assertCount(2, $boxes);
        // Sorted by page then Y then X: a (y=100) then b (y=200)
        self::assertSame('a', $boxes[0]->getName());
        self::assertEqualsWithDelta(100.0, $boxes[0]->getY(), 0.01);
        self::assertSame('b', $boxes[1]->getName());
        self::assertEqualsWithDelta(200.0, $boxes[1]->getY(), 0.01);
    }

    /** When pdf_url is set and url_field is false, pdfUrl is a hidden field. */
    public function testUrlFieldFalseWithPdfUrlUsesHiddenField(): void
    {
        $model = new SignatureCoordinatesModel();
        $form = $this->factory->create(SignatureCoordinatesType::class, $model, [
            'url_field' => false,
            'pdf_url' => 'https://example.com/fixed.pdf',
        ]);
        $pdfUrlField = $form->get('pdfUrl');
        self::assertInstanceOf(HiddenType::class, $pdfUrlField->getConfig()->getType()->getInnerType());
        self::assertSame('https://example.com/fixed.pdf', $pdfUrlField->getConfig()->getData());
    }

    /** show_load_pdf_button is passed to view; default true, can be false (e.g. fixed-URL config). */
    public function testShowLoadPdfButtonPassedToView(): void
    {
        $model = new SignatureCoordinatesModel();
        $form = $this->factory->create(SignatureCoordinatesType::class, $model);
        $view = $form->createView();
        $opts = $view->vars['signature_coordinates_options'];
        self::assertArrayHasKey('show_load_pdf_button', $opts);
        self::assertTrue($opts['show_load_pdf_button']);

        $form2 = $this->factory->create(SignatureCoordinatesType::class, $model, ['show_load_pdf_button' => false]);
        $view2 = $form2->createView();
        self::assertFalse($view2->vars['signature_coordinates_options']['show_load_pdf_button']);
    }

    /** When url_mode is choice and url_choices non-empty, pdfUrl is ChoiceType. */
    public function testUrlModeChoiceRendersPdfUrlAsChoiceType(): void
    {
        $model = new SignatureCoordinatesModel();
        $form = $this->factory->create(SignatureCoordinatesType::class, $model, [
            'url_mode' => SignatureCoordinatesType::URL_MODE_CHOICE,
            'url_choices' => ['Document A' => 'https://a.com/a.pdf', 'Document B' => 'https://b.com/b.pdf'],
        ]);
        self::assertInstanceOf(ChoiceType::class, $form->get('pdfUrl')->getConfig()->getType()->getInnerType());
    }

    /** When unit_field is false, unit is HiddenType with unit_default. */
    public function testUnitFieldFalseRendersUnitAsHidden(): void
    {
        $model = new SignatureCoordinatesModel();
        $form = $this->factory->create(SignatureCoordinatesType::class, $model, [
            'unit_field' => false,
            'unit_default' => SignatureCoordinatesModel::UNIT_PT,
        ]);
        $unitField = $form->get('unit');
        self::assertInstanceOf(HiddenType::class, $unitField->getConfig()->getType()->getInnerType());
        self::assertSame(SignatureCoordinatesModel::UNIT_PT, $unitField->getConfig()->getData());
    }

    /** When origin_field is false, origin is HiddenType with origin_default. */
    public function testOriginFieldFalseRendersOriginAsHidden(): void
    {
        $model = new SignatureCoordinatesModel();
        $form = $this->factory->create(SignatureCoordinatesType::class, $model, [
            'origin_field' => false,
            'origin_default' => SignatureCoordinatesModel::ORIGIN_TOP_LEFT,
        ]);
        $originField = $form->get('origin');
        self::assertInstanceOf(HiddenType::class, $originField->getConfig()->getType()->getInnerType());
        self::assertSame(SignatureCoordinatesModel::ORIGIN_TOP_LEFT, $originField->getConfig()->getData());
    }

    /** When unit_mode is input, unit field is TextType. */
    public function testUnitModeInputRendersUnitAsTextType(): void
    {
        $model = new SignatureCoordinatesModel();
        $form = $this->factory->create(SignatureCoordinatesType::class, $model, [
            'unit_mode' => SignatureCoordinatesType::UNIT_MODE_INPUT,
            'units' => [SignatureCoordinatesModel::UNIT_MM, SignatureCoordinatesModel::UNIT_PT],
        ]);
        self::assertInstanceOf(TextType::class, $form->get('unit')->getConfig()->getType()->getInnerType());
    }

    /** When origin_mode is input, origin field is TextType. */
    public function testOriginModeInputRendersOriginAsTextType(): void
    {
        $model = new SignatureCoordinatesModel();
        $form = $this->factory->create(SignatureCoordinatesType::class, $model, [
            'origin_mode' => SignatureCoordinatesType::ORIGIN_MODE_INPUT,
            'origins' => [SignatureCoordinatesModel::ORIGIN_TOP_LEFT, SignatureCoordinatesModel::ORIGIN_BOTTOM_LEFT],
        ]);
        self::assertInstanceOf(TextType::class, $form->get('origin')->getConfig()->getType()->getInnerType());
    }

    /** pdf_url option pre-populates model when model pdfUrl is empty (PRE_SET_DATA). */
    public function testPdfUrlOptionPrePopulatesModelWhenEmpty(): void
    {
        $model = new SignatureCoordinatesModel();
        self::assertNull($model->getPdfUrl());
        $this->factory->create(SignatureCoordinatesType::class, $model, [
            'pdf_url' => 'https://example.com/preload.pdf',
        ]);
        self::assertSame('https://example.com/preload.pdf', $model->getPdfUrl());
    }

    /** When signing_require_consent is true, signingConsent checkbox is added and required. */
    public function testSigningRequireConsentAddsCheckboxAndSubmits(): void
    {
        $model = new SignatureCoordinatesModel();
        $model->setPdfUrl('https://example.com/doc.pdf');
        $form = $this->factory->create(SignatureCoordinatesType::class, $model, [
            'signing_require_consent' => true,
            'signing_consent_label' => 'I agree to sign',
        ]);
        self::assertTrue($form->has('signingConsent'));
        $form->submit([
            'pdfUrl' => 'https://example.com/doc.pdf',
            'unit' => SignatureCoordinatesModel::UNIT_MM,
            'origin' => SignatureCoordinatesModel::ORIGIN_BOTTOM_LEFT,
            'signatureBoxes' => [],
            'signingConsent' => '1',
        ]);
        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isValid());
        self::assertTrue($form->getData()->getSigningConsent());
    }

    /** Signing consent is required when signing_require_consent is true; unchecked checkbox fails validation. */
    public function testSigningConsentRequiredFailsWhenUnchecked(): void
    {
        $model = new SignatureCoordinatesModel();
        $model->setPdfUrl('https://example.com/doc.pdf');
        $form = $this->factory->create(SignatureCoordinatesType::class, $model, [
            'signing_require_consent' => true,
        ]);
        // Checkbox not checked: omit key or send empty; form normalizes to false, IsTrue constraint fails
        $form->submit([
            'pdfUrl' => 'https://example.com/doc.pdf',
            'unit' => SignatureCoordinatesModel::UNIT_MM,
            'origin' => SignatureCoordinatesModel::ORIGIN_BOTTOM_LEFT,
            'signatureBoxes' => [],
        ]);
        self::assertTrue($form->isSynchronized());
        self::assertFalse($form->isValid());
        self::assertCount(1, $form->get('signingConsent')->getErrors());
    }

    /** unique_box_names can be an array of allowed duplicate names; form builds successfully. */
    public function testUniqueBoxNamesArrayAccepted(): void
    {
        $model = new SignatureCoordinatesModel();
        $model->setPdfUrl('https://example.com/doc.pdf');
        $form = $this->factory->create(SignatureCoordinatesType::class, $model, [
            'unique_box_names' => ['signer_1', 'witness'],
        ]);
        self::assertTrue($form->has('signatureBoxes'));
    }

    /** allowed_pages must contain only positive integers; invalid values trigger OptionsResolver. */
    public function testAllowedPagesInvalidValueThrows(): void
    {
        $this->expectException(InvalidOptionsException::class);
        $this->factory->create(SignatureCoordinatesType::class, new SignatureCoordinatesModel(), [
            'allowed_pages' => [1, 0],
        ]);
    }

    /** When named config has url_field/unit_field/origin_field false, they override resolver defaults (merge order). */
    public function testNamedConfigWithHiddenFieldsOverridesDefaults(): void
    {
        $model = new SignatureCoordinatesModel();
        $form = $this->factory->create(SignatureCoordinatesType::class, $model, [
            'config' => 'fixed_url',
        ]);
        self::assertInstanceOf(HiddenType::class, $form->get('pdfUrl')->getConfig()->getType()->getInnerType());
        self::assertInstanceOf(HiddenType::class, $form->get('unit')->getConfig()->getType()->getInnerType());
        self::assertInstanceOf(HiddenType::class, $form->get('origin')->getConfig()->getType()->getInnerType());
        $view = $form->createView();
        $opts = $view->vars['signature_coordinates_options'];
        self::assertFalse($opts['url_field'], 'Named config url_field: false must override default');
        self::assertFalse($opts['show_load_pdf_button'], 'Named config show_load_pdf_button: false must override default');
        self::assertFalse($opts['unit_field'], 'Named config unit_field: false must override default');
        self::assertFalse($opts['origin_field'], 'Named config origin_field: false must override default');
    }

    /** When config name is not in namedConfigs, mergeNamedConfig returns options unchanged (no merge). */
    public function testConfigNonexistentUsesOptionsWithoutMerge(): void
    {
        $model = new SignatureCoordinatesModel();
        $model->setPdfUrl('https://example.com/doc.pdf');
        $form = $this->factory->create(SignatureCoordinatesType::class, $model, [
            'config' => 'nonexistent_preset',
            'unit_default' => SignatureCoordinatesModel::UNIT_CM,
        ]);
        $view = $form->createView();
        $opts = $view->vars['signature_coordinates_options'];
        self::assertSame(SignatureCoordinatesModel::UNIT_CM, $opts['unit_default'], 'Options should be used as-is when config does not exist');
    }

    /** box_constraints are merged into signature box entry options. */
    public function testBoxConstraintsMergedIntoEntryOptions(): void
    {
        $model = new SignatureCoordinatesModel();
        $model->setPdfUrl('https://example.com/doc.pdf');
        $form = $this->factory->create(SignatureCoordinatesType::class, $model, [
            'box_constraints' => [new \Symfony\Component\Validator\Constraints\NotNull()],
        ]);
        $form->submit([
            'pdfUrl' => 'https://example.com/doc.pdf',
            'unit' => SignatureCoordinatesModel::UNIT_MM,
            'origin' => SignatureCoordinatesModel::ORIGIN_BOTTOM_LEFT,
            'signatureBoxes' => [
                0 => ['name' => 'a', 'page' => 1, 'width' => 100.0, 'height' => 40.0, 'x' => 10.0, 'y' => 100.0],
            ],
        ]);
        self::assertTrue($form->isSynchronized());
    }

    /** buildUnitChoices uses fallback when unit is not in labels map (e.g. custom unit key). */
    public function testUnitsWithUnmappedUnitUsesFallbackInChoices(): void
    {
        $model = new SignatureCoordinatesModel();
        $form = $this->factory->create(SignatureCoordinatesType::class, $model, [
            'units' => [SignatureCoordinatesModel::UNIT_MM, 'custom_unit'],
        ]);
        $unitField = $form->get('unit');
        $choices = $unitField->getConfig()->getOption('choices');
        self::assertArrayHasKey('custom_unit', $choices);
        self::assertSame('custom_unit', $choices['custom_unit']);
    }

    /** unique_box_names true: submitting two boxes with the same name yields a violation on the second. */
    public function testUniqueBoxNamesTrueViolationWhenDuplicateNames(): void
    {
        $model = new SignatureCoordinatesModel();
        $model->setPdfUrl('https://example.com/doc.pdf');
        $form = $this->factory->create(SignatureCoordinatesType::class, $model, [
            'unique_box_names' => true,
            'prevent_box_overlap' => false,
        ]);
        $form->submit([
            'pdfUrl' => 'https://example.com/doc.pdf',
            'unit' => SignatureCoordinatesModel::UNIT_MM,
            'origin' => SignatureCoordinatesModel::ORIGIN_BOTTOM_LEFT,
            'signatureBoxes' => [
                0 => ['name' => 'signer', 'page' => 1, 'width' => 100.0, 'height' => 40.0, 'x' => 10.0, 'y' => 100.0],
                1 => ['name' => 'signer', 'page' => 1, 'width' => 100.0, 'height' => 40.0, 'x' => 10.0, 'y' => 200.0],
            ],
        ]);
        self::assertTrue($form->isSynchronized());
        self::assertFalse($form->isValid());
        self::assertCount(1, $form->get('signatureBoxes')->get('1')->get('name')->getErrors());
    }

    /** unique_box_names as array: two boxes with a name not in the list do not trigger unique-name violation. */
    public function testUniqueBoxNamesArrayNoViolationWhenNameNotInList(): void
    {
        $model = new SignatureCoordinatesModel();
        $model->setPdfUrl('https://example.com/doc.pdf');
        $form = $this->factory->create(SignatureCoordinatesType::class, $model, [
            'unique_box_names' => ['signer_1', 'witness'],
            'prevent_box_overlap' => false,
        ]);
        $form->submit([
            'pdfUrl' => 'https://example.com/doc.pdf',
            'unit' => SignatureCoordinatesModel::UNIT_MM,
            'origin' => SignatureCoordinatesModel::ORIGIN_BOTTOM_LEFT,
            'signatureBoxes' => [
                0 => ['name' => 'other', 'page' => 1, 'width' => 100.0, 'height' => 40.0, 'x' => 10.0, 'y' => 100.0],
                1 => ['name' => 'other', 'page' => 1, 'width' => 100.0, 'height' => 40.0, 'x' => 10.0, 'y' => 200.0],
            ],
        ]);
        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isValid());
    }

    /** prevent_box_overlap true: submitting two overlapping boxes on the same page yields a violation. */
    public function testPreventBoxOverlapViolationWhenOverlapping(): void
    {
        $model = new SignatureCoordinatesModel();
        $model->setPdfUrl('https://example.com/doc.pdf');
        $form = $this->factory->create(SignatureCoordinatesType::class, $model, [
            'prevent_box_overlap' => true,
        ]);
        $form->submit([
            'pdfUrl' => 'https://example.com/doc.pdf',
            'unit' => SignatureCoordinatesModel::UNIT_MM,
            'origin' => SignatureCoordinatesModel::ORIGIN_BOTTOM_LEFT,
            'signatureBoxes' => [
                0 => ['name' => 'a', 'page' => 1, 'width' => 100.0, 'height' => 40.0, 'x' => 10.0, 'y' => 100.0],
                1 => ['name' => 'b', 'page' => 1, 'width' => 100.0, 'height' => 40.0, 'x' => 50.0, 'y' => 120.0],
            ],
        ]);
        self::assertTrue($form->isSynchronized());
        self::assertFalse($form->isValid());
        // Error may be on signatureBoxes or a child ([0]/[1]); count all errors under root
        self::assertGreaterThanOrEqual(1, iterator_count($form->getErrors(true)));
    }

    /** sort_boxes true: PRE_SUBMIT listener reorders boxes by page, then y, then x. */
    public function testSortBoxesReordersByPageAndPosition(): void
    {
        $model = new SignatureCoordinatesModel();
        $model->setPdfUrl('https://example.com/doc.pdf');
        $form = $this->factory->create(SignatureCoordinatesType::class, $model, [
            'sort_boxes' => true,
        ]);
        $form->submit([
            'pdfUrl' => 'https://example.com/doc.pdf',
            'unit' => SignatureCoordinatesModel::UNIT_MM,
            'origin' => SignatureCoordinatesModel::ORIGIN_BOTTOM_LEFT,
            'signatureBoxes' => [
                0 => ['name' => 'page2', 'page' => 2, 'width' => 100.0, 'height' => 40.0, 'x' => 10.0, 'y' => 50.0],
                1 => ['name' => 'page1', 'page' => 1, 'width' => 100.0, 'height' => 40.0, 'x' => 10.0, 'y' => 100.0],
            ],
        ]);
        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isValid());
        $boxes = $form->getData()->getSignatureBoxes();
        self::assertCount(2, $boxes);
        self::assertSame(1, $boxes[0]->getPage());
        self::assertSame('page1', $boxes[0]->getName());
        self::assertSame(2, $boxes[1]->getPage());
        self::assertSame('page2', $boxes[1]->getName());
    }

    /** buildView uses type examplePdfUrl when pdf_url option is empty. */
    public function testBuildViewUsesExamplePdfUrlWhenOptionEmpty(): void
    {
        $exampleUrl = 'https://example.com/fallback.pdf';
        $typeWithExample = new SignatureCoordinatesType($exampleUrl, []);
        $factory = (new \Symfony\Component\Form\FormFactoryBuilder())
            ->addExtension(new PreloadedExtension([new SignatureBoxType(), $typeWithExample], []))
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->getFormFactory();
        $form = $factory->create(SignatureCoordinatesType::class, new SignatureCoordinatesModel(), []);
        $view = $form->createView();
        self::assertSame($exampleUrl, $view->vars['signature_coordinates_options']['pdf_url']);
    }

    /** boxFromArray (private) builds a box from an array; missing required keys return null. */
    public function testBoxFromArrayViaReflection(): void
    {
        $ref = new \ReflectionMethod(SignatureCoordinatesType::class, 'boxFromArray');
        $ref->setAccessible(true);

        self::assertNull($ref->invoke(null, []));
        self::assertNull($ref->invoke(null, ['page' => 1]));

        $box = $ref->invoke(null, [
            'page' => 2,
            'x' => 10.5,
            'y' => 20.5,
            'width' => 100.0,
            'height' => 40.0,
            'name' => 'test',
            'angle' => -90.0,
        ]);
        self::assertInstanceOf(SignatureBoxModel::class, $box);
        self::assertSame(2, $box->getPage());
        self::assertEqualsWithDelta(10.5, $box->getX(), 0.01);
        self::assertEqualsWithDelta(20.5, $box->getY(), 0.01);
        self::assertEqualsWithDelta(100.0, $box->getWidth(), 0.01);
        self::assertEqualsWithDelta(40.0, $box->getHeight(), 0.01);
        self::assertSame('test', $box->getName());
        self::assertEqualsWithDelta(-90.0, $box->getAngle(), 0.01);

        $boxNoAngle = $ref->invoke(null, [
            'page' => 1,
            'x' => 0.0,
            'y' => 0.0,
            'width' => 50.0,
            'height' => 20.0,
        ]);
        self::assertInstanceOf(SignatureBoxModel::class, $boxNoAngle);
        self::assertSame(0.0, $boxNoAngle->getAngle());
    }
}
