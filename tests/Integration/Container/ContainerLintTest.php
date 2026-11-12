<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\Integration\Container;

use Nowo\PdfSignableBundle\Tests\Integration\DddHost\DddHostKernel;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * REQ-ARCH-001 (3.11.0): `lint:container` passes (argument types of every service definition checked against the
 * constructors) for the standard host with the default configuration and for the DDD host configuration.
 */
final class ContainerLintTest extends TestCase
{
    protected function setUp(): void
    {
        $this->cleanUp();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
    }

    public function testDefaultConfigurationPassesLintContainer(): void
    {
        $this->assertLintPasses(new DefaultConfigKernel());
    }

    public function testDddHostConfigurationPassesLintContainer(): void
    {
        $this->assertLintPasses(new DddHostKernel());
    }

    private function assertLintPasses(KernelInterface $kernel): void
    {
        $application = new Application($kernel);
        $application->setAutoExit(false);
        $tester = new ApplicationTester($application);

        $status = $tester->run(['command' => 'lint:container', '--no-interaction' => true]);
        $kernel->shutdown();

        self::assertSame(0, $status, $tester->getDisplay());
        self::assertStringContainsString('The container was linted successfully', $tester->getDisplay());
    }

    private function cleanUp(): void
    {
        (new Filesystem())->remove([DefaultConfigKernel::cacheRoot(), DddHostKernel::cacheRoot()]);
    }
}
