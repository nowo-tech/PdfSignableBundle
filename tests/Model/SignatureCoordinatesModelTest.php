<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\Model;

use Nowo\PdfSignableBundle\Model\SignatureBoxModel;
use Nowo\PdfSignableBundle\Model\SignatureCoordinatesModel;
use PHPUnit\Framework\TestCase;

/**
 * Tests for SignatureCoordinatesModel default values, setters/getters and signature box collection.
 */
final class SignatureCoordinatesModelTest extends TestCase
{
    /**
     * Asserts default unit, origin and empty signature boxes.
     */
    public function testDefaultValues(): void
    {
        $model = new SignatureCoordinatesModel();
        self::assertNull($model->getPdfUrl());
        self::assertSame(SignatureCoordinatesModel::UNIT_MM, $model->getUnit());
        self::assertSame(SignatureCoordinatesModel::ORIGIN_BOTTOM_LEFT, $model->getOrigin());
        self::assertSame([], $model->getSignatureBoxes());
    }

    /**
     * Asserts pdfUrl, unit and origin setters and getters.
     */
    public function testSettersAndGetters(): void
    {
        $model = new SignatureCoordinatesModel();
        $model->setPdfUrl('https://example.com/doc.pdf');
        $model->setUnit(SignatureCoordinatesModel::UNIT_PT);
        $model->setOrigin(SignatureCoordinatesModel::ORIGIN_TOP_LEFT);

        self::assertSame('https://example.com/doc.pdf', $model->getPdfUrl());
        self::assertSame(SignatureCoordinatesModel::UNIT_PT, $model->getUnit());
        self::assertSame(SignatureCoordinatesModel::ORIGIN_TOP_LEFT, $model->getOrigin());
    }

    /**
     * Asserts addSignatureBox adds a box to the collection.
     */
    public function testAddSignatureBox(): void
    {
        $model = new SignatureCoordinatesModel();
        $box = new SignatureBoxModel();
        $box->setName('signer_1')->setPage(1)->setX(10)->setY(20)->setWidth(150)->setHeight(40);
        $model->addSignatureBox($box);

        self::assertCount(1, $model->getSignatureBoxes());
        self::assertSame('signer_1', $model->getSignatureBoxes()[0]->getName());
    }

    /**
     * Asserts setSignatureBoxes replaces the collection and returns self.
     */
    public function testSetSignatureBoxes(): void
    {
        $model = new SignatureCoordinatesModel();
        $box1 = (new SignatureBoxModel())->setName('a')->setPage(1);
        $box2 = (new SignatureBoxModel())->setName('b')->setPage(2);
        $model->addSignatureBox($box1);
        self::assertCount(1, $model->getSignatureBoxes());

        $result = $model->setSignatureBoxes([$box1, $box2]);
        self::assertSame($model, $result);
        self::assertCount(2, $model->getSignatureBoxes());
        self::assertSame('a', $model->getSignatureBoxes()[0]->getName());
        self::assertSame('b', $model->getSignatureBoxes()[1]->getName());
    }
}
