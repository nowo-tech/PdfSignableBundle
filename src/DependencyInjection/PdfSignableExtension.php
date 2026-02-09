<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;

/**
 * Loads bundle configuration and services, and prepends Twig and Framework translator paths.
 */
final class PdfSignableExtension extends Extension implements PrependExtensionInterface
{
    /**
     * Loads services and sets container parameters from the bundle configuration.
     *
     * @param array<string, mixed> $configs
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        $config = $this->processConfiguration($this->getConfiguration($configs, $container), $configs);

        $container->setParameter(Configuration::ALIAS . '.proxy_enabled', $config['proxy_enabled'] ?? true);
        $container->setParameter(Configuration::ALIAS . '.example_pdf_url', $config['example_pdf_url'] ?? '');
        $container->setParameter(Configuration::ALIAS . '.configs', $config['configs'] ?? []);
    }

    /**
     * Returns the configuration alias (nowo_pdf_signable).
     */
    public function getAlias(): string
    {
        return Configuration::ALIAS;
    }

    /**
     * Prepends Twig paths/form_themes and Framework translator paths.
     *
     * @param ContainerBuilder $container
     */
    public function prepend(ContainerBuilder $container): void
    {
        $bundleDir = \dirname(__DIR__, 2);
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
