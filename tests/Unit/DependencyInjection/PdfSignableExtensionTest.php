<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\DependencyInjection;

use Nowo\PdfSignableBundle\AcroForm\Storage\AcroFormOverridesStorageInterface;
use Nowo\PdfSignableBundle\Controller\AcroFormOverridesController;
use Nowo\PdfSignableBundle\DependencyInjection\Configuration;
use Nowo\PdfSignableBundle\DependencyInjection\PdfSignableExtension;
use Nowo\PdfSignableBundle\Event\PdfSignableEvents;
use Nowo\PdfSignableBundle\EventListener\AcroFormApplyScriptListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\EventDispatcher\DependencyInjection\RegisterListenersPass;
use Symfony\Component\EventDispatcher\EventDispatcher;

use function dirname;
use function in_array;

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
                'proxy_enabled'   => false,
                'example_pdf_url' => 'https://example.com/default.pdf',
                'signature'       => [
                    'default_profile' => 'preset',
                    'profiles'        => ['preset' => ['unit_default' => 'pt']],
                ],
            ],
        ], $container);

        self::assertFalse($container->getParameter('nowo_pdf_signable.proxy_enabled'));
        self::assertSame('https://example.com/default.pdf', $container->getParameter('nowo_pdf_signable.example_pdf_url'));
        self::assertSame(['preset' => ['unit_default' => 'pt']], $container->getParameter('nowo_pdf_signable.signature.profiles'));
        self::assertSame(['preset' => ['unit_default' => 'pt']], $container->getParameter('nowo_pdf_signable.signature.configs'));
        self::assertSame('preset', $container->getParameter('nowo_pdf_signable.signature.default_profile'));
        self::assertSame('preset', $container->getParameter('nowo_pdf_signable.signature.default_config_alias'));
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
                    'default_box_width'  => 150.0,
                    'default_box_height' => 40.0,
                    'lock_box_width'     => true,
                    'lock_box_height'    => true,
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
                    'min_box_width'  => 30.0,
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
                    'apply_script_command'   => 'python',
                    'process_script_command' => '/usr/bin/python3.12',
                    'min_field_width'        => 25.0,
                    'min_field_height'       => 18.0,
                ],
            ],
        ], $container2);

        self::assertSame('python', $container2->getParameter('nowo_pdf_signable.acroform.apply_script_command'));
        self::assertSame('/usr/bin/python3.12', $container2->getParameter('nowo_pdf_signable.acroform.process_script_command'));
        self::assertSame(25.0, $container2->getParameter('nowo_pdf_signable.acroform.min_field_width'));
        self::assertSame(18.0, $container2->getParameter('nowo_pdf_signable.acroform.min_field_height'));
    }

    /**
     * The bundled scripts moved from .scripts/ (dev tooling, not shipped) to src/Resources/scripts/ (REQ-PKG-004):
     * pre-move vendor paths are remapped, other paths are kept as configured.
     */
    public function testLoadRemapsPreMoveBundledScriptPaths(): void
    {
        $container = new ContainerBuilder();
        (new PdfSignableExtension())->load([
            [
                'acroform' => [
                    'fields_extractor_script' => '%kernel.project_dir%/vendor/nowo-tech/pdf-signable-bundle/.scripts/extract_acroform_fields.py',
                    'apply_script'            => 'C:\\app\\vendor\\nowo-tech\\pdf-signable-bundle\\.scripts\\apply_acroform_patches.py',
                    'process_script'          => '/var/www/PdfSignableBundle/.scripts/process_modified_pdf.py',
                ],
            ],
        ], $container);

        self::assertSame('%kernel.project_dir%/vendor/nowo-tech/pdf-signable-bundle/src/Resources/scripts/extract_acroform_fields.py', $container->getParameter('nowo_pdf_signable.acroform.fields_extractor_script'));
        self::assertSame('C:\\app\\vendor\\nowo-tech\\pdf-signable-bundle\\src\\Resources\\scripts\\apply_acroform_patches.py', $container->getParameter('nowo_pdf_signable.acroform.apply_script'));
        self::assertSame('/var/www/PdfSignableBundle/src/Resources/scripts/process_modified_pdf.py', $container->getParameter('nowo_pdf_signable.acroform.process_script'));

        $kept = new ContainerBuilder();
        (new PdfSignableExtension())->load([
            [
                'acroform' => [
                    'fields_extractor_script' => '%kernel.project_dir%/vendor/nowo-tech/pdf-signable-bundle/src/Resources/scripts/extract_acroform_fields.py',
                    'apply_script'            => '/srv/app/.scripts/apply_acroform_patches.py',
                ],
            ],
        ], $kept);

        self::assertSame('%kernel.project_dir%/vendor/nowo-tech/pdf-signable-bundle/src/Resources/scripts/extract_acroform_fields.py', $kept->getParameter('nowo_pdf_signable.acroform.fields_extractor_script'));
        self::assertSame('/srv/app/.scripts/apply_acroform_patches.py', $kept->getParameter('nowo_pdf_signable.acroform.apply_script'));
        self::assertNull($kept->getParameter('nowo_pdf_signable.acroform.process_script'));
    }

    /** The bundled runtime scripts ship under src/Resources/scripts/. */
    public function testBundledScriptsShipUnderResources(): void
    {
        foreach (['extract_acroform_fields', 'apply_acroform_patches', 'process_modified_pdf'] as $script) {
            self::assertFileExists(dirname(__DIR__, 3) . '/src/Resources/scripts/' . $script . '.py');
        }
    }

    /**
     * Prepend adds Twig form themes and Framework translator paths when those extensions are present.
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
        self::assertArrayHasKey('form_themes', $twigConfigs[0]);
        self::assertContains('@NowoPdfSignableBundle/form/theme.html.twig', $twigConfigs[0]['form_themes']);

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
        $def       = $container->getDefinition(AcroFormOverridesController::class);
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
                'audit'              => ['fill_from_request' => false],
                'tsa_url'            => 'https://tsa.example.com',
                'signing_service_id' => 'app.pades_signer',
            ],
        ], $container);

        self::assertFalse($container->getParameter('nowo_pdf_signable.audit.fill_from_request'));
        self::assertSame('https://tsa.example.com', $container->getParameter('nowo_pdf_signable.tsa_url'));
        self::assertSame('app.pades_signer', $container->getParameter('nowo_pdf_signable.signing_service_id'));
    }

    /** Legacy acroform.label_* config reaches the field_name_* parameters. */
    public function testLoadAppliesLegacyLabelKeysToFieldNameParameters(): void
    {
        $container = new ContainerBuilder();
        (new PdfSignableExtension())->load([
            ['acroform' => ['label_mode' => 'choice', 'label_choices' => ['A'], 'label_other_text' => 'Other']],
        ], $container);

        self::assertSame('choice', $container->getParameter('nowo_pdf_signable.acroform.field_name_mode'));
        self::assertSame(['A'], $container->getParameter('nowo_pdf_signable.acroform.field_name_choices'));
        self::assertSame('Other', $container->getParameter('nowo_pdf_signable.acroform.field_name_other_text'));
        self::assertSame('python3', $container->getParameter('nowo_pdf_signable.acroform.fields_extractor_command'));
    }

    /** The apply script listener is registered exactly once for ACROFORM_APPLY_REQUEST (attribute only, no extra yaml tag). */
    public function testApplyScriptListenerIsRegisteredOnce(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        $container->registerAttributeForAutoconfiguration(
            AsEventListener::class,
            static function (ChildDefinition $definition, AsEventListener $attribute): void {
                $definition->addTag('kernel.event_listener', get_object_vars($attribute));
            },
        );
        $container->register('event_dispatcher', EventDispatcher::class)->setPublic(true);
        (new PdfSignableExtension())->load([[]], $container);
        $container->addCompilerPass(new RegisterListenersPass());
        // Remove services that need framework services we do not register here.
        foreach (array_keys($container->getDefinitions()) as $id) {
            if (!in_array($id, ['service_container', 'event_dispatcher', AcroFormApplyScriptListener::class], true)) {
                $container->removeDefinition($id);
            }
        }
        $container->compile();

        /** @var EventDispatcher $dispatcher */
        $dispatcher = $container->get('event_dispatcher');
        $listeners  = $dispatcher->getListeners(PdfSignableEvents::ACROFORM_APPLY_REQUEST);
        self::assertCount(1, $listeners);
        self::assertSame(-100, $dispatcher->getListenerPriority(PdfSignableEvents::ACROFORM_APPLY_REQUEST, $listeners[0]));
    }
}
