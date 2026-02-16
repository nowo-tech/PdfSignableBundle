<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\Model;

use Nowo\PdfSignableBundle\Model\SignatureBoxModel;
use PHPUnit\Framework\TestCase;

/**
 * Tests for SignatureBoxModel default values, setters and getters.
 */
final class SignatureBoxModelTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $model = new SignatureBoxModel();
        self::assertSame(1, $model->getPage());
        self::assertSame('', $model->getName());
        self::assertSame(0.0, $model->getX());
        self::assertSame(0.0, $model->getY());
        self::assertSame(150.0, $model->getWidth());
        self::assertSame(40.0, $model->getHeight());
        self::assertSame(0.0, $model->getAngle());
        self::assertNull($model->getSignatureData());
        self::assertNull($model->getSignedAt());
    }

    public function testSettersAndGetters(): void
    {
        $model = new SignatureBoxModel();
        $model->setPage(2)
            ->setName('signer_1')
            ->setX(50.5)
            ->setY(100.25)
            ->setWidth(200.0)
            ->setHeight(60.0)
            ->setAngle(15.5);

        self::assertSame(2, $model->getPage());
        self::assertSame('signer_1', $model->getName());
        self::assertSame(50.5, $model->getX());
        self::assertSame(100.25, $model->getY());
        self::assertSame(200.0, $model->getWidth());
        self::assertSame(60.0, $model->getHeight());
        self::assertSame(15.5, $model->getAngle());
    }

    public function testToArrayAndFromArray(): void
    {
        $model = new SignatureBoxModel();
        $model->setName('witness')->setPage(2)->setX(10)->setY(20)->setWidth(120)->setHeight(30)->setAngle(5.0);
        $arr = $model->toArray();
        self::assertSame('witness', $arr['name']);
        self::assertSame(2, $arr['page']);
        self::assertSame(10.0, $arr['x']);
        self::assertSame(20.0, $arr['y']);
        self::assertSame(120.0, $arr['width']);
        self::assertSame(30.0, $arr['height']);
        self::assertSame(5.0, $arr['angle']);
        $restored = SignatureBoxModel::fromArray($arr);
        self::assertSame($model->getName(), $restored->getName());
        self::assertSame($model->getPage(), $restored->getPage());
        self::assertSame($model->getAngle(), $restored->getAngle());
    }

    public function testSignatureDataGetterSetter(): void
    {
        $model = new SignatureBoxModel();
        self::assertNull($model->getSignatureData());
        $dataUrl = 'data:image/png;base64,iVBORw0KGgo=';
        $model->setSignatureData($dataUrl);
        self::assertSame($dataUrl, $model->getSignatureData());
        $model->setSignatureData(null);
        self::assertNull($model->getSignatureData());
    }

    public function testToArrayAndFromArrayWithSignatureData(): void
    {
        $model = new SignatureBoxModel();
        $model->setName('a')->setPage(1)->setSignatureData('data:image/png;base64,abc');
        $arr = $model->toArray();
        self::assertArrayHasKey('signature_data', $arr);
        self::assertSame('data:image/png;base64,abc', $arr['signature_data']);
        $restored = SignatureBoxModel::fromArray($arr);
        self::assertSame($model->getSignatureData(), $restored->getSignatureData());
        $model->setSignatureData(null);
        $arr2 = $model->toArray();
        self::assertArrayNotHasKey('signature_data', $arr2);
    }

    public function testSignedAtGetterSetterAndToArrayFromArray(): void
    {
        $model = new SignatureBoxModel();
        self::assertNull($model->getSignedAt());
        $model->setSignedAt('2025-02-09T12:00:00+00:00');
        self::assertSame('2025-02-09T12:00:00+00:00', $model->getSignedAt());
        $arr = $model->toArray();
        self::assertArrayHasKey('signed_at', $arr);
        self::assertSame('2025-02-09T12:00:00+00:00', $arr['signed_at']);
        $restored = SignatureBoxModel::fromArray($arr);
        self::assertSame($model->getSignedAt(), $restored->getSignedAt());
        $model->setSignedAt(null);
        self::assertArrayNotHasKey('signed_at', $model->toArray());
    }

    public function testFluentInterface(): void
    {
        $model = new SignatureBoxModel();
        $result = $model->setName('test')->setPage(1);
        self::assertSame($model, $result);
    }

    public function testFromArrayWithEmptySignatureDataAndSignedAtTreatsAsNull(): void
    {
        $arr = [
            'name' => 's1',
            'page' => 1,
            'x' => 0,
            'y' => 0,
            'width' => 100,
            'height' => 30,
            'signature_data' => '',
            'signed_at' => '',
        ];
        $model = SignatureBoxModel::fromArray($arr);
        self::assertNull($model->getSignatureData());
        self::assertNull($model->getSignedAt());
    }
}
