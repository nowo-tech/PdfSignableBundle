<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\Proxy;

use Nowo\PdfSignableBundle\Proxy\ProxyUrlValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ProxyUrlValidatorTest extends TestCase
{
    public function testIsAllowedByAllowlistEmptyAllowsAny(): void
    {
        $validator = new ProxyUrlValidator([]);
        self::assertTrue($validator->isAllowedByAllowlist('https://example.com/doc.pdf'));
    }

    public function testIsAllowedByAllowlistSubstringMatch(): void
    {
        $validator = new ProxyUrlValidator(['example.com']);
        self::assertTrue($validator->isAllowedByAllowlist('https://example.com/doc.pdf'));
        self::assertFalse($validator->isAllowedByAllowlist('https://other.com/doc.pdf'));
    }

    public function testIsAllowedByAllowlistRegexMatch(): void
    {
        $validator = new ProxyUrlValidator(['#^https://allowed\.example\.com/#']);
        self::assertTrue($validator->isAllowedByAllowlist('https://allowed.example.com/doc.pdf'));
        self::assertFalse($validator->isAllowedByAllowlist('https://other.example.com/doc.pdf'));
    }

    public function testIsAllowedByAllowlistEmptyPatternSkipped(): void
    {
        $validator = new ProxyUrlValidator(['', 'example.com', '']);
        self::assertTrue($validator->isAllowedByAllowlist('https://example.com/doc.pdf'));
    }

    public function testIsAllowedByAllowlistNonEmptyWithNoMatchReturnsFalse(): void
    {
        $validator = new ProxyUrlValidator(['allowed.example.com']);
        self::assertFalse($validator->isAllowedByAllowlist('https://other.example.com/doc.pdf'));
    }

    public function testIsAllowedByAllowlistInvalidRegexWithLoggerLogsWarning(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with(
                'Invalid regex in proxy_url_allowlist, pattern skipped',
                self::callback(static fn (array $context): bool => isset($context['pattern'], $context['preg_error'])
                    && $context['pattern'] === '#invalid(regex'),
            );
        $validator = new ProxyUrlValidator(['#invalid(regex'], $logger);
        self::assertFalse($validator->isAllowedByAllowlist('https://example.com/doc.pdf'));
    }

    public function testIsAllowedByAllowlistInvalidRegexWithoutLoggerDoesNotThrow(): void
    {
        $validator = new ProxyUrlValidator(['#invalid(regex']);
        self::assertFalse($validator->isAllowedByAllowlist('https://example.com/doc.pdf'));
    }

    public function testIsBlockedForSsrfLocalhost(): void
    {
        $validator = new ProxyUrlValidator([]);
        self::assertTrue($validator->isBlockedForSsrf('http://localhost/doc.pdf'));
        self::assertTrue($validator->isBlockedForSsrf('http://127.0.0.1/doc.pdf'));
    }

    public function testIsBlockedForSsrfPublicUrlNotBlocked(): void
    {
        $validator = new ProxyUrlValidator([], null, static fn (): array => ['93.184.215.14']);
        self::assertFalse($validator->isBlockedForSsrf('https://example.com/doc.pdf'));
    }

    public function testIsBlockedForSsrfEmptyOrMissingHostBlocked(): void
    {
        $validator = new ProxyUrlValidator([]);
        self::assertTrue($validator->isBlockedForSsrf('file:///etc/passwd'));
        self::assertTrue($validator->isBlockedForSsrf('http:///path'));
    }

    public function testIsBlockedForSsrfPrivateRangesBlocked(): void
    {
        $validator = new ProxyUrlValidator([]);
        self::assertTrue($validator->isBlockedForSsrf('http://10.0.0.1/doc.pdf'));
        self::assertTrue($validator->isBlockedForSsrf('http://192.168.1.1/doc.pdf'));
        self::assertTrue($validator->isBlockedForSsrf('http://169.254.1.1/doc.pdf'));
    }

    public function testIsBlockedForSsrfIpv6LinkLocalBlocked(): void
    {
        $validator = new ProxyUrlValidator([]);
        self::assertTrue($validator->isBlockedForSsrf('http://[fe80::1]/doc.pdf'));
    }

    public function testIsBlockedForSsrfIpv6LoopbackBlocked(): void
    {
        $validator = new ProxyUrlValidator([]);
        self::assertTrue($validator->isBlockedForSsrf('http://[::1]/doc.pdf'));
    }

    public function testIsBlockedForSsrfPublicIpv6NotBlocked(): void
    {
        $validator = new ProxyUrlValidator([]);
        self::assertFalse($validator->isBlockedForSsrf('http://[2606:4700:4700::1111]/doc.pdf'));
    }

    public function testIsBlockedForSsrfHostBracketsOnlyTrimmedToEmptyBlocked(): void
    {
        $validator = new ProxyUrlValidator([]);
        self::assertTrue($validator->isBlockedForSsrf('http://[]/doc.pdf'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function nonPublicUrlProvider(): iterable
    {
        yield '172.16/12 start' => ['http://172.16.0.1/doc.pdf'];
        yield '172.16/12 end' => ['http://172.31.255.254/doc.pdf'];
        yield '0.0.0.0/8' => ['http://0.0.0.0/doc.pdf'];
        yield '0.x' => ['http://0.1.2.3/doc.pdf'];
        yield 'CGNAT 100.64/10' => ['http://100.64.0.1/doc.pdf'];
        yield 'CGNAT end' => ['http://100.127.255.254/doc.pdf'];
        yield 'metadata' => ['http://169.254.169.254/latest/meta-data/'];
        yield 'loopback 127/8' => ['http://127.1.2.3/doc.pdf'];
        yield 'benchmark 198.18/15' => ['http://198.18.0.1/doc.pdf'];
        yield 'multicast' => ['http://224.0.0.1/doc.pdf'];
        yield 'broadcast' => ['http://255.255.255.255/doc.pdf'];
        yield 'ULA fc00::/7' => ['http://[fc00::1]/doc.pdf'];
        yield 'ULA fd00::' => ['http://[fd12:3456::1]/doc.pdf'];
        yield 'IPv4-mapped loopback' => ['http://[::ffff:127.0.0.1]/doc.pdf'];
        yield 'IPv4-mapped metadata' => ['http://[::ffff:169.254.169.254]/doc.pdf'];
        yield 'IPv4-mapped hex' => ['http://[::ffff:7f00:1]/doc.pdf'];
        yield 'unspecified ::' => ['http://[::]/doc.pdf'];
        yield 'NAT64 private' => ['http://[64:ff9b::a00:1]/doc.pdf'];
        yield 'localhost subdomain' => ['http://foo.localhost/doc.pdf'];
        yield 'localhost trailing dot' => ['http://localhost./doc.pdf'];
    }

    #[DataProvider('nonPublicUrlProvider')]
    public function testIsBlockedForSsrfNonPublicRanges(string $url): void
    {
        $validator = new ProxyUrlValidator([], null, static fn (): array => ['93.184.215.14']);
        self::assertTrue($validator->isBlockedForSsrf($url), $url);
    }

    public function testIsBlockedForSsrfUnresolvableHostFailsClosed(): void
    {
        $validator = new ProxyUrlValidator([], null, static fn (): array => []);
        self::assertTrue($validator->isBlockedForSsrf('https://aaaa-only.example.com/doc.pdf'));
        self::assertNull($validator->resolvePublicIp('https://aaaa-only.example.com/doc.pdf'));
    }

    public function testIsBlockedForSsrfDefaultResolverUnresolvableHostFailsClosed(): void
    {
        $validator = new ProxyUrlValidator([]);
        self::assertTrue($validator->isBlockedForSsrf('https://does-not-exist-pdfsignable.invalid/doc.pdf'));
    }

    public function testIsBlockedForSsrfAnyPrivateRecordBlocksHost(): void
    {
        $validator = new ProxyUrlValidator([], null, static fn (): array => ['93.184.215.14', '2606:4700::1', '10.0.0.1']);
        self::assertTrue($validator->isBlockedForSsrf('https://mixed.example.com/doc.pdf'));
    }

    public function testIsBlockedForSsrfAaaaOnlyPrivateBlocked(): void
    {
        $validator = new ProxyUrlValidator([], null, static fn (): array => ['fd00::1']);
        self::assertTrue($validator->isBlockedForSsrf('https://v6.example.com/doc.pdf'));
    }

    public function testResolvePublicIpPrefersIpv4AndIgnoresNonStrings(): void
    {
        $validator = new ProxyUrlValidator([], null, static fn (): array => ['2606:4700::1', 42, '93.184.215.14']);
        self::assertSame('93.184.215.14', $validator->resolvePublicIp('https://dual.example.com/doc.pdf'));

        $v6only = new ProxyUrlValidator([], null, static fn (): array => ['2606:4700::1']);
        self::assertSame('2606:4700::1', $v6only->resolvePublicIp('https://v6.example.com/doc.pdf'));
    }

    public function testIsPublicIpRejectsInvalidInput(): void
    {
        self::assertFalse(ProxyUrlValidator::isPublicIp('not-an-ip'));
        self::assertTrue(ProxyUrlValidator::isPublicIp('8.8.8.8'));
    }

    /** Bypass from the audit: allowlist value in the query string must not match. */
    public function testAllowlistHostInQueryStringDoesNotMatch(): void
    {
        $validator = new ProxyUrlValidator(['example.com']);
        self::assertFalse($validator->isAllowedByAllowlist('https://evil/?x=example.com'));
        self::assertFalse($validator->isAllowedByAllowlist('http://169.254.169.254/?x=example.com'));
        self::assertFalse($validator->isAllowedByAllowlist('https://example.com.evil.net/doc.pdf'));
        self::assertFalse($validator->isAllowedByAllowlist('https://notexample.com/doc.pdf'));
        self::assertFalse($validator->isAllowedByAllowlist('https://example.com@evil.net/doc.pdf'));
        self::assertTrue($validator->isAllowedByAllowlist('https://example.com/doc.pdf'));
        self::assertTrue($validator->isAllowedByAllowlist('https://cdn.example.com/doc.pdf'));
        self::assertTrue($validator->isAllowedByAllowlist('https://EXAMPLE.com./doc.pdf'));
    }

    public function testAllowlistUrlPrefixEntryRequiresSchemeHostPortAndPath(): void
    {
        $validator = new ProxyUrlValidator(['https://allowed.example.com/pdfs/']);
        self::assertTrue($validator->isAllowedByAllowlist('https://allowed.example.com/pdfs/a.pdf'));
        self::assertFalse($validator->isAllowedByAllowlist('https://allowed.example.com/private/a.pdf'));
        self::assertFalse($validator->isAllowedByAllowlist('http://allowed.example.com/pdfs/a.pdf'));
        self::assertFalse($validator->isAllowedByAllowlist('https://allowed.example.com:8443/pdfs/a.pdf'));
        self::assertFalse($validator->isAllowedByAllowlist('https://sub.allowed.example.com/pdfs/a.pdf'));
        self::assertFalse($validator->isAllowedByAllowlist('https://evil.net/?u=https://allowed.example.com/pdfs/'));

        $root = new ProxyUrlValidator(['https://allowed.example.com/']);
        self::assertTrue($root->isAllowedByAllowlist('https://allowed.example.com'));

        $broken = new ProxyUrlValidator(['https:///nohost']);
        self::assertFalse($broken->isAllowedByAllowlist('https://allowed.example.com/'));
    }

    public function testAllowlistHostEntryWithPathPortAndLeadingDot(): void
    {
        $withPath = new ProxyUrlValidator(['example.com/pdfs/']);
        self::assertTrue($withPath->isAllowedByAllowlist('https://example.com/pdfs/a.pdf'));
        self::assertFalse($withPath->isAllowedByAllowlist('https://example.com/other/a.pdf'));

        $withPort = new ProxyUrlValidator(['example.com:8443']);
        self::assertTrue($withPort->isAllowedByAllowlist('https://example.com:8443/a.pdf'));
        self::assertFalse($withPort->isAllowedByAllowlist('https://example.com/a.pdf'));

        $subOnly = new ProxyUrlValidator(['.example.com']);
        self::assertTrue($subOnly->isAllowedByAllowlist('https://cdn.example.com/a.pdf'));
        self::assertFalse($subOnly->isAllowedByAllowlist('https://example.com/a.pdf'));

        $dotOnly = new ProxyUrlValidator(['.']);
        self::assertFalse($dotOnly->isAllowedByAllowlist('https://example.com/a.pdf'));
    }

    public function testAllowlistPathPrefixIgnoresDotSegmentTraversal(): void
    {
        foreach (['example.com/pdfs/', 'https://example.com/pdfs/'] as $entry) {
            $validator = new ProxyUrlValidator([$entry]);
            // HttpClient normalises these to /admin/secret.pdf before sending the request.
            self::assertFalse($validator->isAllowedByAllowlist('https://example.com/pdfs/../admin/secret.pdf'), $entry);
            self::assertFalse($validator->isAllowedByAllowlist('https://example.com/pdfs/%2e%2e/admin/secret.pdf'), $entry);
            self::assertFalse($validator->isAllowedByAllowlist('https://example.com/pdfs/%2E%2E/admin/secret.pdf'), $entry);
            self::assertFalse($validator->isAllowedByAllowlist('https://example.com/pdfs/sub/../../admin/secret.pdf'), $entry);
            self::assertFalse($validator->isAllowedByAllowlist('https://example.com/pdfs/..'), $entry);
            // Dot segments that stay below the prefix are still allowed.
            self::assertTrue($validator->isAllowedByAllowlist('https://example.com/pdfs/./a.pdf'), $entry);
            self::assertTrue($validator->isAllowedByAllowlist('https://example.com/pdfs/sub/../a.pdf'), $entry);
            self::assertTrue($validator->isAllowedByAllowlist('https://example.com/pdfs/sub/.'), $entry);
            self::assertTrue($validator->isAllowedByAllowlist('https://example.com/pdfs/a/./b/../c.pdf'), $entry);
        }
    }

    public function testAllowlistUrlWithoutHostIsRejected(): void
    {
        $validator = new ProxyUrlValidator([]);
        self::assertFalse($validator->isAllowedByAllowlist('/relative/path.pdf'));
    }
}
