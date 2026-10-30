<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\DependencyInjection;

use Nowo\PdfSignableBundle\AcroForm\Storage\AcroFormOverridesStorageInterface;
use Nowo\PdfSignableBundle\Controller\AcroFormOverridesController;
use Nowo\PdfSignableBundle\NowoPdfSignableBundle;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

use function array_key_exists;
use function array_values;
use function dirname;
use function is_array;
use function is_string;
use function preg_replace;

/**
 * Loads bundle configuration and services, and prepends Twig and Framework translator paths.
 *
 * Registers the bundle's services (SignatureController, form types) and sets parameters
 * (proxy_enabled, proxy_url_allowlist, example_pdf_url, signature/acroform profiles) from config.
 *
 * Extends DependencyInjection\Extension\Extension (Symfony 6.4+); the previous
 * HttpKernel\DependencyInjection\Extension was just extending this class and is deprecated.
 */
final class PdfSignableExtension extends Extension implements PrependExtensionInterface
{
    /**
     * Loads services from Resources/config/services.yaml and sets container parameters.
     *
     * @param array<int, array<string, mixed>> $configs Raw configuration arrays (e.g. from config files)
     * @param ContainerBuilder $container The container builder
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration($this->getConfiguration($configs, $container), $configs);

        $security = $config['security'];
        $container->setParameter(Configuration::ALIAS . '.security.access_roles', array_values($security['access_roles']));
        $container->setParameter(Configuration::ALIAS . '.security.access_checker', $security['access_checker']);
        $container->setParameter(Configuration::ALIAS . '.security.allow_unauthenticated', $security['allow_unauthenticated']);
        $container->setParameter(Configuration::ALIAS . '.proxy_enabled', $config['proxy_enabled'] ?? true);
        $container->setParameter(Configuration::ALIAS . '.proxy_url_allowlist', $config['proxy_url_allowlist'] ?? []);
        $container->setParameter(Configuration::ALIAS . '.proxy_max_pdf_size', $config['proxy_max_pdf_size'] ?? 20971520);
        $container->setParameter(Configuration::ALIAS . '.proxy_url_allowlist_required', $config['proxy_url_allowlist_required'] ?? true);
        $container->setParameter(Configuration::ALIAS . '.example_pdf_url', $config['example_pdf_url'] ?? '');
        $container->setParameter(Configuration::ALIAS . '.debug', $config['debug'] ?? false);
        $container->setParameter(Configuration::ALIAS . '.http_timeout', $config['http_timeout'] ?? 30.0);
        $container->setParameter(Configuration::ALIAS . '.process_timeout', $config['process_timeout'] ?? 60.0);
        $container->setParameter(Configuration::ALIAS . '.process_script_timeout', $config['process_script_timeout'] ?? 120.0);
        $container->setParameter(Configuration::ALIAS . '.dependency_check_timeout', $config['dependency_check_timeout'] ?? 5.0);

        $signature         = $config['signature'] ?? [];
        $signatureDefault  = $signature['default_profile'] ?? 'default';
        $signatureProfiles = $signature['profiles'] ?? [];
        $container->setParameter(Configuration::ALIAS . '.signature.default_profile', $signatureDefault);
        $container->setParameter(Configuration::ALIAS . '.signature.profiles', $signatureProfiles);
        // Legacy parameter names (BC)
        $container->setParameter(Configuration::ALIAS . '.signature.default_config_alias', $signatureDefault);
        $container->setParameter(Configuration::ALIAS . '.signature.configs', $signatureProfiles);
        $container->setParameter(Configuration::ALIAS . '.signature.default_box_width', $signature['default_box_width'] ?? null);
        $container->setParameter(Configuration::ALIAS . '.signature.default_box_height', $signature['default_box_height'] ?? null);
        $container->setParameter(Configuration::ALIAS . '.signature.lock_box_width', $signature['lock_box_width'] ?? false);
        $container->setParameter(Configuration::ALIAS . '.signature.lock_box_height', $signature['lock_box_height'] ?? false);
        $container->setParameter(Configuration::ALIAS . '.signature.min_box_width', $signature['min_box_width'] ?? null);
        $container->setParameter(Configuration::ALIAS . '.signature.min_box_height', $signature['min_box_height'] ?? null);

        $container->setParameter(Configuration::ALIAS . '.audit.fill_from_request', $config['audit']['fill_from_request'] ?? true);
        $container->setParameter(Configuration::ALIAS . '.tsa_url', $config['tsa_url'] ?? null);
        $container->setParameter(Configuration::ALIAS . '.signing_service_id', $config['signing_service_id'] ?? null);

        $acroform = $config['acroform'] ?? [];
        $container->setParameter(Configuration::ALIAS . '.acroform.enabled', $acroform['enabled'] ?? false);
        $container->setParameter(Configuration::ALIAS . '.acroform.overrides_storage', $acroform['overrides_storage'] ?? 'session');
        // Deprecated, no effect (kept so existing references to the parameter keep resolving).
        $container->setParameter(Configuration::ALIAS . '.acroform.document_key_mode', $acroform['document_key_mode'] ?? 'request');
        $container->setParameter(Configuration::ALIAS . '.acroform.allow_pdf_modify', $acroform['allow_pdf_modify'] ?? false);
        $container->setParameter(Configuration::ALIAS . '.acroform.editor_service_id', $acroform['editor_service_id'] ?? null);
        $container->setParameter(Configuration::ALIAS . '.acroform.max_pdf_size', $acroform['max_pdf_size'] ?? 20971520);
        $container->setParameter(Configuration::ALIAS . '.acroform.max_patches', $acroform['max_patches'] ?? 500);
        $container->setParameter(Configuration::ALIAS . '.acroform.fields_extractor_script', $this->bundledScriptPath($acroform['fields_extractor_script'] ?? null));
        $container->setParameter(Configuration::ALIAS . '.acroform.fields_extractor_command', $acroform['fields_extractor_command'] ?? 'python3');
        $container->setParameter(Configuration::ALIAS . '.acroform.apply_script', $this->bundledScriptPath($acroform['apply_script'] ?? null));
        $container->setParameter(Configuration::ALIAS . '.acroform.apply_script_command', $acroform['apply_script_command'] ?? 'python3');
        $container->setParameter(Configuration::ALIAS . '.acroform.process_script', $this->bundledScriptPath($acroform['process_script'] ?? null));
        $container->setParameter(Configuration::ALIAS . '.acroform.process_script_command', $acroform['process_script_command'] ?? 'python3');
        $acroformDefault  = $acroform['default_profile'] ?? 'default';
        $acroformProfiles = $acroform['profiles'] ?? [];
        $container->setParameter(Configuration::ALIAS . '.acroform.default_profile', $acroformDefault);
        $container->setParameter(Configuration::ALIAS . '.acroform.profiles', $acroformProfiles);
        // Legacy parameter names (BC)
        $container->setParameter(Configuration::ALIAS . '.acroform.default_config_alias', $acroformDefault);
        $container->setParameter(Configuration::ALIAS . '.acroform.configs', $acroformProfiles);
        $container->setParameter(Configuration::ALIAS . '.acroform.min_field_width', $acroform['min_field_width'] ?? 12.0);
        $container->setParameter(Configuration::ALIAS . '.acroform.min_field_height', $acroform['min_field_height'] ?? 12.0);
        $container->setParameter(Configuration::ALIAS . '.acroform.label_mode', $acroform['label_mode'] ?? 'input');
        $container->setParameter(Configuration::ALIAS . '.acroform.label_choices', $acroform['label_choices'] ?? []);
        $container->setParameter(Configuration::ALIAS . '.acroform.label_other_text', $acroform['label_other_text'] ?? '');
        // Legacy label_* values are copied to field_name_* by Configuration when field_name_* are not set.
        $container->setParameter(Configuration::ALIAS . '.acroform.field_name_mode', $acroform['field_name_mode'] ?? 'input');
        $container->setParameter(Configuration::ALIAS . '.acroform.field_name_choices', $acroform['field_name_choices'] ?? []);
        $container->setParameter(Configuration::ALIAS . '.acroform.field_name_other_text', $acroform['field_name_other_text'] ?? '');
        $container->setParameter(Configuration::ALIAS . '.acroform.show_field_rect', $acroform['show_field_rect'] ?? true);
        $container->setParameter(Configuration::ALIAS . '.acroform.font_sizes', $acroform['font_sizes'] ?? []);
        $container->setParameter(Configuration::ALIAS . '.acroform.font_families', $acroform['font_families'] ?? []);

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
     * Prepends Twig paths and form theme, Framework translator paths and the FormKit profile for the bundle.
     *
     * @param ContainerBuilder $container The container builder
     */
    public function prepend(ContainerBuilder $container): void
    {
        $bundleDir = dirname(__DIR__, 2);
        if ($container->hasExtension('twig')) {
            $container->prependExtensionConfig('twig', [
                'form_themes' => ['@NowoPdfSignableBundle/form/theme.html.twig'],
            ]);
        }
        if ($container->hasExtension('framework')) {
            $container->prependExtensionConfig('framework', [
                'translator' => [
                    'paths' => [
                        $bundleDir . '/src/Resources/translations',
                    ],
                ],
                'assets' => [
                    'packages' => [
                        Configuration::ALIAS => [
                            'base_path' => '/bundles/nowopdfsignable',
                        ],
                    ],
                ],
            ]);
        }

        $this->prependFormKitProfile($container);
    }

