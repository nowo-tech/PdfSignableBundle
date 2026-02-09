<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * Loads bundle configuration and services, and prepends Twig and Framework translator paths.
 *
 * Registers the bundle's services (SignatureController, form types) and sets parameters
 * (proxy_enabled, proxy_url_allowlist, example_pdf_url, configs) from config.
 */
final class PdfSignableExtension extends Extension implements PrependExtensionInterface
{
    /**
     * Loads services from Resources/config/services.yaml and sets container parameters.
     *
     * @param array<string, mixed> $configs   Raw configuration arrays (e.g. from config files)
     * @param ContainerBuilder     $container The container builder
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yaml');

        $config = $this->processConfiguration($this->getConfiguration($configs, $container), $configs);

        $container->setParameter(Configuration::ALIAS.'.proxy_enabled', $config['proxy_enabled'] ?? true);
        $container->setParameter(Configuration::ALIAS.'.proxy_url_allowlist', $config['proxy_url_allowlist'] ?? []);
        $container->setParameter(Configuration::ALIAS.'.example_pdf_url', $config['example_pdf_url'] ?? '');
        $container->setParameter(Configuration::ALIAS.'.debug', $config['debug'] ?? false);
        $container->setParameter(Configuration::ALIAS.'.configs', $config['configs'] ?? []);
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
        $bundleDir = \dirname(__DIR__, 2);
        if ($container->hasExtension('twig')) {
            $container->prependExtensionConfig('twig', [
                'paths' => [
                    $bundleDir.'/src/Resources/views' => 'NowoPdfSignable',
                ],
                'form_themes' => ['@NowoPdfSignable/form/theme.html.twig'],
            ]);
        }
        if ($container->hasExtension('framework')) {
            $container->prependExtensionConfig('framework', [
                'translator' => [
                    'paths' => [
                        $bundleDir.'/src/Resources/translations',
                    ],
                ],
            ]);
        }
    }
}
