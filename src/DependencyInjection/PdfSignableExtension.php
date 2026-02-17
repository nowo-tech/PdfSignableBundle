<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\DependencyInjection;

use Nowo\PdfSignableBundle\AcroForm\Storage\AcroFormOverridesStorageInterface;
use Nowo\PdfSignableBundle\Controller\AcroFormOverridesController;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

use function dirname;

/**
 * Loads bundle configuration and services, and prepends Twig and Framework translator paths.
 *
 * Registers the bundle's services (SignatureController, form types) and sets parameters
 * (proxy_enabled, proxy_url_allowlist, example_pdf_url, configs) from config.
 *
 * Extends DependencyInjection\Extension\Extension (Symfony 6.4+); the previous
 * HttpKernel\DependencyInjection\Extension was just extending this class and is deprecated.
 */
final class PdfSignableExtension extends Extension implements PrependExtensionInterface
{
    /**
     * Loads services from Resources/config/services.yaml and sets container parameters.
     *
     * @param array<string, mixed> $configs Raw configuration arrays (e.g. from config files)
     * @param ContainerBuilder $container The container builder
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration($this->getConfiguration($configs, $container), $configs);

        $container->setParameter(Configuration::ALIAS . '.proxy_enabled', $config['proxy_enabled'] ?? true);
        $container->setParameter(Configuration::ALIAS . '.proxy_url_allowlist', $config['proxy_url_allowlist'] ?? []);
        $container->setParameter(Configuration::ALIAS . '.example_pdf_url', $config['example_pdf_url'] ?? '');
        $container->setParameter(Configuration::ALIAS . '.debug', $config['debug'] ?? false);

        $signature = $config['signature'] ?? [];
        $container->setParameter(Configuration::ALIAS . '.signature.default_config_alias', $signature['default_config_alias'] ?? 'default');
        $container->setParameter(Configuration::ALIAS . '.signature.default_box_width', $signature['default_box_width'] ?? null);
        $container->setParameter(Configuration::ALIAS . '.signature.default_box_height', $signature['default_box_height'] ?? null);
        $container->setParameter(Configuration::ALIAS . '.signature.lock_box_width', $signature['lock_box_width'] ?? false);
        $container->setParameter(Configuration::ALIAS . '.signature.lock_box_height', $signature['lock_box_height'] ?? false);
        $container->setParameter(Configuration::ALIAS . '.signature.min_box_width', $signature['min_box_width'] ?? null);
        $container->setParameter(Configuration::ALIAS . '.signature.min_box_height', $signature['min_box_height'] ?? null);
        $container->setParameter(Configuration::ALIAS . '.signature.configs', $signature['configs'] ?? []);

        $container->setParameter(Configuration::ALIAS . '.audit.fill_from_request', $config['audit']['fill_from_request'] ?? true);
        $container->setParameter(Configuration::ALIAS . '.tsa_url', $config['tsa_url'] ?? null);
        $container->setParameter(Configuration::ALIAS . '.signing_service_id', $config['signing_service_id'] ?? null);

        $acroform = $config['acroform'] ?? [];
        $container->setParameter(Configuration::ALIAS . '.acroform.enabled', $acroform['enabled'] ?? false);
        $container->setParameter(Configuration::ALIAS . '.acroform.overrides_storage', $acroform['overrides_storage'] ?? 'session');
        $container->setParameter(Configuration::ALIAS . '.acroform.document_key_mode', $acroform['document_key_mode'] ?? 'request');
        $container->setParameter(Configuration::ALIAS . '.acroform.allow_pdf_modify', $acroform['allow_pdf_modify'] ?? false);
        $container->setParameter(Configuration::ALIAS . '.acroform.editor_service_id', $acroform['editor_service_id'] ?? null);
        $container->setParameter(Configuration::ALIAS . '.acroform.max_pdf_size', $acroform['max_pdf_size'] ?? 20971520);
        $container->setParameter(Configuration::ALIAS . '.acroform.max_patches', $acroform['max_patches'] ?? 500);
        $container->setParameter(Configuration::ALIAS . '.acroform.fields_extractor_script', $acroform['fields_extractor_script'] ?? null);
        $container->setParameter(Configuration::ALIAS . '.acroform.apply_script', $acroform['apply_script'] ?? null);
        $container->setParameter(Configuration::ALIAS . '.acroform.apply_script_command', $acroform['apply_script_command'] ?? 'python3');
        $container->setParameter(Configuration::ALIAS . '.acroform.process_script', $acroform['process_script'] ?? null);
        $container->setParameter(Configuration::ALIAS . '.acroform.process_script_command', $acroform['process_script_command'] ?? 'python3');
        $container->setParameter(Configuration::ALIAS . '.acroform.default_config_alias', $acroform['default_config_alias'] ?? 'default');
        $container->setParameter(Configuration::ALIAS . '.acroform.min_field_width', $acroform['min_field_width'] ?? 12.0);
        $container->setParameter(Configuration::ALIAS . '.acroform.min_field_height', $acroform['min_field_height'] ?? 12.0);
        $container->setParameter(Configuration::ALIAS . '.acroform.label_mode', $acroform['label_mode'] ?? 'input');
        $container->setParameter(Configuration::ALIAS . '.acroform.label_choices', $acroform['label_choices'] ?? []);
        $container->setParameter(Configuration::ALIAS . '.acroform.label_other_text', $acroform['label_other_text'] ?? '');
        $container->setParameter(Configuration::ALIAS . '.acroform.field_name_mode', $acroform['field_name_mode'] ?? $acroform['label_mode'] ?? 'input');
        $container->setParameter(Configuration::ALIAS . '.acroform.field_name_choices', $acroform['field_name_choices'] ?? $acroform['label_choices'] ?? []);
        $container->setParameter(Configuration::ALIAS . '.acroform.field_name_other_text', $acroform['field_name_other_text'] ?? $acroform['label_other_text'] ?? '');
        $container->setParameter(Configuration::ALIAS . '.acroform.show_field_rect', $acroform['show_field_rect'] ?? true);
        $container->setParameter(Configuration::ALIAS . '.acroform.font_sizes', $acroform['font_sizes'] ?? []);
        $container->setParameter(Configuration::ALIAS . '.acroform.font_families', $acroform['font_families'] ?? []);
        $container->setParameter(Configuration::ALIAS . '.acroform.configs', $acroform['configs'] ?? []);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        // When overrides_storage is "session", #[AsAlias] on SessionAcroFormOverridesStorage sets the interface alias.
        // When it's a custom service id, we override the alias here.
        $storage = $acroform['overrides_storage'] ?? 'session';
        if ($storage !== 'session') {
            $container->setAlias(AcroFormOverridesStorageInterface::class, $storage);
        }

        // AcroFormOverridesController is loaded by the Controller\ resource (like SignatureController); only set $editor when configured.
        $editorServiceId = $acroform['editor_service_id'] ?? null;
        if ($editorServiceId !== null && $editorServiceId !== '' && $container->hasDefinition(AcroFormOverridesController::class)) {
            $container->getDefinition(AcroFormOverridesController::class)
                ->setArgument('$editor', new Reference($editorServiceId));
        }
    }

    /**
     * Returns the configuration alias used in config files (nowo_pdf_signable).
     *
     * @return string The alias
     */
    public function getAlias(): string
    {
        return Configuration::ALIAS;
    }

    /**
     * Prepends Twig paths and form theme, and Framework translator paths for the bundle.
     *
     * @param ContainerBuilder $container The container builder
     */
    public function prepend(ContainerBuilder $container): void
    {
        $bundleDir = dirname(__DIR__, 2);
        if ($container->hasExtension('twig')) {
            $container->prependExtensionConfig('twig', [
                'paths' => [
                    $bundleDir . '/src/Resources/views' => 'NowoPdfSignable',
                ],
                'form_themes' => ['@NowoPdfSignable/form/theme.html.twig'],
            ]);
        }
        if ($container->hasExtension('framework')) {
            $container->prependExtensionConfig('framework', [
                'translator' => [
                    'paths' => [
                        $bundleDir . '/src/Resources/translations',
                    ],
                ],
            ]);
        }
    }
}
