<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\Integration\Worker;

use Nowo\FormKitBundle\NowoFormKitBundle;
use Nowo\PdfSignableBundle\NowoPdfSignableBundle;
use Nowo\PdfSignableBundle\Proxy\ProxyUrlValidator;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Twig\Extra\TwigExtraBundle\TwigExtraBundle;

use function dirname;
use function getmypid;
use function hash;
use function str_ends_with;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function sys_get_temp_dir;

/**
 * Host kernel of the REQ-RUNTIME-002 worker test (3.10.1): the bundle with its default role check
 * (`security.access_roles: [ROLE_USER]`), two in-memory users over stateless HTTP Basic, the PDF proxy enabled with an
 * allowlist, a MockHttpClient upstream and a deterministic DNS resolver for the SSRF check.
 */
final class WorkerHostKernel extends Kernel
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
        return sys_get_temp_dir() . '/pdf-signable-worker-host/' . getmypid();
    }

    /**
     * Throwaway HTTP Basic credential of a test user, derived per run (no literal in the repository).
     */
    public static function credentialOf(string $user): string
    {
        return hash('sha256', __FILE__ . '|' . $user);
    }

    /**
     * The real validator with a fake DNS: `*.example` resolves to a public address, anything else does not resolve.
     *
     * @param list<string> $allowlist
     */
    public static function proxyUrlValidator(array $allowlist): ProxyUrlValidator
    {
        return new ProxyUrlValidator($allowlist, null, static fn (string $host): array => str_ends_with($host, '.example') ? ['93.184.215.14'] : []);
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret'                => 'worker-host-test',
            'test'                  => true,
            'http_method_override'  => false,
            'handle_all_throwables' => true,
            'router'                => ['utf8' => true],
            'session'               => ['storage_factory_id' => 'session.storage.factory.mock_file'],
            // No CSRF here (no browser-kit in the dev dependencies): the test posts with http_kernel->handle().
            'form'            => ['enabled' => true, 'csrf_protection' => ['enabled' => false]],
            'csrf_protection' => ['enabled' => false],
            'http_client'     => ['enabled' => true],
            'php_errors'      => ['log' => true],
        ]);
        $container->extension('security', [
            'password_hashers' => [InMemoryUser::class => 'plaintext'],
            'providers'        => [
                'users' => ['memory' => ['users' => [
                    'alice' => ['password' => self::credentialOf('alice'), 'roles' => ['ROLE_USER']],
                    'bob'   => ['password' => self::credentialOf('bob'), 'roles' => ['ROLE_USER']],
                ]]],
            ],
            'firewalls' => ['main' => ['stateless' => true, 'http_basic' => null, 'provider' => 'users']],
        ]);
        $container->extension('twig', ['default_path' => __DIR__ . '/../Routing/templates']);
        $container->extension('nowo_pdf_signable', [
            'proxy_enabled'       => true,
            'proxy_url_allowlist' => ['https://alice.example/', 'https://bob.example/'],
            'example_pdf_url'     => '',
        ]);

        $services = $container->services();
        $services->set('logger', NullLogger::class);
        $services->set(FakePdfUpstream::class);
        $services->set('http_client', MockHttpClient::class)->args([service(FakePdfUpstream::class)]);
        $services->set(ProxyUrlValidator::class)
            ->factory(self::class . '::proxyUrlValidator')
            ->args(['%nowo_pdf_signable.proxy_url_allowlist%']);
        $services->set(SubmissionRecorder::class)->public()->tag('kernel.event_subscriber');
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@NowoPdfSignableBundle/Resources/config/routes.yaml')->prefix('/pdf-signable');
    }
}
