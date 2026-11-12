<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\Integration\Container;

use Nowo\FormKitBundle\NowoFormKitBundle;
use Nowo\PdfSignableBundle\NowoPdfSignableBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Twig\Extra\TwigExtraBundle\TwigExtraBundle;

use function dirname;
use function getmypid;
use function sys_get_temp_dir;

/**
 * Standard host with the bundle defaults (role check on ROLE_USER through symfony/security-bundle, proxy on, AcroForm
 * off) plus the one required value, a proxy allowlist, and the documented route import (3.11.0 `lint:container` test).
 */
final class DefaultConfigKernel extends Kernel
{
    use MicroKernelTrait;

    public function __construct()
    {
        parent::__construct('test', true);
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new TwigBundle();
        yield new TwigExtraBundle();
        yield new SecurityBundle();
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
        return sys_get_temp_dir() . '/pdf-signable-default-config/' . getmypid();
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret'               => 'default-config-test',
            'test'                 => true,
            'http_method_override' => false,
            'router'               => ['utf8' => true],
            'session'              => ['storage_factory_id' => 'session.storage.factory.mock_file'],
            'form'                 => ['enabled' => true],
            'csrf_protection'      => ['enabled' => true],
            'http_client'          => ['enabled' => true],
        ]);
        $container->extension('security', [
            'providers' => ['users' => ['memory' => ['users' => []]]],
            'firewalls' => ['main' => ['lazy' => true, 'provider' => 'users']],
        ]);
        // Defaults everywhere, except the one value the bundle requires with the proxy on (fail closed without it).
        $container->extension('nowo_pdf_signable', ['proxy_url_allowlist' => ['https://cdn.example.com/']]);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@NowoPdfSignableBundle/Resources/config/routes.yaml')->prefix('/pdf-signable');
    }
}
