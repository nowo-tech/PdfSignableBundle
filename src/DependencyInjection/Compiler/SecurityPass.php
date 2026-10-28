<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\DependencyInjection\Compiler;

use Nowo\PdfSignableBundle\Security\AllowAllPdfSignableAccessChecker;
use Nowo\PdfSignableBundle\Security\ConfigurablePdfSignableAccessChecker;
use Nowo\PdfSignableBundle\Security\PdfSignableAccessCheckerInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

use function is_string;

/**
 * Wires {@see PdfSignableAccessCheckerInterface}: a host `security.access_checker`, the allow-all checker (only with
 * `security.allow_unauthenticated`) or the role checker, which needs SecurityBundle — without it the build fails
 * (fail closed) instead of leaving the endpoints open.
 */
final class SecurityPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter('nowo_pdf_signable.security.allow_unauthenticated')) {
            return;
        }

        $custom = $container->getParameter('nowo_pdf_signable.security.access_checker');
        if (is_string($custom) && $custom !== '') {
            $container->setAlias(PdfSignableAccessCheckerInterface::class, $custom);

            return;
        }

        if ((bool) $container->getParameter('nowo_pdf_signable.security.allow_unauthenticated')) {
            $container->register(AllowAllPdfSignableAccessChecker::class, AllowAllPdfSignableAccessChecker::class);
            $container->setAlias(PdfSignableAccessCheckerInterface::class, AllowAllPdfSignableAccessChecker::class);

            return;
        }

        if (!$container->has('security.authorization_checker')) {
            throw new InvalidConfigurationException('nowo_pdf_signable protects its AcroForm endpoints and PDF proxy with security.access_roles, which needs symfony/security-bundle. Install it, set security.access_checker to your own PdfSignableAccessCheckerInterface service, or set security.allow_unauthenticated: true (local demos only).');
        }

        $container->register(ConfigurablePdfSignableAccessChecker::class, ConfigurablePdfSignableAccessChecker::class)
            ->setArguments([new Reference('security.authorization_checker'), '%nowo_pdf_signable.security.access_roles%']);
        $container->setAlias(PdfSignableAccessCheckerInterface::class, ConfigurablePdfSignableAccessChecker::class);
    }
}
