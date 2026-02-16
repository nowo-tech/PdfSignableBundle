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

        self::assertSame([], $config['signature']['configs']);
    }

    public function testConfigsOverride(): void
    {
        $configuration = new Configuration();
        $processor = new Processor();
        $config = $processor->processConfiguration($configuration, [
            [
                'signature' => [
                    'configs' => [
                        'fixed_url' => [
                            'pdf_url' => 'https://example.com/template.pdf',
                            'url_field' => false,
                        ],
                    ],
                ],
            ],
        ]);

        self::assertArrayHasKey('fixed_url', $config['signature']['configs']);
        self::assertSame('https://example.com/template.pdf', $config['signature']['configs']['fixed_url']['pdf_url']);
        self::assertFalse($config['signature']['configs']['fixed_url']['url_field']);
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

    public function testAcroformEditorDefaults(): void
    {
        $configuration = new Configuration();
        $processor = new Processor();
        $config = $processor->processConfiguration($configuration, []);

        self::assertArrayHasKey('acroform', $config);
        self::assertFalse($config['acroform']['enabled']);
        self::assertSame('session', $config['acroform']['overrides_storage']);
        self::assertSame('request', $config['acroform']['document_key_mode']);
        self::assertFalse($config['acroform']['allow_pdf_modify']);
        self::assertNull($config['acroform']['editor_service_id']);
        self::assertSame(20971520, $config['acroform']['max_pdf_size']);
        self::assertSame(500, $config['acroform']['max_patches']);
    }

    public function testAcroformEditorOverride(): void
    {
        $configuration = new Configuration();
        $processor = new Processor();
        $config = $processor->processConfiguration($configuration, [
            [
                'acroform' => [
                    'enabled' => true,
                    'allow_pdf_modify' => true,
                    'max_pdf_size' => 10_000_000,
                ],
            ],
        ]);

        self::assertTrue($config['acroform']['enabled']);
        self::assertTrue($config['acroform']['allow_pdf_modify']);
        self::assertSame(10_000_000, $config['acroform']['max_pdf_size']);
    }

    public function testMinBoxWidthHeightDefaultsNull(): void
    {
        $configuration = new Configuration();
        $processor = new Processor();
        $config = $processor->processConfiguration($configuration, []);

        self::assertNull($config['signature']['min_box_width']);
        self::assertNull($config['signature']['min_box_height']);
    }

    public function testMinBoxWidthHeightOverride(): void
    {
        $configuration = new Configuration();
        $processor = new Processor();
        $config = $processor->processConfiguration($configuration, [
            [
                'signature' => [
                    'min_box_width' => 25.0,
                    'min_box_height' => 15.0,
                ],
            ],
        ]);

        self::assertSame(25.0, $config['signature']['min_box_width']);
        self::assertSame(15.0, $config['signature']['min_box_height']);
    }

    public function testAcroformEditorApplyScriptCommandDefault(): void
    {
        $configuration = new Configuration();
        $processor = new Processor();
        $config = $processor->processConfiguration($configuration, []);

        self::assertSame('python3', $config['acroform']['apply_script_command']);
    }

    public function testAcroformEditorProcessScriptCommandDefault(): void
    {
        $configuration = new Configuration();
        $processor = new Processor();
        $config = $processor->processConfiguration($configuration, []);

        self::assertSame('python3', $config['acroform']['process_script_command']);
    }

    public function testAcroformEditorScriptCommandsOverride(): void
    {
        $configuration = new Configuration();
        $processor = new Processor();
        $config = $processor->processConfiguration($configuration, [
            [
                'acroform' => [
                    'apply_script_command' => '/usr/bin/python3',
                    'process_script_command' => 'python',
                ],
            ],
        ]);

        self::assertSame('/usr/bin/python3', $config['acroform']['apply_script_command']);
        self::assertSame('python', $config['acroform']['process_script_command']);
    }

    public function testAcroformEditorMinFieldWidthHeightDefaults(): void
    {
        $configuration = new Configuration();
        $processor = new Processor();
        $config = $processor->processConfiguration($configuration, []);

        self::assertSame(12.0, $config['acroform']['min_field_width']);
        self::assertSame(12.0, $config['acroform']['min_field_height']);
    }

    public function testAcroformEditorMinFieldWidthHeightOverride(): void
    {
        $configuration = new Configuration();
        $processor = new Processor();
        $config = $processor->processConfiguration($configuration, [
            [
                'acroform' => [
                    'min_field_width' => 20.0,
                    'min_field_height' => 18.0,
                ],
            ],
        ]);

        self::assertSame(20.0, $config['acroform']['min_field_width']);
        self::assertSame(18.0, $config['acroform']['min_field_height']);
    }
}
