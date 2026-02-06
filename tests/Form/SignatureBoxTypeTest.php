<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\Form;

use Nowo\PdfSignableBundle\Form\SignatureBoxType;
use Nowo\PdfSignableBundle\Model\SignatureBoxModel;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;

/**
 * Tests for SignatureBoxType form type: options, submit and data mapping.
 */
final class SignatureBoxTypeTest extends TypeTestCase
{
    protected function getExtensions(): array
    {
        $type = new SignatureBoxType();
        return [
            new PreloadedExtension([$type], []),
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
}
