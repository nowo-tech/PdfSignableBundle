<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\DependencyInjection;

use Nowo\PdfSignableBundle\DependencyInjection\Configuration;
use Nowo\PdfSignableBundle\DependencyInjection\PdfSignableExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

/**
 * Tests for PdfSignableExtension (alias, load and prepend).
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

    public function testLoadSetsProxyUrlAllowlistParameter(): void
    {
        $container = new ContainerBuilder();
        $extension = new PdfSignableExtension();
        $extension->load([['proxy_url_allowlist' => ['https://cdn.example.com/']]], $container);

        self::assertSame(['https://cdn.example.com/'], $container->getParameter('nowo_pdf_signable.proxy_url_allowlist'));
    }

    /**
     * Prepend adds Twig paths/form_themes and Framework translator paths when those extensions are present.
     */
    public function testPrependAddsTwigAndFrameworkConfig(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new class extends Extension {
            public function getAlias(): string
            {
                return 'twig';
            }

            public function load(array $configs, ContainerBuilder $container): void
            {
            }
        });
        $container->registerExtension(new class extends Extension {
            public function getAlias(): string
            {
                return 'framework';
            }

            public function load(array $configs, ContainerBuilder $container): void
            {
            }
        });

        $extension = new PdfSignableExtension();
        $extension->prepend($container);

        $twigConfigs = $container->getExtensionConfig('twig');
        self::assertNotEmpty($twigConfigs);
        self::assertArrayHasKey('paths', $twigConfigs[0]);
        self::assertArrayHasKey('form_themes', $twigConfigs[0]);
        $paths = $twigConfigs[0]['paths'];
        $pathsString = implode('', array_merge(array_keys($paths), array_values($paths)));
        self::assertTrue(str_contains($pathsString, 'Resources/views'), 'Twig paths should contain bundle views path');
        self::assertContains('@NowoPdfSignable/form/theme.html.twig', $twigConfigs[0]['form_themes']);

        $frameworkConfigs = $container->getExtensionConfig('framework');
        self::assertNotEmpty($frameworkConfigs);
        self::assertArrayHasKey('translator', $frameworkConfigs[0]);
        self::assertArrayHasKey('paths', $frameworkConfigs[0]['translator']);
    }
}
