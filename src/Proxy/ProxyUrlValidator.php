<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Proxy;

use Closure;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\IpUtils;

use function array_filter;
use function array_merge;
use function array_values;
use function dns_get_record;
use function filter_var;
use function gethostbynamel;
use function in_array;
use function is_array;
use function is_string;
use function ltrim;
use function preg_last_error;
use function preg_match;
use function rtrim;
use function str_contains;
use function str_ends_with;
use function str_starts_with;
use function strpos;
use function strtolower;
use function substr;
use function trim;

use const DNS_AAAA;
use const FILTER_FLAG_IPV4;
use const FILTER_FLAG_NO_PRIV_RANGE;
use const FILTER_FLAG_NO_RES_RANGE;
use const FILTER_VALIDATE_IP;
use const PHP_URL_HOST;
use const PREG_NO_ERROR;

/**
 * Validates URLs for the PDF proxy and AcroForm apply: SSRF mitigation and allowlist (host match or regex).
 *
 * When an allowlist entry is a regex (prefix #), invalid patterns are logged and skipped instead of failing silently.
 */
final class ProxyUrlValidator
{
    /**
     * Non-public ranges blocked in addition to {@see IpUtils::PRIVATE_SUBNETS} and PHP's private/reserved filters.
     */
    private const BLOCKED_SUBNETS = [
        '0.0.0.0/8',
        '10.0.0.0/8',
        '100.64.0.0/10',
        '127.0.0.0/8',
        '169.254.0.0/16',
        '172.16.0.0/12',
        '192.0.0.0/24',
        '192.0.2.0/24',
        '192.168.0.0/16',
        '198.18.0.0/15',
        '198.51.100.0/24',
        '203.0.113.0/24',
        '224.0.0.0/4',
        '240.0.0.0/4',
        '::/128',
        '::1/128',
        '::ffff:0:0/96',
        '64:ff9b::/96',
        '64:ff9b:1::/48',
        '100::/64',
        '2001:db8::/32',
        '2002::/16',
        'fc00::/7',
        'fe80::/10',
        'ff00::/8',
    ];

    /**
     * @param list<string> $proxyUrlAllowlist Host patterns (exact host or subdomain), URL prefixes (with scheme), or regex (prefix #). Empty = allow any (subject to SSRF check).
     * @param LoggerInterface|null $logger Optional logger for invalid regex patterns in allowlist
     * @param (Closure(string): array<mixed>)|null $dnsResolver Optional resolver returning all A/AAAA addresses of a host (tests); defaults to system DNS
     */
    public function __construct(
        #[Autowire(param: 'nowo_pdf_signable.proxy_url_allowlist')]
        private readonly array $proxyUrlAllowlist,
        private readonly ?LoggerInterface $logger = null,
        private readonly ?Closure $dnsResolver = null,
    ) {
    }

    /**
     * Returns true if the URL targets a private, local or reserved host (SSRF mitigation).
     *
     * Every A/AAAA record of the host must be public; hosts that cannot be resolved are blocked (fail closed).
     */
    public function isBlockedForSsrf(string $url): bool
    {
        return $this->resolvePublicIp($url) === null;
    }

