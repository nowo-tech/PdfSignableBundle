<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\DependencyInjection;

use Nowo\PdfSignableBundle\DependencyInjection\ProxyUrlAllowlistValidationPass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class ProxyUrlAllowlistValidationPassTest extends TestCase
{
    public function testProcessWhenParameterNotSetDoesNothing(): void
    {
        $this->expectNotToPerformAssertions();
        $container = new ContainerBuilder();
        (new ProxyUrlAllowlistValidationPass())->process($container);
    }

    public function testProcessWhenAllowlistRequiredAndEmptyThrows(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('nowo_pdf_signable.proxy_enabled', true);
        $container->setParameter('nowo_pdf_signable.proxy_url_allowlist', []);
        $container->setParameter('nowo_pdf_signable.proxy_url_allowlist_required', true);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('proxy_url_allowlist_required is true');
        (new ProxyUrlAllowlistValidationPass())->process($container);
    }

    public function testProcessWhenAllowlistRequiredButProxyDisabledDoesNotThrow(): void
    {
        $this->expectNotToPerformAssertions();
        $container = new ContainerBuilder();
        $container->setParameter('nowo_pdf_signable.proxy_enabled', false);
        $container->setParameter('nowo_pdf_signable.proxy_url_allowlist', []);
        $container->setParameter('nowo_pdf_signable.proxy_url_allowlist_required', true);

        (new ProxyUrlAllowlistValidationPass())->process($container);
    }

    public function testProcessWhenInvalidRegexThrows(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('nowo_pdf_signable.proxy_enabled', true);
        $container->setParameter('nowo_pdf_signable.proxy_url_allowlist', ['#invalid(regex']);
        $container->setParameter('nowo_pdf_signable.proxy_url_allowlist_required', false);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('invalid regex');
        (new ProxyUrlAllowlistValidationPass())->process($container);
    }

    public function testProcessWhenEmptyOrSubstringPatternsSkipped(): void
    {
        $this->expectNotToPerformAssertions();
        $container = new ContainerBuilder();
        $container->setParameter('nowo_pdf_signable.proxy_enabled', true);
        $container->setParameter('nowo_pdf_signable.proxy_url_allowlist', ['', 'substring', '#^https://#']);
        $container->setParameter('nowo_pdf_signable.proxy_url_allowlist_required', false);

        (new ProxyUrlAllowlistValidationPass())->process($container);
    }

    public function testProcessWhenProxyEnabledParameterMissingDefaultsToTrueAndRequiredThrows(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('nowo_pdf_signable.proxy_url_allowlist', []);
        $container->setParameter('nowo_pdf_signable.proxy_url_allowlist_required', true);
        // proxy_enabled intentionally omitted → defaults to true in the pass

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('proxy_url_allowlist_required is true');
        (new ProxyUrlAllowlistValidationPass())->process($container);
    }
}
