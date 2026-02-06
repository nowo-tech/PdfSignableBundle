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
    }

    public function testSettersAndGetters(): void
    {
        $model = new SignatureBoxModel();
        $model->setPage(2)
            ->setName('signer_1')
            ->setX(50.5)
            ->setY(100.25)
            ->setWidth(200.0)
            ->setHeight(60.0);

        self::assertSame(2, $model->getPage());
        self::assertSame('signer_1', $model->getName());
        self::assertSame(50.5, $model->getX());
        self::assertSame(100.25, $model->getY());
        self::assertSame(200.0, $model->getWidth());
        self::assertSame(60.0, $model->getHeight());
    }

    public function testFluentInterface(): void
    {
        $model = new SignatureBoxModel();
        $result = $model->setName('test')->setPage(1);
        self::assertSame($model, $result);
    }
}
