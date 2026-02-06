<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\Form;

use Nowo\PdfSignableBundle\Form\SignatureBoxType;
use Nowo\PdfSignableBundle\Form\SignatureCoordinatesType;
use Nowo\PdfSignableBundle\Model\SignatureBoxModel;
use Nowo\PdfSignableBundle\Model\SignatureCoordinatesModel;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;

/**
 * Tests for SignatureCoordinatesType: static helpers, options and form building.
 */
final class SignatureCoordinatesTypeTest extends TypeTestCase
{
    protected function getExtensions(): array
    {
        $signatureBoxType = new SignatureBoxType();
        $signatureCoordinatesType = new SignatureCoordinatesType('');
        return [
            new PreloadedExtension(
                [$signatureBoxType, $signatureCoordinatesType],
                []
            ),
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
}
