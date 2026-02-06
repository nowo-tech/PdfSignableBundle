<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Defines the bundle configuration tree (proxy_enabled, example_pdf_url, etc.).
 */
final class Configuration implements ConfigurationInterface
{
    /** Configuration root key. */
    public const ALIAS = 'nowo_pdf_signable';

    /**
     * Builds the configuration tree for nowo_pdf_signable.
     *
     * @return TreeBuilder
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
                ->scalarNode('example_pdf_url')
                    ->info('Default PDF URL for demo/preload')
                    ->defaultValue('https://www.transportes.gob.es/recursos_mfom/paginabasica/recursos/11_07_2019_modelo_orientativo_de_contrato_de_arrendamiento_de_vivienda.pdf')
                ->end()
            ->end();

        return $treeBuilder;
    }
}
