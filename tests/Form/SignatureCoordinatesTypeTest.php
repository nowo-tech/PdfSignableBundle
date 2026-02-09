<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\Form;

use Nowo\PdfSignableBundle\Form\SignatureBoxType;
use Nowo\PdfSignableBundle\Form\SignatureCoordinatesType;
use Nowo\PdfSignableBundle\Model\SignatureBoxModel;
use Nowo\PdfSignableBundle\Model\SignatureCoordinatesModel;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;
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
        self::assertSameWithDelta(100.0, $boxes[0]->getY(), 0.01);
        self::assertSame('a', $boxes[1]->getName());
        self::assertSame(1, $boxes[1]->getPage());
        self::assertSameWithDelta(200.0, $boxes[1]->getY(), 0.01);
        self::assertSame('b', $boxes[2]->getName());
        self::assertSame(2, $boxes[2]->getPage());
    }

    public function testPreventBoxOverlapRejectsOverlappingBoxes(): void
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
        self::assertGreaterThan(0, $form->get('signatureBoxes')->getErrors(true)->count());
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
}