    /**
     * Registers the FormKit profile {@see NowoPdfSignableBundle::FORM_KIT_PROFILE} used by every bundle form type
     * (#[FormKitConfig]): translation domain, no auto help / placeholder convention keys, Bootstrap 5 field classes.
     * A host overrides it by defining `nowo_form_kit.profiles.pdf_signable` itself (the bundle then prepends nothing).
     * Does not change the host `default_profile`.
     */
    private function prependFormKitProfile(ContainerBuilder $container): void
    {
        if (!$container->hasExtension('nowo_form_kit')) {
            return;
        }

        foreach ($container->getExtensionConfig('nowo_form_kit') as $config) {
            $profiles = $config['profiles'] ?? null;
            if (is_array($profiles) && array_key_exists(NowoPdfSignableBundle::FORM_KIT_PROFILE, $profiles)) {
                return;
            }
        }

        $container->prependExtensionConfig('nowo_form_kit', [
            'profiles' => [
                NowoPdfSignableBundle::FORM_KIT_PROFILE => self::formKitProfile(),
            ],
        ]);
    }

    /**
     * Default FormKit profile of the bundle. Field types set their own widget classes (`form-control-sm`, ids for the JS);
     * these defaults only apply to options a type leaves unset.
     *
     * @return array<string, mixed>
     */
    public static function formKitProfile(): array
    {
        $input = ['attr' => ['class' => 'form-control']];

        return [
            'alias'              => NowoPdfSignableBundle::FORM_KIT_PROFILE,
            'translation_domain' => NowoPdfSignableBundle::TRANSLATION_DOMAIN,
            'auto_help'          => false,
            'auto_placeholder'   => false,
            'defaults'           => [
                'attr'     => [],
                'row_attr' => [],
            ],
            'field_types' => [
                'text'     => $input,
                'url'      => $input,
                'number'   => $input,
                'integer'  => $input,
                'textarea' => $input,
                'choice'   => ['attr' => ['class' => 'form-select']],
                'checkbox' => [
                    'attr'     => ['class' => 'form-check-input'],
                    'row_attr' => ['class' => 'form-check'],
                ],
            ],
        ];
    }

    /**
     * Maps the pre-move location of the bundled AcroForm scripts (`vendor/nowo-tech/pdf-signable-bundle/.scripts/*.py`)
     * to their runtime location `src/Resources/scripts/` (REQ-PKG-004: `.scripts/` holds development tooling only and
     * is not shipped). Any other path (host scripts, the new location) is returned unchanged. The old paths are
     * deprecated and remapped for BC only; the remap will be removed in 4.0.
     */
    private function bundledScriptPath(mixed $path): mixed
    {
        if (!is_string($path)) {
            return $path;
        }

        return preg_replace(
            '#(pdf-signable-bundle|PdfSignableBundle)([/\\\\])\.scripts[/\\\\](extract_acroform_fields|apply_acroform_patches|process_modified_pdf)\.py$#i',
            '$1$2src$2Resources$2scripts$2$3.py',
            $path,
        ) ?? $path;
    }
}
