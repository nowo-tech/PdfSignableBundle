<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\Unit\DependencyInjection\Compiler;

use Nowo\PdfSignableBundle\DependencyInjection\Compiler\SecurityPass;
use Nowo\PdfSignableBundle\Security\AllowAllPdfSignableAccessChecker;
use Nowo\PdfSignableBundle\Security\ConfigurablePdfSignableAccessChecker;
use Nowo\PdfSignableBundle\Security\PdfSignableAccessCheckerInterface;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

final class SecurityPassTest extends TestCase
{
    public function testSkipsWhenTheBundleIsNotLoaded(): void
    {
        (new SecurityPass())->process($container = new ContainerBuilder());

        self::assertFalse($container->hasAlias(PdfSignableAccessCheckerInterface::class));
    }

    public function testHostCheckerWins(): void
    {
        $container = $this->container(checker: 'app.signable_checker', allow: true);
        (new SecurityPass())->process($container);

        self::assertSame('app.signable_checker', (string) $container->getAlias(PdfSignableAccessCheckerInterface::class));
    }

    public function testAllowUnauthenticatedUsesTheAllowAllChecker(): void
    {
        $container = $this->container(allow: true);
        (new SecurityPass())->process($container);

        self::assertSame(AllowAllPdfSignableAccessChecker::class, (string) $container->getAlias(PdfSignableAccessCheckerInterface::class));
    }

    public function testRoleCheckerNeedsSecurityBundle(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('symfony/security-bundle');
        (new SecurityPass())->process($this->container());
    }

    public function testRoleCheckerIsTheDefault(): void
    {
        $container = $this->container();
        $container->setDefinition('security.authorization_checker', new Definition(stdClass::class));
        (new SecurityPass())->process($container);

        self::assertSame(ConfigurablePdfSignableAccessChecker::class, (string) $container->getAlias(PdfSignableAccessCheckerInterface::class));
        self::assertSame('%nowo_pdf_signable.security.access_roles%', $container->getDefinition(ConfigurablePdfSignableAccessChecker::class)->getArgument(1));
    }

    private function container(?string $checker = null, bool $allow = false): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('nowo_pdf_signable.security.access_roles', ['ROLE_USER']);
        $container->setParameter('nowo_pdf_signable.security.access_checker', $checker);
        $container->setParameter('nowo_pdf_signable.security.allow_unauthenticated', $allow);

        return $container;
    }
}
