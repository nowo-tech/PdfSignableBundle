<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\DependencyInjection;

use Nowo\PdfSignableBundle\AcroForm\Storage\AcroFormOverridesStorageInterface;
use Nowo\PdfSignableBundle\Controller\AcroFormOverridesController;
use Nowo\PdfSignableBundle\DependencyInjection\Configuration;
use Nowo\PdfSignableBundle\DependencyInjection\PdfSignableExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;

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
                'signature' => ['configs' => ['preset' => ['unit_default' => 'pt']]],
            ],
        ], $container);

        self::assertFalse($container->getParameter('nowo_pdf_signable.proxy_enabled'));
        self::assertSame('https://example.com/default.pdf', $container->getParameter('nowo_pdf_signable.example_pdf_url'));
        self::assertSame(['preset' => ['unit_default' => 'pt']], $container->getParameter('nowo_pdf_signable.signature.configs'));
        self::assertNull($container->getParameter('nowo_pdf_signable.signature.default_box_width'));
        self::assertNull($container->getParameter('nowo_pdf_signable.signature.default_box_height'));
        self::assertFalse($container->getParameter('nowo_pdf_signable.signature.lock_box_width'));
        self::assertFalse($container->getParameter('nowo_pdf_signable.signature.lock_box_height'));
    }

    public function testLoadSetsBoxDimensionParameters(): void
    {
        $container = new ContainerBuilder();
        $extension = new PdfSignableExtension();
        $extension->load([
            [
                'signature' => [
                    'default_box_width' => 150.0,
                    'default_box_height' => 40.0,
                    'lock_box_width' => true,
                    'lock_box_height' => true,
                ],
            ],
        ], $container);

        self::assertSame(150.0, $container->getParameter('nowo_pdf_signable.signature.default_box_width'));
        self::assertSame(40.0, $container->getParameter('nowo_pdf_signable.signature.default_box_height'));
        self::assertTrue($container->getParameter('nowo_pdf_signable.signature.lock_box_width'));
        self::assertTrue($container->getParameter('nowo_pdf_signable.signature.lock_box_height'));
    }

    public function testLoadSetsProxyUrlAllowlistParameter(): void
    {
        $container = new ContainerBuilder();
        $extension = new PdfSignableExtension();
        $extension->load([['proxy_url_allowlist' => ['https://cdn.example.com/']]], $container);

        self::assertSame(['https://cdn.example.com/'], $container->getParameter('nowo_pdf_signable.proxy_url_allowlist'));
    }

    public function testLoadSetsMinBoxParameters(): void
    {
        $container = new ContainerBuilder();
        $extension = new PdfSignableExtension();
        $extension->load([], $container);

        self::assertNull($container->getParameter('nowo_pdf_signable.signature.min_box_width'));
        self::assertNull($container->getParameter('nowo_pdf_signable.signature.min_box_height'));

        $container2 = new ContainerBuilder();
        $extension->load([
            [
                'signature' => [
                    'min_box_width' => 30.0,
                    'min_box_height' => 20.0,
                ],
            ],
        ], $container2);

        self::assertSame(30.0, $container2->getParameter('nowo_pdf_signable.signature.min_box_width'));
        self::assertSame(20.0, $container2->getParameter('nowo_pdf_signable.signature.min_box_height'));
    }

    public function testLoadSetsAcroformScriptAndMinFieldParameters(): void
    {
        $container = new ContainerBuilder();
        $extension = new PdfSignableExtension();
        $extension->load([], $container);

        self::assertSame('python3', $container->getParameter('nowo_pdf_signable.acroform.apply_script_command'));
        self::assertSame('python3', $container->getParameter('nowo_pdf_signable.acroform.process_script_command'));
        self::assertSame(12.0, $container->getParameter('nowo_pdf_signable.acroform.min_field_width'));
        self::assertSame(12.0, $container->getParameter('nowo_pdf_signable.acroform.min_field_height'));

        $container2 = new ContainerBuilder();
        $extension->load([
            [
                'acroform' => [
                    'apply_script_command' => 'python',
                    'process_script_command' => '/usr/bin/python3.12',
                    'min_field_width' => 25.0,
                    'min_field_height' => 18.0,
                ],
            ],
        ], $container2);

        self::assertSame('python', $container2->getParameter('nowo_pdf_signable.acroform.apply_script_command'));
        self::assertSame('/usr/bin/python3.12', $container2->getParameter('nowo_pdf_signable.acroform.process_script_command'));
        self::assertSame(25.0, $container2->getParameter('nowo_pdf_signable.acroform.min_field_width'));
        self::assertSame(18.0, $container2->getParameter('nowo_pdf_signable.acroform.min_field_height'));
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

    /**
     * Prepend does not fail when twig and framework extensions are not registered.
     */
    public function testPrependWithEmptyContainerDoesNotFail(): void
    {
        $container = new ContainerBuilder();
        $extension = new PdfSignableExtension();

        $extension->prepend($container);

        self::assertFalse($container->hasExtension('twig'));
        self::assertFalse($container->hasExtension('framework'));
    }

    /**
     * When acroform.overrides_storage is not "session", the extension sets an alias for AcroFormOverridesStorageInterface.
     */
    public function testLoadSetsCustomOverridesStorageAlias(): void
    {
        $container = new ContainerBuilder();
        $extension = new PdfSignableExtension();
        $extension->load([
            ['acroform' => ['overrides_storage' => 'my_custom_storage_service']],
        ], $container);

        self::assertTrue($container->hasAlias(AcroFormOverridesStorageInterface::class));
        self::assertSame('my_custom_storage_service', (string) $container->getAlias(AcroFormOverridesStorageInterface::class));
    }

    /**
     * When acroform.editor_service_id is set, the extension injects it into AcroFormOverridesController.
     */
    public function testLoadSetsEditorServiceIdOnController(): void
    {
        $container = new ContainerBuilder();
        $extension = new PdfSignableExtension();
        $extension->load([
            ['acroform' => ['editor_service_id' => 'app.acroform_editor']],
        ], $container);

        self::assertTrue($container->hasDefinition(AcroFormOverridesController::class));
        $def = $container->getDefinition(AcroFormOverridesController::class);
        $editorRef = $def->getArgument('$editor');
        self::assertInstanceOf(Reference::class, $editorRef);
        self::assertSame('app.acroform_editor', (string) $editorRef);
    }

    public function testLoadSetsAuditAndTsaAndSigningServiceParameters(): void
    {
        $container = new ContainerBuilder();
        $extension = new PdfSignableExtension();
        $extension->load([
            [
                'audit' => ['fill_from_request' => false],
                'tsa_url' => 'https://tsa.example.com',
                'signing_service_id' => 'app.pades_signer',
            ],
        ], $container);

        self::assertFalse($container->getParameter('nowo_pdf_signable.audit.fill_from_request'));
        self::assertSame('https://tsa.example.com', $container->getParameter('nowo_pdf_signable.tsa_url'));
        self::assertSame('app.pades_signer', $container->getParameter('nowo_pdf_signable.signing_service_id'));
    }
}
