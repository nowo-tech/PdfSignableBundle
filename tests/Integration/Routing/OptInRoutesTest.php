<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\Integration\Routing;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Loader\AttributeServicesLoader;
use Symfony\Component\Routing\RouterInterface;

use function array_filter;
use function array_keys;
use function array_values;
use function assert;
use function str_starts_with;

/**
 * REQ-SF-004 rule 7: the bundle routes are opt-in. The host's default `routing.controllers` resource must not expose
 * them; the documented routes.yaml import does, and the controllers (not autoconfigured) still serve their pages.
 */
final class OptInRoutesTest extends TestCase
{
    /**
     * Stale caches are removed once, before any kernel boots: deleting them while a booted container's lazy classes may
     * still be autoloaded (later test, same process) would break that container.
     */
    public static function setUpBeforeClass(): void
    {
        self::removeKernelCaches();
    }

    public function testRoutingControllersResourceDoesNotRegisterBundleRoutes(): void
    {
        if (!class_exists(AttributeServicesLoader::class)) {
            self::markTestSkipped('The routing.controllers resource needs symfony/routing >= 7.3.');
        }

        $kernel = new OptInRoutesKernel(false);
        $kernel->boot();

        try {
            $router = $kernel->getContainer()->get('router');
            assert($router instanceof RouterInterface);
            $bundleRoutes = array_values(array_filter(
                array_keys($router->getRouteCollection()->all()),
                static fn (string $name): bool => str_starts_with($name, 'nowo_pdf_signable'),
            ));
            self::assertSame([], $bundleRoutes, 'Bundle routes must only exist when the host imports routes.yaml.');
        } finally {
            $kernel->shutdown();
        }
    }

    public function testImportedRoutesServeTheSignaturePage(): void
    {
        $kernel = new OptInRoutesKernel(true);
        $kernel->boot();

        try {
            $router = $kernel->getContainer()->get('router');
            assert($router instanceof RouterInterface);
            self::assertSame('/pdf-signable/', $router->getRouteCollection()->get('nowo_pdf_signable_index')?->getPath());
            self::assertNotNull($router->getRouteCollection()->get('nowo_pdf_signable_acroform_overrides'));

            $response = $kernel->handle(Request::create('/pdf-signable/'));
            self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
            self::assertStringContainsString('<form', (string) $response->getContent());

            // AbstractController::isCsrfTokenValid() reads the service locator: a forged token is a 403, not a 500.
            $save = $kernel->handle(Request::create('/pdf-signable/acroform/overrides', 'POST', [], [], [], ['HTTP_X_CSRF_TOKEN' => 'forged'], '{"document_key":"opt-in-routes"}'));
            self::assertSame(403, $save->getStatusCode(), (string) $save->getContent());
        } finally {
            $kernel->shutdown();
        }
    }

    private static function removeKernelCaches(): void
    {
        (new Filesystem())->remove(OptInRoutesKernel::cacheRoot());
    }
}
