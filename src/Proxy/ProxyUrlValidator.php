<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Proxy;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

use function filter_var;
use function gethostbyname;
use function is_string;
use function preg_last_error;
use function preg_match;
use function sprintf;
use function str_contains;
use function str_starts_with;
use function strtolower;

use const FILTER_FLAG_IPV4;
use const FILTER_FLAG_IPV6;
use const FILTER_VALIDATE_IP;
use const PHP_URL_HOST;
use const PREG_NO_ERROR;

/**
 * Validates URLs for the PDF proxy and AcroForm apply: SSRF mitigation and allowlist (substring or regex).
 *
 * When an allowlist entry is a regex (prefix #), invalid patterns are logged and skipped instead of failing silently.
 */
final class ProxyUrlValidator
{
    /**
     * @param list<string> $proxyUrlAllowlist Substring patterns or regex (prefix #). Empty = allow any (subject to SSRF check).
     * @param LoggerInterface|null $logger Optional logger for invalid regex patterns in allowlist
     */
    public function __construct(
        #[Autowire(param: 'nowo_pdf_signable.proxy_url_allowlist')]
        private readonly array $proxyUrlAllowlist,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * Returns true if the URL targets a private or local host (SSRF mitigation).
     *
     * Blocks: localhost, ::1, 127.0.0.0/8, 10.0.0.0/8, 192.168.0.0/16, 169.254.0.0/16, IPv6 link-local (fe80::).
     */
    public function isBlockedForSsrf(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return true;
        }
        $host = trim($host, '[]');
        if ($host === '') {
            return true;
        }
        $hostLower = strtolower($host);
        if ($hostLower === 'localhost' || $hostLower === '::1' || str_starts_with($hostLower, 'fe80:')) {
            return true;
        }
        $ip = $host;
        if (!filter_var($host, FILTER_VALIDATE_IP)) {
            $resolved = gethostbyname($host);
            if ($resolved === $host) {
                return false;
            }
            $ip = $resolved;
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $long = ip2long($ip);
            if ($long === false) {
                return true;
            }
            $u = (float) sprintf('%u', $long);

            return ($u >= 2130706432 && $u <= 2147483647)
                || ($u >= 167772160 && $u <= 184549375)
                || ($u >= 3232235520 && $u <= 3232301055)
                || ($u >= 2851995648 && $u <= 2852061183);
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return str_starts_with($ip, '::1') || str_starts_with($ip, 'fe80:');
        }

        return false;
    }

    /**
     * Returns true if the URL is allowed by proxy_url_allowlist (substring or regex with prefix #).
     *
     * When allowlist is empty, returns true (caller should still enforce SSRF). Invalid regex patterns are logged and skipped.
     */
    public function isAllowedByAllowlist(string $url): bool
    {
        foreach ($this->proxyUrlAllowlist as $pattern) {
            if ($pattern === '') {
                continue;
            }
            if (str_starts_with($pattern, '#')) {
                $matched = preg_match($pattern, $url);
                if ($matched === 1) {
                    return true;
                }
                if ($matched === false && preg_last_error() !== PREG_NO_ERROR && $this->logger !== null) {
                    $this->logger->warning('Invalid regex in proxy_url_allowlist, pattern skipped', [
                        'pattern'    => $pattern,
                        'preg_error' => preg_last_error(),
                    ]);
                }
                continue;
            }
            if (str_contains($url, $pattern)) {
                return true;
            }
        }

        return $this->proxyUrlAllowlist === [];
    }
}
