<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\Proxy;

use Nowo\PdfSignableBundle\Proxy\ProxyUrlValidator;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ProxyUrlValidatorTest extends TestCase
{
    public function testIsAllowedByAllowlistEmptyAllowsAny(): void
    {
        $validator = new ProxyUrlValidator([], null);
        self::assertTrue($validator->isAllowedByAllowlist('https://example.com/doc.pdf'));
    }

    public function testIsAllowedByAllowlistSubstringMatch(): void
    {
        $validator = new ProxyUrlValidator(['example.com'], null);
        self::assertTrue($validator->isAllowedByAllowlist('https://example.com/doc.pdf'));
        self::assertFalse($validator->isAllowedByAllowlist('https://other.com/doc.pdf'));
    }

    public function testIsAllowedByAllowlistRegexMatch(): void
    {
        $validator = new ProxyUrlValidator(['#^https://allowed\.example\.com/#'], null);
        self::assertTrue($validator->isAllowedByAllowlist('https://allowed.example.com/doc.pdf'));
        self::assertFalse($validator->isAllowedByAllowlist('https://other.example.com/doc.pdf'));
    }

    public function testIsAllowedByAllowlistEmptyPatternSkipped(): void
    {
        $validator = new ProxyUrlValidator(['', 'example.com', ''], null);
        self::assertTrue($validator->isAllowedByAllowlist('https://example.com/doc.pdf'));
    }

    public function testIsAllowedByAllowlistNonEmptyWithNoMatchReturnsFalse(): void
    {
        $validator = new ProxyUrlValidator(['allowed.example.com'], null);
        self::assertFalse($validator->isAllowedByAllowlist('https://other.example.com/doc.pdf'));
    }

    public function testIsAllowedByAllowlistInvalidRegexWithLoggerLogsWarning(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with(
                'Invalid regex in proxy_url_allowlist, pattern skipped',
                self::callback(static function (array $context): bool {
                    return isset($context['pattern'], $context['preg_error'])
                        && $context['pattern'] === '#invalid(regex';
                }),
            );
        $validator = new ProxyUrlValidator(['#invalid(regex'], $logger);
        self::assertFalse($validator->isAllowedByAllowlist('https://example.com/doc.pdf'));
    }

    public function testIsAllowedByAllowlistInvalidRegexWithoutLoggerDoesNotThrow(): void
    {
        $validator = new ProxyUrlValidator(['#invalid(regex'], null);
        self::assertFalse($validator->isAllowedByAllowlist('https://example.com/doc.pdf'));
    }

    public function testIsBlockedForSsrfLocalhost(): void
    {
        $validator = new ProxyUrlValidator([], null);
        self::assertTrue($validator->isBlockedForSsrf('http://localhost/doc.pdf'));
        self::assertTrue($validator->isBlockedForSsrf('http://127.0.0.1/doc.pdf'));
    }

    public function testIsBlockedForSsrfPublicUrlNotBlocked(): void
    {
        $validator = new ProxyUrlValidator([], null);
        self::assertFalse($validator->isBlockedForSsrf('https://example.com/doc.pdf'));
    }

    public function testIsBlockedForSsrfEmptyOrMissingHostBlocked(): void
    {
        $validator = new ProxyUrlValidator([], null);
        self::assertTrue($validator->isBlockedForSsrf('file:///etc/passwd'));
        self::assertTrue($validator->isBlockedForSsrf('http:///path'));
    }

    public function testIsBlockedForSsrfPrivateRangesBlocked(): void
    {
        $validator = new ProxyUrlValidator([], null);
        self::assertTrue($validator->isBlockedForSsrf('http://10.0.0.1/doc.pdf'));
        self::assertTrue($validator->isBlockedForSsrf('http://192.168.1.1/doc.pdf'));
        self::assertTrue($validator->isBlockedForSsrf('http://169.254.1.1/doc.pdf'));
    }

    public function testIsBlockedForSsrfIpv6LinkLocalBlocked(): void
    {
        $validator = new ProxyUrlValidator([], null);
        self::assertTrue($validator->isBlockedForSsrf('http://[fe80::1]/doc.pdf'));
    }

    public function testIsBlockedForSsrfIpv6LoopbackBlocked(): void
    {
        $validator = new ProxyUrlValidator([], null);
        self::assertTrue($validator->isBlockedForSsrf('http://[::1]/doc.pdf'));
    }
}
