<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\DependencyInjection;

use Nowo\PdfSignableBundle\DependencyInjection\ProxyUrlAllowlistValidationPass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use const E_USER_WARNING;

final class ProxyUrlAllowlistValidationPassTest extends TestCase
{
    public function testProcessWhenNotDebugDoesNothing(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        $container->setParameter('nowo_pdf_signable.proxy_url_allowlist', ['#invalid(regex']);

        $warnings = [];
        set_error_handler(static function (int $errno, string $errstr) use (&$warnings): bool {
            $warnings[] = $errstr;

            return true;
        }, E_USER_WARNING);
        try {
            (new ProxyUrlAllowlistValidationPass())->process($container);
        } finally {
            restore_error_handler();
        }
        self::assertSame([], $warnings);
    }

    public function testProcessWhenDebugAndInvalidRegexTriggersWarning(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', true);
        $container->setParameter('nowo_pdf_signable.proxy_url_allowlist', ['#invalid(regex']);

        $warnings = [];
        set_error_handler(static function (int $errno, string $errstr) use (&$warnings): bool {
            $warnings[] = $errstr;

            return true;
        }, E_USER_WARNING);
        try {
            (new ProxyUrlAllowlistValidationPass())->process($container);
        } finally {
            restore_error_handler();
        }
        self::assertCount(1, $warnings);
        self::assertStringContainsString('Invalid regex', $warnings[0]);
        self::assertStringContainsString('#invalid(regex', $warnings[0]);
    }

    public function testProcessWhenDebugAndValidRegexDoesNotTriggerWarning(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', true);
        $container->setParameter('nowo_pdf_signable.proxy_url_allowlist', ['#^https://example\.com/#', 'substring']);

        $warnings = [];
        set_error_handler(static function (int $errno, string $errstr) use (&$warnings): bool {
            $warnings[] = $errstr;

            return true;
        }, E_USER_WARNING);
        try {
            (new ProxyUrlAllowlistValidationPass())->process($container);
        } finally {
            restore_error_handler();
        }
        self::assertSame([], $warnings);
    }
}