    /**
     * Resolves the URL host and returns one of its public IP addresses, or null when the URL must be blocked.
     *
     * Callers should pin the HTTP connection to the returned IP (HttpClient "resolve" option) so that a second
     * DNS lookup cannot be rebound to an internal address.
     */
    public function resolvePublicIp(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!is_string($host)) {
            return null;
        }
        $host = strtolower(rtrim(trim($host, '[]'), '.'));
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost')) {
            return null;
        }
        $ips = filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : $this->resolveHost($host);
        if ($ips === []) {
            return null;
        }
        $preferred = null;
        foreach ($ips as $ip) {
            if (!self::isPublicIp($ip)) {
                return null;
            }
            if ($preferred === null || (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false && !str_contains($preferred, '.'))) {
                $preferred = $ip;
            }
        }

        return $preferred;
    }

    /**
     * Returns true when the IP address is publicly routable (not private, loopback, link-local, CGNAT, reserved…).
     */
    public static function isPublicIp(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }
        // IPv4-mapped / compatible IPv6 (e.g. ::ffff:127.0.0.1): check the embedded IPv4 too.
        if (str_contains($ip, ':') && preg_match('/^::(?:ffff:)?(\d{1,3}(?:\.\d{1,3}){3})$/i', $ip, $m) === 1) {
            return false;
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }

        return !IpUtils::checkIp($ip, array_merge(IpUtils::PRIVATE_SUBNETS, self::BLOCKED_SUBNETS));
    }

    /**
     * Returns true if the URL is allowed by proxy_url_allowlist.
     *
     * Entries: regex (prefix #) matched against the full URL; URL prefix with scheme (e.g. https://cdn.example.com/pdfs/)
     * requiring same scheme, exact host, same port and path prefix; or host pattern (e.g. example.com, optionally with
     * a path) matching the exact host or any subdomain. A leading dot (.example.com) matches subdomains only.
     * When allowlist is empty, returns true (caller should still enforce SSRF). Invalid regex patterns are logged and skipped.
     */
    public function isAllowedByAllowlist(string $url): bool
    {
        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['host']) || $parts['host'] === '') {
            return false;
        }
        foreach ($this->proxyUrlAllowlist as $pattern) {
            if ($pattern === '') {
                continue;
            }
            if (str_starts_with($pattern, '#')) {
                $matched = preg_match($pattern, $url);
                if ($matched === 1) {
                    return true;
                }
                if ($matched === false && preg_last_error() !== PREG_NO_ERROR && $this->logger instanceof LoggerInterface) {
                    $this->logger->warning('Invalid regex in proxy_url_allowlist, pattern skipped', [
                        'pattern'    => $pattern,
                        'preg_error' => preg_last_error(),
                    ]);
                }
                continue;
            }
            if (self::matchesEntry($pattern, $parts)) {
                return true;
            }
        }

        return $this->proxyUrlAllowlist === [];
    }

    /**
     * @param array{scheme?: string, host?: string, port?: int, path?: string} $url Parsed URL
     */
    private static function matchesEntry(string $entry, array $url): bool
    {
        $urlHost = strtolower(rtrim(trim($url['host'] ?? '', '[]'), '.'));
        $urlPath = $url['path'] ?? '';
        if (str_contains($entry, '://')) {
            $e = parse_url($entry);
            if (!is_array($e) || !isset($e['host']) || $e['host'] === '') {
                return false;
            }
            if (strtolower($e['scheme'] ?? '') !== strtolower($url['scheme'] ?? '')
                || strtolower(rtrim(trim($e['host'], '[]'), '.')) !== $urlHost
                || ($e['port'] ?? null) !== ($url['port'] ?? null)) {
                return false;
            }

            return self::matchesPathPrefix($e['path'] ?? '', $urlPath);
        }

        $slash      = strpos($entry, '/');
        $hostPart   = strtolower(rtrim($slash === false ? $entry : substr($entry, 0, $slash), '.'));
        $pathPrefix = $slash === false ? '' : substr($entry, $slash);
        $port       = null;
        if (preg_match('/^(.+):(\d+)$/', $hostPart, $m) === 1 && !str_contains($m[1], ':')) {
            $hostPart = $m[1];
            $port     = (int) $m[2];
        }
        if ($port !== null && ($url['port'] ?? null) !== $port) {
            return false;
        }
        if (str_starts_with($hostPart, '.')) {
            $hostMatches = str_ends_with($urlHost, $hostPart);
        } else {
            $hostPart    = ltrim($hostPart, '.');
            $hostMatches = $hostPart !== '' && ($urlHost === $hostPart || str_ends_with($urlHost, '.' . $hostPart));
        }

        return $hostMatches && self::matchesPathPrefix($pathPrefix, $urlPath);
    }

    private static function matchesPathPrefix(string $prefix, string $path): bool
    {
        if ($prefix === '' || $prefix === '/') {
            return true;
        }

        return str_starts_with($path === '' ? '/' : $path, $prefix);
    }

    /**
     * @return list<string> All A and AAAA addresses of the host (empty when it cannot be resolved)
     */
    private function resolveHost(string $host): array
    {
        if ($this->dnsResolver instanceof Closure) {
            return array_values(array_filter(($this->dnsResolver)($host), is_string(...)));
        }

        $ips  = gethostbynamel($host) ?: [];
        $aaaa = @dns_get_record($host, DNS_AAAA);
        if (is_array($aaaa)) {
            foreach ($aaaa as $record) {
                if (isset($record['ipv6']) && is_string($record['ipv6']) && !in_array($record['ipv6'], $ips, true)) {
                    $ips[] = $record['ipv6'];
                }
            }
        }

        return $ips;
    }
}
