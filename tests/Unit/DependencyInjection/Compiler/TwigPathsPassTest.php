<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\DependencyInjection\Compiler;

use Nowo\PdfSignableBundle\DependencyInjection\Compiler\TwigPathsPass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

use function dirname;

final class TwigPathsPassTest extends TestCase
{
    public function testProcessAddsBundleViewsPath(): void
    {
        $container = new ContainerBuilder();
        $loader    = new Definition();
        $container->setDefinition('twig.loader.native', $loader);

        (new TwigPathsPass())->process($container);

        self::assertSame(
            [['addPath', [dirname(__DIR__, 4) . '/src/Resources/views', 'NowoPdfSignableBundle']]],
            $loader->getMethodCalls(),
        );
    }

    public function testProcessPrependsOverridePathWhenPresent(): void
    {
        $container  = new ContainerBuilder();
        $loader     = new Definition();
        $projectDir = sys_get_temp_dir() . '/pdf-signable-twig-paths-' . bin2hex(random_bytes(4));
        mkdir($projectDir . '/templates/bundles/NowoPdfSignableBundle', 0777, true);

        $container->setDefinition('twig.loader.native', $loader);
        $container->setParameter('kernel.project_dir', $projectDir);

        try {
            (new TwigPathsPass())->process($container);
        } finally {
            rmdir($projectDir . '/templates/bundles/NowoPdfSignableBundle');
            rmdir($projectDir . '/templates/bundles');
            rmdir($projectDir . '/templates');
            rmdir($projectDir);
        }

        self::assertSame(
            [
                ['prependPath', [$projectDir . '/templates/bundles/NowoPdfSignableBundle', 'NowoPdfSignableBundle']],
                ['addPath', [dirname(__DIR__, 4) . '/src/Resources/views', 'NowoPdfSignableBundle']],
            ],
            $loader->getMethodCalls(),
        );
    }

    public function testProcessUsesAliasWhenDefined(): void
    {
        $container = new ContainerBuilder();
        $loader    = new Definition();
        $container->setDefinition('twig.loader.native_filesystem', $loader);
        $container->setAlias('twig.loader.native', new Alias('twig.loader.native_filesystem'));

        (new TwigPathsPass())->process($container);

        self::assertSame('addPath', $loader->getMethodCalls()[0][0]);
    }

    public function testProcessSkipsWhenTwigLoaderMissing(): void
    {
        $container = new ContainerBuilder();

        (new TwigPathsPass())->process($container);

        self::assertFalse($container->hasDefinition('twig.loader.native'));
    }
}
