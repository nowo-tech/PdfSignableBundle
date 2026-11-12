<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\Integration\DddHost;

use Nowo\FormKitBundle\NowoFormKitBundle;
use Nowo\PdfSignableBundle\NowoPdfSignableBundle;
use Nowo\PdfSignableBundle\Tests\Integration\DddHost\Fixtures\Domain\SignatureRequestLog;
use Nowo\PdfSignableBundle\Tests\Integration\DddHost\Fixtures\Infrastructure\SignatureSubmittedListener;
use Nowo\PdfSignableBundle\Tests\Integration\DddHost\Fixtures\Infrastructure\StandardHostProbe;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Twig\Extra\TwigExtraBundle\TwigExtraBundle;

use function dirname;
use function getmypid;
use function sys_get_temp_dir;

/**
 * Host kernel with a DDD listener (framework-free accessors only) and a standard listener (deprecated accessors) on the
 * same submission events.
 */
final class DddHostKernel extends Kernel
{
    use MicroKernelTrait;

    public function __construct()
    {
        parent::__construct('test', false);
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new TwigBundle();
        yield new TwigExtraBundle();
        yield new NowoFormKitBundle();
        yield new NowoPdfSignableBundle();
    }

    public function getProjectDir(): string
    {
        return dirname(__DIR__, 3);
    }

    public function getCacheDir(): string
    {
        return self::cacheRoot() . '/cache';
    }

    public function getLogDir(): string
    {
        return self::cacheRoot() . '/log';
    }

    public static function cacheRoot(): string
    {
        return sys_get_temp_dir() . '/pdf-signable-ddd-host/' . getmypid();
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret'                => 'ddd-host-test',
            'test'                  => true,
            'http_method_override'  => false,
            'handle_all_throwables' => true,
            'router'                => ['utf8' => true],
            'session'               => ['storage_factory_id' => 'session.storage.factory.mock_file'],
            // No CSRF here (no browser-kit in the dev dependencies): the test posts with kernel->handle().
            'form'            => ['enabled' => true, 'csrf_protection' => ['enabled' => false]],
            'csrf_protection' => ['enabled' => false],
            'http_client'     => ['enabled' => true],
            'php_errors'      => ['log' => true],
        ]);
        $container->extension('twig', ['default_path' => __DIR__ . '/../Routing/templates']);
        $container->extension('nowo_pdf_signable', [
            'proxy_enabled'   => false,
            'example_pdf_url' => '',
            'security'        => ['allow_unauthenticated' => true],
        ]);

        $services = $container->services();
        $services->set('logger', NullLogger::class);
        $services->set(SignatureRequestLog::class)->public();
        $services->set(StandardHostProbe::class)->public()->tag('kernel.event_subscriber');
        $services->set(SignatureSubmittedListener::class)->autowire()->autoconfigure();
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@NowoPdfSignableBundle/Resources/config/routes.yaml')->prefix('/pdf-signable');
    }
}
