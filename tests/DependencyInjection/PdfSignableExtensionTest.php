<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\DependencyInjection;

use Nowo\PdfSignableBundle\DependencyInjection\Configuration;
use Nowo\PdfSignableBundle\DependencyInjection\PdfSignableExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests for PdfSignableExtension (alias and load).
 */
final class PdfSignableExtensionTest extends TestCase
{
    public function testGetAlias(): void
    {
        $extension = new PdfSignableExtension();
        self::assertSame(Configuration::ALIAS, $extension->getAlias());
        self::assertSame('nowo_pdf_signable', $extension->getAlias());
    }

    public function testLoadSetsParameters(): void
    {
        $container = new ContainerBuilder();
        $extension = new PdfSignableExtension();
        $extension->load([
            [
                'proxy_enabled' => false,
                'example_pdf_url' => 'https://example.com/default.pdf',
                'configs' => ['preset' => ['unit_default' => 'pt']],
            ],
        ], $container);

        self::assertFalse($container->getParameter('nowo_pdf_signable.proxy_enabled'));
        self::assertSame('https://example.com/default.pdf', $container->getParameter('nowo_pdf_signable.example_pdf_url'));
        self::assertSame(['preset' => ['unit_default' => 'pt']], $container->getParameter('nowo_pdf_signable.configs'));
    }
}
