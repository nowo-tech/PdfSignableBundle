<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Defines the bundle configuration tree (proxy_enabled, proxy_url_allowlist, example_pdf_url, configs).
 *
 * @see PdfSignableExtension
 */
final class Configuration implements ConfigurationInterface
{
    /** Configuration root key (nowo_pdf_signable). */
    public const ALIAS = 'nowo_pdf_signable';

    /**
     * Builds the configuration tree for the nowo_pdf_signable bundle.
     *
     * @return TreeBuilder The tree builder with proxy_enabled, proxy_url_allowlist, example_pdf_url and configs nodes
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(self::ALIAS);
        $root = $treeBuilder->getRootNode();

        $root
            ->children()
                ->booleanNode('proxy_enabled')
                    ->info('Enable proxy endpoint to fetch external PDFs (avoids CORS)')
                    ->defaultValue(true)
                ->end()
                ->arrayNode('proxy_url_allowlist')
                    ->info('When non-empty, proxy only fetches URLs matching one entry. Each entry: substring of URL, or regex if prefixed with # (e.g. #^https://example\.com/#)')
                    ->scalarPrototype()->end()
                    ->defaultValue([])
                ->end()
                ->scalarNode('example_pdf_url')
                    ->info('Default PDF URL for demo/preload')
                    ->defaultValue('https://www.transportes.gob.es/recursos_mfom/paginabasica/recursos/11_07_2019_modelo_orientativo_de_contrato_de_arrendamiento_de_vivienda.pdf')
                ->end()
                ->arrayNode('configs')
                    ->info('Named configurations for SignatureCoordinatesType. Use form option config: "name" to apply.')
                    ->useAttributeAsKey('name')
                    ->variablePrototype()->end()
                    ->defaultValue([])
                ->end()
            ->end();

        return $treeBuilder;
    }
}
