<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\Bundle;

use Nowo\PdfSignableBundle\DependencyInjection\PdfSignableExtension;
use Nowo\PdfSignableBundle\NowoPdfSignableBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;

/**
 * Tests for NowoPdfSignableBundle (container extension).
 */
final class NowoPdfSignableBundleTest extends TestCase
{
    public function testGetContainerExtensionReturnsPdfSignableExtension(): void
    {
        $bundle = new NowoPdfSignableBundle();
        $extension = $bundle->getContainerExtension();

        self::assertInstanceOf(ExtensionInterface::class, $extension);
        self::assertInstanceOf(PdfSignableExtension::class, $extension);
        self::assertSame('nowo_pdf_signable', $extension->getAlias());
    }

    public function testGetContainerExtensionReturnsSameInstanceOnMultipleCalls(): void
    {
        $bundle = new NowoPdfSignableBundle();
        $first = $bundle->getContainerExtension();
        $second = $bundle->getContainerExtension();

        self::assertSame($first, $second);
    }
}
