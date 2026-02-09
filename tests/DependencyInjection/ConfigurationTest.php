<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\DependencyInjection;

use Nowo\PdfSignableBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;

/**
 * Tests for the bundle Configuration (nowo_pdf_signable tree and defaults).
 */
final class ConfigurationTest extends TestCase
{
    public function testDefaultConfig(): void
    {
        $configuration = new Configuration();
        $processor = new Processor();
        $config = $processor->processConfiguration($configuration, []);

        self::assertTrue($config['proxy_enabled']);
        self::assertSame([], $config['proxy_url_allowlist']);
        self::assertIsString($config['example_pdf_url']);
        self::assertNotEmpty($config['example_pdf_url']);
    }

    public function testConfigAlias(): void
    {
        self::assertSame('nowo_pdf_signable', Configuration::ALIAS);
    }

    public function testProxyDisabledOverride(): void
    {
        $configuration = new Configuration();
        $processor = new Processor();
        $config = $processor->processConfiguration($configuration, [
            ['proxy_enabled' => false],
        ]);

        self::assertFalse($config['proxy_enabled']);
    }

    public function testExamplePdfUrlOverride(): void
    {
        $configuration = new Configuration();
        $processor = new Processor();
        $config = $processor->processConfiguration($configuration, [
            ['example_pdf_url' => 'https://example.com/doc.pdf'],
        ]);

        self::assertSame('https://example.com/doc.pdf', $config['example_pdf_url']);
    }

    public function testConfigsDefaultEmpty(): void
    {
        $configuration = new Configuration();
        $processor = new Processor();
        $config = $processor->processConfiguration($configuration, []);

        self::assertSame([], $config['configs']);
    }

    public function testConfigsOverride(): void
    {
        $configuration = new Configuration();
        $processor = new Processor();
        $config = $processor->processConfiguration($configuration, [
            [
                'configs' => [
                    'fixed_url' => [
                        'pdf_url' => 'https://example.com/template.pdf',
                        'url_field' => false,
                    ],
                ],
            ],
        ]);

        self::assertArrayHasKey('fixed_url', $config['configs']);
        self::assertSame('https://example.com/template.pdf', $config['configs']['fixed_url']['pdf_url']);
        self::assertFalse($config['configs']['fixed_url']['url_field']);
    }

    public function testProxyUrlAllowlistOverride(): void
    {
        $configuration = new Configuration();
        $processor = new Processor();
        $config = $processor->processConfiguration($configuration, [
            [
                'proxy_url_allowlist' => [
                    'https://cdn.example.com/',
                    '#^https://internal\.corp/#',
                ],
            ],
        ]);

        self::assertSame(
            ['https://cdn.example.com/', '#^https://internal\.corp/#'],
            $config['proxy_url_allowlist']
        );
    }

    public function testDebugDefaultFalse(): void
    {
        $configuration = new Configuration();
        $processor = new Processor();
        $config = $processor->processConfiguration($configuration, []);

        self::assertFalse($config['debug']);
    }

    public function testDebugOverride(): void
    {
        $configuration = new Configuration();
        $processor = new Processor();
        $config = $processor->processConfiguration($configuration, [
            ['debug' => true],
        ]);

        self::assertTrue($config['debug']);
    }
}
