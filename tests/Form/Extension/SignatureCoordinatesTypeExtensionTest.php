<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\Form\Extension;

use Nowo\PdfSignableBundle\Form\Extension\SignatureCoordinatesTypeExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class SignatureCoordinatesTypeExtensionTest extends TestCase
{
    public function testGetExtendedTypes(): void
    {
        $types = SignatureCoordinatesTypeExtension::getExtendedTypes();
        $arr = \is_array($types) ? $types : iterator_to_array($types);

        self::assertCount(1, $arr);
        self::assertSame('Nowo\PdfSignableBundle\Form\SignatureCoordinatesType', $arr[0]);
    }

    public function testConfigureOptionsSetsDefaultsFromConstructor(): void
    {
        $extension = new SignatureCoordinatesTypeExtension(150.0, 40.0, true, false, 30.0, 15.0);
        $resolver = new OptionsResolver();
        $extension->configureOptions($resolver);

        $options = $resolver->resolve();

        self::assertSame(150.0, $options['default_box_width']);
        self::assertSame(40.0, $options['default_box_height']);
        self::assertTrue($options['lock_box_width']);
        self::assertFalse($options['lock_box_height']);
        self::assertSame(30.0, $options['min_box_width']);
        self::assertSame(15.0, $options['min_box_height']);
    }

    public function testConfigureOptionsWithNullDefaults(): void
    {
        $extension = new SignatureCoordinatesTypeExtension(null, null, false, false, null, null);
        $resolver = new OptionsResolver();
        $extension->configureOptions($resolver);

        $options = $resolver->resolve();

        self::assertNull($options['default_box_width']);
        self::assertNull($options['default_box_height']);
        self::assertFalse($options['lock_box_width']);
        self::assertFalse($options['lock_box_height']);
        self::assertNull($options['min_box_width']);
        self::assertNull($options['min_box_height']);
    }
}
