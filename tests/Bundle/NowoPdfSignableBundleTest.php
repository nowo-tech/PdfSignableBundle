<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\Bundle;

use Nowo\PdfSignableBundle\DependencyInjection\PdfSignableExtension;
use Nowo\PdfSignableBundle\DependencyInjection\ProxyUrlAllowlistValidationPass;
use Nowo\PdfSignableBundle\NowoPdfSignableBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;

use function is_array;

/**
 * Tests for NowoPdfSignableBundle (container extension and compiler pass).
 */
final class NowoPdfSignableBundleTest extends TestCase
{
    public function testGetContainerExtensionReturnsPdfSignableExtension(): void
    {
        $bundle    = new NowoPdfSignableBundle();
        $extension = $bundle->getContainerExtension();

        self::assertInstanceOf(ExtensionInterface::class, $extension);
        self::assertInstanceOf(PdfSignableExtension::class, $extension);
        self::assertSame('nowo_pdf_signable', $extension->getAlias());
    }

    public function testGetContainerExtensionReturnsSameInstanceOnMultipleCalls(): void
    {
        $bundle = new NowoPdfSignableBundle();
        $first  = $bundle->getContainerExtension();
        $second = $bundle->getContainerExtension();

        self::assertSame($first, $second);
    }

    public function testGetPathReturnsBundleDirectory(): void
    {
        $bundle = new NowoPdfSignableBundle();
        $path   = $bundle->getPath();

        self::assertIsString($path);
        self::assertNotEmpty($path);
        self::assertDirectoryExists($path);
        self::assertFileExists($path . '/Resources/config/services.yaml');
    }

    public function testBuildRegistersProxyUrlAllowlistValidationPass(): void
    {
        $container = new ContainerBuilder();
        $bundle    = new NowoPdfSignableBundle();
        $bundle->build($container);

        $passes = $container->getCompiler()->getPassConfig()->getBeforeOptimizationPasses();
        $found  = false;
        foreach ($passes as $p) {
            $pass = is_array($p) ? $p[0] : $p;
            if ($pass instanceof ProxyUrlAllowlistValidationPass) {
                $found = true;
                break;
            }
        }
        self::assertTrue($found, 'build() must register ProxyUrlAllowlistValidationPass');
    }
}
