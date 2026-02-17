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

    public function testSetPdfUrlNullClearsUrl(): void
    {
        $model = new SignatureCoordinatesModel();
        $model->setPdfUrl('https://example.com/doc.pdf');
        self::assertNotNull($model->getPdfUrl());
        $model->setPdfUrl(null);
        self::assertNull($model->getPdfUrl());
    }

    /**
     * Asserts addSignatureBox adds a box to the collection.
     */
    public function testAddSignatureBox(): void
    {
        $model = new SignatureCoordinatesModel();
        $box   = new SignatureBoxModel();
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
        $box1  = (new SignatureBoxModel())->setName('a')->setPage(1);
        $box2  = (new SignatureBoxModel())->setName('b')->setPage(2);
        $model->addSignatureBox($box1);
        self::assertCount(1, $model->getSignatureBoxes());

        $result = $model->setSignatureBoxes([$box1, $box2]);
        self::assertSame($model, $result);
        self::assertCount(2, $model->getSignatureBoxes());
        self::assertSame('a', $model->getSignatureBoxes()[0]->getName());
        self::assertSame('b', $model->getSignatureBoxes()[1]->getName());
    }

    /**
     * Asserts toArray and fromArray round-trip.
     */
    public function testToArrayAndFromArray(): void
    {
        $model = new SignatureCoordinatesModel();
        $model->setPdfUrl('https://example.com/doc.pdf');
        $model->setUnit(SignatureCoordinatesModel::UNIT_MM);
        $model->setOrigin(SignatureCoordinatesModel::ORIGIN_BOTTOM_LEFT);
        $model->addSignatureBox(
            (new SignatureBoxModel())->setName('s1')->setPage(1)->setX(10)->setY(20)->setWidth(150)->setHeight(40)->setAngle(0),
        );
        $model->addSignatureBox(
            (new SignatureBoxModel())->setName('s2')->setPage(2)->setX(50)->setY(60)->setWidth(120)->setHeight(30)->setAngle(5.0),
        );
        $arr = $model->toArray();
        self::assertSame('https://example.com/doc.pdf', $arr['pdf_url']);
        self::assertSame('mm', $arr['unit']);
        self::assertSame('bottom_left', $arr['origin']);
        self::assertCount(2, $arr['signature_boxes']);
        self::assertSame('s1', $arr['signature_boxes'][0]['name']);
        self::assertSame(5.0, $arr['signature_boxes'][1]['angle']);
        $restored = SignatureCoordinatesModel::fromArray($arr);
        self::assertSame($model->getPdfUrl(), $restored->getPdfUrl());
        self::assertCount(2, $restored->getSignatureBoxes());
        self::assertSame($model->getSignatureBoxes()[1]->getAngle(), $restored->getSignatureBoxes()[1]->getAngle());
    }

    public function testSigningConsentAndAuditMetadata(): void
    {
        $model = new SignatureCoordinatesModel();
        $model->setSigningConsent(true);
        self::assertTrue($model->getSigningConsent());
        $model->setAuditMetadata(['signed_at' => '2025-02-09T12:00:00+00:00', 'ip' => '127.0.0.1']);
        self::assertSame(['signed_at' => '2025-02-09T12:00:00+00:00', 'ip' => '127.0.0.1'], $model->getAuditMetadata());
        $arr = $model->toArray();
        self::assertArrayHasKey('signing_consent', $arr);
        self::assertTrue($arr['signing_consent']);
        self::assertArrayHasKey('audit_metadata', $arr);
        self::assertSame('127.0.0.1', $arr['audit_metadata']['ip']);
        $restored = SignatureCoordinatesModel::fromArray($arr);
        self::assertTrue($restored->getSigningConsent());
        self::assertSame($model->getAuditMetadata(), $restored->getAuditMetadata());
    }

    public function testFromArraySkipsNonArraySignatureBoxItems(): void
    {
        $data = [
            'pdf_url'         => 'https://example.com/doc.pdf',
            'unit'            => SignatureCoordinatesModel::UNIT_MM,
            'origin'          => SignatureCoordinatesModel::ORIGIN_BOTTOM_LEFT,
            'signature_boxes' => [
                ['name' => 's1', 'page' => 1, 'x' => 0, 'y' => 0, 'width' => 100, 'height' => 30],
                'invalid',
                null,
                ['name' => 's2', 'page' => 2, 'x' => 10, 'y' => 20, 'width' => 80, 'height' => 25],
            ],
        ];
        $model = SignatureCoordinatesModel::fromArray($data);
        self::assertCount(2, $model->getSignatureBoxes());
        self::assertSame('s1', $model->getSignatureBoxes()[0]->getName());
        self::assertSame('s2', $model->getSignatureBoxes()[1]->getName());
    }
}
